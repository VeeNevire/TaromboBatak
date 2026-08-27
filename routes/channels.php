<?php

use App\Models\ChatGroup;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('groups.{chatGroupId}', function (User $user, int $chatGroupId): bool {
    return ChatGroup::query()->find($chatGroupId)?->hasMember($user) ?? false;
});
