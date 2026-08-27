<?php

namespace App\Models;

use Database\Factories\GroupMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chat_group_id
 * @property int|null $sender_id
 * @property string $source
 * @property string $body
 * @property int|null $telegram_sender_id
 * @property string|null $telegram_sender_name
 * @property int|null $telegram_message_id
 * @property string|null $telegram_delivery_status
 * @property string|null $telegram_error
 * @property-read ChatGroup $chatGroup
 * @property-read User|null $sender
 */
#[Fillable([
    'chat_group_id', 'sender_id', 'source', 'body', 'telegram_sender_id',
    'telegram_sender_name', 'telegram_message_id', 'telegram_delivery_status',
    'telegram_error', 'sent_at',
])]
class GroupMessage extends Model
{
    /** @use HasFactory<GroupMessageFactory> */
    use HasFactory;

    public const SOURCE_APP = 'app';

    public const SOURCE_TELEGRAM = 'telegram';

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_FAILED = 'failed';

    /** @return BelongsTo<ChatGroup, $this> */
    public function chatGroup(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
