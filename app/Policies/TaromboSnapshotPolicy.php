<?php

namespace App\Policies;

use App\Models\TaromboSnapshot;
use App\Models\User;

class TaromboSnapshotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TaromboSnapshot $taromboSnapshot): bool
    {
        return $taromboSnapshot->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaromboSnapshot $taromboSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaromboSnapshot $taromboSnapshot): bool
    {
        return $taromboSnapshot->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaromboSnapshot $taromboSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaromboSnapshot $taromboSnapshot): bool
    {
        return false;
    }
}
