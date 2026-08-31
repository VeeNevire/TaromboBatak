<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['telegram_account_id', 'telegram_peer_id', 'type', 'title', 'username', 'last_message_at', 'unread_count', 'last_read_at'])]
class TelegramDialog extends Model
{
    /** @return BelongsTo<TelegramAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    /** @return HasMany<TelegramMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'last_read_at' => 'datetime', 'unread_count' => 'integer'];
    }
}
