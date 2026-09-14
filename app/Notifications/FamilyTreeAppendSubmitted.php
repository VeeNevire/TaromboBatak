<?php

namespace App\Notifications;

use App\Models\FamilyTreeAppendRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FamilyTreeAppendSubmitted extends Notification
{
    use Queueable;

    public function __construct(public FamilyTreeAppendRequest $appendRequest) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'family_tree_append_request_id' => $this->appendRequest->id,
            'family_tree_id' => $this->appendRequest->family_tree_id,
            'requester_name' => $this->appendRequest->requester->name,
            'member_name' => $this->appendRequest->payload['name'] ?? 'Anggota baru',
            'message' => $this->appendRequest->requester->name.' mengajukan penambahan anggota '.$this->appendRequest->payload['name'].' pada '.$this->appendRequest->familyTree->name.'.',
        ];
    }
}
