<?php

namespace App\Policies;

use App\Models\FeedPost;
use App\Models\User;

class FeedPostPolicy
{
    /**
     * Mirrors FeedPost::scopeVisibleTo so a single status page and the feed
     * agree on who may read a post.
     */
    public function view(?User $user, FeedPost $feedPost): bool
    {
        if ($feedPost->audience === 'public') {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($feedPost->user_id === $user->id) {
            return true;
        }

        return $user->marga_id !== null
            && $feedPost->audienceMargas()->whereKey($user->marga_id)->exists();
    }

    /** Only the author may rewrite their own status. */
    public function update(User $user, FeedPost $feedPost): bool
    {
        return $feedPost->user_id === $user->id;
    }

    /** The author, or staff acting as moderators. */
    public function delete(User $user, FeedPost $feedPost): bool
    {
        return $feedPost->user_id === $user->id || $user->isStaff();
    }
}
