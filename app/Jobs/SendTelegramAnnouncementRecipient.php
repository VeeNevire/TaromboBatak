<?php

namespace App\Jobs;

use App\Contracts\TelegramBot;
use App\Models\TelegramAnnouncementRecipient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTelegramAnnouncementRecipient implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [2, 10, 30, 60];

    public function __construct(public int $recipientId) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramBot $telegram): void
    {
        $recipient = TelegramAnnouncementRecipient::query()
            ->with(['announcement.sender'])
            ->findOrFail($this->recipientId);

        if ($recipient->status !== TelegramAnnouncementRecipient::STATUS_PENDING || $recipient->chat_id === null) {
            return;
        }

        $result = $telegram->sendMessage(
            $recipient->chat_id,
            "Pengumuman dari {$recipient->announcement->sender->name}:\n{$recipient->announcement->body}",
        );

        $recipient->update([
            'status' => TelegramAnnouncementRecipient::STATUS_SENT,
            'telegram_message_id' => $result['message_id'] ?? null,
            'error' => null,
            'sent_at' => now(),
        ]);
        $recipient->refreshAnnouncementCounts();
    }

    public function uniqueId(): string
    {
        return (string) $this->recipientId;
    }

    public function failed(?Throwable $exception): void
    {
        $recipient = TelegramAnnouncementRecipient::query()->find($this->recipientId);

        if ($recipient) {
            $recipient->update([
                'status' => TelegramAnnouncementRecipient::STATUS_FAILED,
                'error' => $exception?->getMessage(),
            ]);
            $recipient->refreshAnnouncementCounts();
        }
    }
}
