<?php

namespace App\Jobs;

use App\Models\TelegramUpdate;
use App\Services\TelegramUpdateProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessTelegramUpdate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [2, 10, 30, 60];

    public int $uniqueFor = 300;

    public function __construct(public int $telegramUpdateId) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramUpdateProcessor $processor): void
    {
        $update = TelegramUpdate::query()->findOrFail($this->telegramUpdateId);

        if ($update->status === TelegramUpdate::STATUS_PROCESSED) {
            return;
        }

        $update->increment('attempts');
        $processor->process($update->payload);
        $update->update([
            'status' => TelegramUpdate::STATUS_PROCESSED,
            'processed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->telegramUpdateId;
    }

    public function failed(?Throwable $exception): void
    {
        TelegramUpdate::query()->whereKey($this->telegramUpdateId)->update([
            'status' => TelegramUpdate::STATUS_FAILED,
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
