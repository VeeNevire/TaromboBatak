<?php

namespace App\Models;

use Database\Factories\TelegramAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $telegram_user_id
 * @property int $private_chat_id
 * @property string|null $username
 * @property string $display_name
 * @property-read User $user
 */
#[Fillable(['user_id', 'telegram_user_id', 'private_chat_id', 'username', 'display_name', 'linked_at'])]
class TelegramAccount extends Model
{
    /** @use HasFactory<TelegramAccountFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['linked_at' => 'datetime'];
    }
}
