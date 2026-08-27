<?php

namespace App\Policies;

use App\Models\ChatGroup;
use App\Models\User;

class ChatGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canUseGroups();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChatGroup $chatGroup): bool
    {
        return $chatGroup->hasMember($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canUseGroups();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ChatGroup $chatGroup): bool
    {
        return $chatGroup->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ChatGroup $chatGroup): bool
    {
        return $chatGroup->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ChatGroup $chatGroup): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ChatGroup $chatGroup): bool
    {
        return false;
    }

    public function announce(User $user, ChatGroup $chatGroup): bool
    {
        return $chatGroup->owner_id === $user->id
            || ($user->isSubAdmin() && $user->marga_id === $chatGroup->marga_id);
    }
}
