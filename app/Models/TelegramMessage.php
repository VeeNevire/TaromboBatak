<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['telegram_account_id', 'telegram_dialog_id', 'telegram_message_id', 'telegram_sender_id', 'sender_name', 'body', 'media_type', 'is_outgoing', 'sent_at', 'metadata'])]
class TelegramMessage extends Model
{
    /** @return BelongsTo<TelegramAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    /** @return BelongsTo<TelegramDialog, $this> */
    public function dialog(): BelongsTo
    {
        return $this->belongsTo(TelegramDialog::class, 'telegram_dialog_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'sent_at' => 'datetime', 'is_outgoing' => 'boolean'];
    }
}
