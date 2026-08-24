<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Story $story): bool
    {
        return $user->isStaff() || $story->created_by === $user->id;
    }

    public function delete(User $user, Story $story): bool
    {
        return $this->update($user, $story);
    }
}
