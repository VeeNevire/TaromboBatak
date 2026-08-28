<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $recipient_id
 * @property string $status
 * @property Carbon|null $reviewed_at
 * @property-read User $requester
 * @property-read User $recipient
 */
#[Fillable(['requester_id', 'recipient_id', 'status', 'reviewed_at'])]
class ContactRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
