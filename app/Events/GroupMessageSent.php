<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GroupMessage $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('groups.'.$this->message->chat_group_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'group.message.sent';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');
        $senderName = $this->message->sender_id === null
            ? $this->message->telegram_sender_name
            : $this->message->sender->name;

        return [
            'id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $senderName,
            'body' => $this->message->body,
            'source' => $this->message->source,
            'delivery_status' => $this->message->telegram_delivery_status,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
