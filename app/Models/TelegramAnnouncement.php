<?php

namespace App\Models;

use Database\Factories\TelegramAnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $sender_id
 * @property string $target_type
 * @property int|null $chat_group_id
 * @property string $body
 * @property int $sent_count
 * @property int $failed_count
 * @property int $skipped_count
 * @property-read User $sender
 * @property-read ChatGroup|null $chatGroup
 * @property-read Collection<int, TelegramAnnouncementRecipient> $recipients
 */
#[Fillable(['sender_id', 'target_type', 'chat_group_id', 'body', 'sent_count', 'failed_count', 'skipped_count', 'completed_at'])]
class TelegramAnnouncement extends Model
{
    /** @use HasFactory<TelegramAnnouncementFactory> */
    use HasFactory;

    public const TARGET_CONTACTS = 'contacts';

    public const TARGET_GROUP = 'group';

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<ChatGroup, $this> */
    public function chatGroup(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class);
    }

    /** @return HasMany<TelegramAnnouncementRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(TelegramAnnouncementRecipient::class);
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
