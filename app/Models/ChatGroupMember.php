<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_group_id
 * @property int $user_id
 * @property string $role
 * @property Carbon|null $last_read_at
 * @property-read ChatGroup $chatGroup
 * @property-read User $user
 */
#[Fillable(['chat_group_id', 'user_id', 'role', 'last_read_at'])]
class ChatGroupMember extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_MEMBER = 'member';

    /** @return BelongsTo<ChatGroup, $this> */
    public function chatGroup(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }
}
