<?php

namespace App\Notifications;

use App\Models\ContributionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FatherMatchSubmitted extends Notification
{
    use Queueable;

    public function __construct(public ContributionRequest $contribution) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'contribution_request_id' => $this->contribution->id,
            'requester_name' => $this->contribution->requester->name,
            'subject_name' => $this->contribution->subjectPerson->name,
            'matched_father_name' => $this->contribution->matchedFather->name,
            'message' => $this->contribution->requester->name.' mengajukan pencocokan Ayah '.$this->contribution->matchedFather->name.'.',
        ];
    }
}
