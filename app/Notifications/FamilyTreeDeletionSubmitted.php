<?php

namespace App\Notifications;

use App\Models\FamilyTreeDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FamilyTreeDeletionSubmitted extends Notification
{
    use Queueable;

    public function __construct(public FamilyTreeDeletionRequest $deletion) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'family_tree_deletion_request_id' => $this->deletion->id,
            'tree_name' => $this->deletion->tree_name,
            'root_name' => $this->deletion->root_name,
            'requester_name' => $this->deletion->requester?->name,
            'message' => ($this->deletion->requester?->name ?? 'User').' mengajukan penghapusan '.$this->deletion->tree_name.'.',
        ];
    }
}
