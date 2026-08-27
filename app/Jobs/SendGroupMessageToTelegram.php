<?php

namespace App\Jobs;

use App\Contracts\TelegramBot;
use App\Events\GroupMessageSent;
use App\Models\GroupMessage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendGroupMessageToTelegram implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [2, 10, 30, 60];

    public function __construct(public int $groupMessageId) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramBot $telegram): void
    {
        $message = GroupMessage::query()->with(['sender', 'chatGroup'])->findOrFail($this->groupMessageId);

        if ($message->source !== GroupMessage::SOURCE_APP || $message->telegram_message_id !== null || $message->chatGroup->telegram_chat_id === null) {
            return;
        }

        $result = $telegram->sendMessage(
            $message->chatGroup->telegram_chat_id,
            $message->sender->name.":\n".$message->body,
        );

        $message->update([
            'telegram_message_id' => $result['message_id'] ?? null,
            'telegram_delivery_status' => GroupMessage::DELIVERY_SENT,
            'telegram_error' => null,
        ]);
        GroupMessageSent::dispatch($message->fresh());
    }

    public function uniqueId(): string
    {
        return (string) $this->groupMessageId;
    }

    public function failed(?Throwable $exception): void
    {
        $message = GroupMessage::query()->find($this->groupMessageId);

        if ($message) {
            $message->update([
                'telegram_delivery_status' => GroupMessage::DELIVERY_FAILED,
                'telegram_error' => $exception?->getMessage(),
            ]);
            GroupMessageSent::dispatch($message->fresh());
        }
    }
}
