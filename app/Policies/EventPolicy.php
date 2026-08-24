<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->marga_id !== null;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isStaff() || $event->created_by === $user->id;
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
