<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventSubmitted extends Notification
{
    use Queueable;

    public function __construct(public Event $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'creator_name' => $this->event->creator?->name,
            'event_title' => $this->event->title,
            'message' => ($this->event->creator?->name ?? 'User').' mengajukan event '.$this->event->title.'.',
        ];
    }
}
