<?php

namespace App\Models;

use Database\Factories\ChatGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $owner_id
 * @property int $marga_id
 * @property string $name
 * @property int|null $telegram_chat_id
 * @property string|null $telegram_title
 * @property-read User $owner
 * @property-read Marga $marga
 * @property-read Collection<int, ChatGroupMember> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, GroupMessage> $messages
 * @property-read GroupMessage|null $latestMessage
 * @property-read int $members_count
 */
#[Fillable(['owner_id', 'marga_id', 'name', 'telegram_chat_id', 'telegram_title', 'telegram_linked_at'])]
class ChatGroup extends Model
{
    /** @use HasFactory<ChatGroupFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Marga, $this> */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    /** @return HasMany<ChatGroupMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_group_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<GroupMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    /** @return HasOne<GroupMessage, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(GroupMessage::class)->latestOfMany();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    protected function casts(): array
    {
        return ['telegram_linked_at' => 'datetime'];
    }
}
