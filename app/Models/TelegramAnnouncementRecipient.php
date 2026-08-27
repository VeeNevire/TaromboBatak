<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $telegram_announcement_id
 * @property int|null $user_id
 * @property int|null $chat_id
 * @property string $recipient_name
 * @property string $status
 * @property-read TelegramAnnouncement $announcement
 * @property-read User|null $user
 */
#[Fillable(['telegram_announcement_id', 'user_id', 'chat_id', 'recipient_name', 'status', 'telegram_message_id', 'error', 'sent_at'])]
class TelegramAnnouncementRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /** @return BelongsTo<TelegramAnnouncement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(TelegramAnnouncement::class, 'telegram_announcement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refreshAnnouncementCounts(): void
    {
        $announcement = $this->announcement()->firstOrFail();
        $counts = $announcement->recipients()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $announcement->update([
            'sent_count' => (int) ($counts[self::STATUS_SENT] ?? 0),
            'failed_count' => (int) ($counts[self::STATUS_FAILED] ?? 0),
            'skipped_count' => (int) ($counts[self::STATUS_SKIPPED] ?? 0),
            'completed_at' => ((int) ($counts[self::STATUS_PENDING] ?? 0)) === 0 ? now() : null,
        ]);
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
