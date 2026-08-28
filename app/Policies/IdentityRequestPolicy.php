<?php

namespace App\Policies;

use App\Models\IdentityRequest;
use App\Models\User;

class IdentityRequestPolicy
{
    public function review(User $user, IdentityRequest $identityRequest): bool
    {
        return $user->canReviewContributions()
            && $identityRequest->status === IdentityRequest::STATUS_PENDING
            && ($user->isAdmin() || $identityRequest->person->marga_id === $user->marga_id);
    }

    public function cancel(User $user, IdentityRequest $identityRequest): bool
    {
        return $user->isAdmin();
    }
}
