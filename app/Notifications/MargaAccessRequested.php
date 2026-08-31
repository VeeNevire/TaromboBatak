<?php

namespace App\Notifications;

use App\Models\MargaAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MargaAccessRequested extends Notification
{
    use Queueable;

    public function __construct(public MargaAccessRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'marga_access_request_id' => $this->request->id,
            'requester_name' => $this->request->requester->name,
            'marga_name' => $this->request->marga->name,
            'message' => $this->request->requester->name.' meminta akses membuka silsilah marga '.$this->request->marga->name.'.',
        ];
    }
}
