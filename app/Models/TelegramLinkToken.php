<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $chat_group_id
 * @property string $purpose
 * @property string $token_hash
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $used_at
 * @property-read User $user
 * @property-read ChatGroup|null $chatGroup
 */
#[Fillable(['user_id', 'chat_group_id', 'purpose', 'token_hash', 'expires_at', 'used_at'])]
class TelegramLinkToken extends Model
{
    public const PURPOSE_ACCOUNT = 'account';

    public const PURPOSE_GROUP = 'group';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ChatGroup, $this> */
    public function chatGroup(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
