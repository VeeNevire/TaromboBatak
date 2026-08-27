<?php

namespace App\Console\Commands;

use App\Contracts\TelegramBot;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('telegram:poll {--once : Ambil satu batch lalu berhenti}')]
#[Description('Menerima update Telegram menggunakan long polling tanpa webhook')]
class TelegramPoll extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TelegramBot $telegram): int
    {
        if (! config('services.telegram.bot_token')) {
            $this->components->error('TELEGRAM_BOT_TOKEN belum diisi.');

            return self::FAILURE;
        }

        try {
            $webhook = $telegram->getWebhookInfo();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! empty($webhook['url'])) {
            $this->components->error('Bot masih memakai webhook. Hapus webhook sebelum menjalankan long polling.');

            return self::FAILURE;
        }

        TelegramUpdate::query()
            ->whereIn('status', [TelegramUpdate::STATUS_PENDING, TelegramUpdate::STATUS_FAILED])
            ->orderBy('id')
            ->each(fn (TelegramUpdate $update) => ProcessTelegramUpdate::dispatch($update->id));

        $this->components->info('Telegram long polling aktif.');

        do {
            $lock = Cache::lock('telegram:poller', 40);

            if (! $lock->get()) {
                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(2);

                continue;
            }

            try {
                $offset = ((int) TelegramUpdate::query()->max('update_id')) + 1;
                $updates = $telegram->getUpdates($offset);

                foreach ($updates as $payload) {
                    if (! isset($payload['update_id'])) {
                        continue;
                    }

                    $update = TelegramUpdate::query()->firstOrCreate(
                        ['update_id' => $payload['update_id']],
                        ['payload' => $payload],
                    );

                    if ($update->wasRecentlyCreated || $update->status !== TelegramUpdate::STATUS_PROCESSED) {
                        ProcessTelegramUpdate::dispatch($update->id);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->components->warn($exception->getMessage());
            } finally {
                $lock->release();
            }

            if (! $this->option('once')) {
                sleep(1);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
