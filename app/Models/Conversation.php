<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_one_id
 * @property int $user_two_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $userOne
 * @property-read User $userTwo
 * @property-read Collection<int, Message> $messages
 * @property-read Message|null $latestMessage
 */
#[Fillable(['user_one_id', 'user_two_id'])]
class Conversation extends Model
{
    /**
     * @return Builder<Conversation>
     */
    public static function between(User $firstUser, User $secondUser): Builder
    {
        [$userOneId, $userTwoId] = self::participantIds($firstUser, $secondUser);

        return self::query()
            ->where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId);
    }

    /**
     * @return array{user_one_id: int, user_two_id: int}
     */
    public static function participantAttributes(User $firstUser, User $secondUser): array
    {
        [$userOneId, $userTwoId] = self::participantIds($firstUser, $secondUser);

        return [
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ];
    }

    public function otherParticipantId(int $userId): int
    {
        return $this->user_one_id === $userId ? $this->user_two_id : $this->user_one_id;
    }

    public function includes(User $user): bool
    {
        return in_array($user->id, [$this->user_one_id, $this->user_two_id], true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * @return array{int, int}
     */
    private static function participantIds(User $firstUser, User $secondUser): array
    {
        return [
            min($firstUser->id, $secondUser->id),
            max($firstUser->id, $secondUser->id),
        ];
    }
}
