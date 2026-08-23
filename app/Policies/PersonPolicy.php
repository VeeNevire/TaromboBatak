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
            || ($user->marga_id !== null
                && $person->marga_id === $user->marga_id);
    }

    public function update(User $user, Person $person): bool
    {
        return $this->view($user, $person);
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->isStaff();
    }
}
