<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string|null $location
 * @property string|null $registration_url
 * @property Carbon $date
 * @property bool $published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'created_by',
    'marga_id',
    'title',
    'description',
    'location',
    'registration_url',
    'date',
    'published',
    'status',
    'review_version',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('published', true)
            ->where('status', self::STATUS_APPROVED);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }
}
