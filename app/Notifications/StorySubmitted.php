<?php

namespace App\Notifications;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StorySubmitted extends Notification
{
    use Queueable;

    public function __construct(public Story $story) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'story_id' => $this->story->id,
            'creator_name' => $this->story->creator?->name,
            'story_title' => $this->story->title,
            'classification' => $this->story->classification,
            'message' => ($this->story->creator->name ?? 'User').' mengajukan cerita '.$this->story->title.'.',
        ];
    }
}
