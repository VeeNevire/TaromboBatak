<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['user_one_id', 'user_two_id'])]
class ContactDisconnect extends Model
{
    /** @return array{user_one_id: int, user_two_id: int} */
    public static function attributesFor(int $firstUserId, int $secondUserId): array
    {
        return $firstUserId < $secondUserId
            ? ['user_one_id' => $firstUserId, 'user_two_id' => $secondUserId]
            : ['user_one_id' => $secondUserId, 'user_two_id' => $firstUserId];
    }

    /** @return Collection<int, int> */
    public static function otherUserIdsFor(User $user): Collection
    {
        return static::query()
            ->where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->get(['user_one_id', 'user_two_id'])
            ->map(fn (self $disconnect): int => $disconnect->user_one_id === $user->id
                ? $disconnect->user_two_id
                : $disconnect->user_one_id);
    }
}
