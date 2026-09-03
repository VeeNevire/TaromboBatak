<?php

namespace App\Models;

use Database\Factories\TelegramAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $telegram_user_id
 * @property int $private_chat_id
 * @property string|null $username
 * @property string $display_name
 * @property-read User $user
 * @property string|null $session_path
 * @property string $connection_status
 */
#[Fillable(['user_id', 'telegram_user_id', 'private_chat_id', 'phone', 'username', 'display_name', 'linked_at', 'session_path', 'connection_status', 'last_error', 'last_seen_at'])]
class TelegramAccount extends Model
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    public function isMtprotoConnected(): bool
    {
        return filled($this->session_path)
            && $this->connection_status === self::STATUS_CONNECTED;
    }

    /** @use HasFactory<TelegramAccountFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TelegramDialog, $this> */
    public function dialogs(): HasMany
    {
        return $this->hasMany(TelegramDialog::class);
    }

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'linked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
