<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use App\Services\TelegramMtproto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telegram:mtproto-listen {account? : ID akun Telegram tertentu}')]
#[Description('Mendengarkan pesan baru dari akun Telegram user melalui MTProto')]
class TelegramMtprotoListen extends Command
{
    public function handle(TelegramMtproto $telegram): int
    {
        $query = TelegramAccount::query();
        if ($this->argument('account')) {
            $accountId = (int) $this->argument('account');
            $query->where(function ($query) use ($accountId): void {
                $query->whereKey($accountId)->orWhere('user_id', $accountId);
            })->where('connection_status', '!=', TelegramAccount::STATUS_DISCONNECTED);
        } else {
            $query->where('connection_status', TelegramAccount::STATUS_CONNECTED);
        }

        $accounts = $query->get();
        if ($accounts->isEmpty()) {
            $this->components->warn('Tidak ada akun Telegram yang terhubung.');

            return self::SUCCESS;
        }

        if ($accounts->count() > 1 && ! $this->argument('account')) {
            $this->components->warn('Pilih satu akun untuk listener: php artisan telegram:mtproto-listen {account-id}');

            return self::INVALID;
        }

        $account = $accounts->first();
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
}
