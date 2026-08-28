<?php

namespace App\Policies;

use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestPolicy
{
    public function review(User $user, ContactRequest $contactRequest): bool
    {
        return $contactRequest->recipient_id === $user->id
            && $contactRequest->status === ContactRequest::STATUS_PENDING;
    }
}
