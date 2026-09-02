<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use App\Services\TelegramMtproto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telegram:mtproto-listen {account? : ID akun Telegram tertentu} {--all : Menjalankan semua session MTProto aktif} {--worker= : Nomor worker Supervisor} {--workers= : Jumlah total worker Supervisor}')]
#[Description('Mendengarkan pesan baru dari akun Telegram user melalui MTProto')]
class TelegramMtprotoListen extends Command
{
    public function handle(TelegramMtproto $telegram): int
    {
        if ($this->option('all')) {
            return $this->listenAll($telegram);
        }

        $workers = max(1, (int) ($this->option('workers') ?: 1));
        $worker = max(0, (int) ($this->option('worker') ?: 0));

        if ($worker >= $workers) {
            $this->components->error("Worker {$worker} berada di luar jumlah worker {$workers}.");

            return self::INVALID;
        }

        $query = TelegramAccount::query();
        if ($this->argument('account')) {
            $accountId = (int) $this->argument('account');
            $query->where(function ($query) use ($accountId): void {
                $query->whereKey($accountId)->orWhere('user_id', $accountId);
            });
        } else {
            $query->whereRaw('MOD(id, ?) = ?', [$workers, $worker]);
        }

        $query->whereNotNull('session_path')
            ->whereIn('connection_status', [
                TelegramAccount::STATUS_CONNECTED,
                TelegramAccount::STATUS_ERROR,
            ])
            ->orderBy('id');

        do {
            $account = $query->first();

            if ($account) {
                break;
            }

            $this->components->info("Worker {$worker}/{$workers}: menunggu akun MTProto yang terhubung...");
            sleep(15);
        } while (! $this->argument('account'));

        if (! $account) {
            $this->components->warn('Akun MTProto tidak ditemukan atau session tidak aktif.');

            return self::SUCCESS;
        }

        try {
            $account->update(['connection_status' => TelegramAccount::STATUS_CONNECTED, 'last_error' => null]);
            $telegram->listenAccount($account);
        } catch (Throwable $exception) {
            $account->update(['connection_status' => TelegramAccount::STATUS_ERROR, 'last_error' => $exception->getMessage()]);
            report($exception);
            $this->components->error("Akun {$account->id}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function listenAll(TelegramMtproto $telegram): int
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid') || ! function_exists('posix_kill')) {
            $this->components->error('PHP CLI harus mengaktifkan ext-pcntl dan ext-posix untuk mode --all.');

            return self::FAILURE;
        }

        /** @var array<int, array{pid: int, stopping: bool}> $children */
        $children = [];

        pcntl_async_signals(true);
        $shutdown = function () use (&$children): void {
            foreach ($children as $child) {
                posix_kill($child['pid'], SIGTERM);
            }

            exit(0);
        };
        pcntl_signal(SIGTERM, $shutdown);
        pcntl_signal(SIGINT, $shutdown);

        while (true) {
            $accounts = TelegramAccount::query()
                ->whereNotNull('session_path')
                ->whereIn('connection_status', [
                    TelegramAccount::STATUS_CONNECTED,
                    TelegramAccount::STATUS_ERROR,
                ])
                ->get()
                ->keyBy('id');

            foreach ($accounts as $account) {
                if (isset($children[$account->id])) {
                    continue;
                }

                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->components->error("Gagal membuat process untuk akun {$account->id}.");

                    continue;
                }

                if ($pid === 0) {
                    pcntl_signal(SIGTERM, SIG_DFL);
                    pcntl_signal(SIGINT, SIG_DFL);
                    $this->listenAccount($telegram, $account->id);
                }

                $children[$account->id] = ['pid' => $pid, 'stopping' => false];
                $this->components->info("MTProto listener akun {$account->id} berjalan (PID {$pid}).");
            }

            foreach ($children as $accountId => &$child) {
                if (! isset($accounts[$accountId]) && ! $child['stopping']) {
                    posix_kill($child['pid'], SIGTERM);
                    $child['stopping'] = true;
                }

                $result = pcntl_waitpid($child['pid'], $status, WNOHANG);
                if ($result === $child['pid']) {
                    unset($children[$accountId]);
                }
            }
            unset($child);

            sleep(5);
        }
    }

    private function listenAccount(TelegramMtproto $telegram, int $accountId): never
    {
        try {
            // Child process harus membuka ulang koneksi database setelah fork.
            app('db')->disconnect();
            $account = TelegramAccount::query()->find($accountId);

            if (! $account?->isMtprotoConnected() && $account?->connection_status !== TelegramAccount::STATUS_ERROR) {
                exit(0);
            }

            $account?->update([
                'connection_status' => TelegramAccount::STATUS_CONNECTED,
                'last_error' => null,
            ]);

            if ($account) {
                $telegram->listenAccount($account);
            }

            exit(0);
        } catch (Throwable $exception) {
            TelegramAccount::query()->whereKey($accountId)->update([
                'connection_status' => TelegramAccount::STATUS_ERROR,
                'last_error' => $exception->getMessage(),
            ]);
            report($exception);
            exit(1);
        }
    }
}
