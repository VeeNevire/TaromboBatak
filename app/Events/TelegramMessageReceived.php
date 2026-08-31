<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TelegramMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TelegramMessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TelegramMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->message->account->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'telegram.message.received';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'dialog_id' => $this->message->telegram_dialog_id,
            'telegram_message_id' => $this->message->telegram_message_id,
            'body' => $this->message->body,
            'sender_name' => $this->message->sender_name,
            'media_type' => $this->message->media_type,
            'is_outgoing' => $this->message->is_outgoing,
            'sent_at' => $this->message->sent_at?->toISOString(),
        ];
    }
}
