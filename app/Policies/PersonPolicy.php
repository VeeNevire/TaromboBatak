<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->marga_id !== null;
    }

    public function view(User $user, Person $person): bool
    {
        return $user->isStaff()
            || ($person->marga_id === $user->marga_id
                && $person->familyTrees()->where('family_trees.user_id', $user->id)->exists());
    }

    public function update(User $user, Person $person): bool
    {
        return $user->isStaff()
            || ($person->marga_id === $user->marga_id
                && $person->created_by !== null
                && $person->created_by === $user->id);
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->isStaff();
    }
}
