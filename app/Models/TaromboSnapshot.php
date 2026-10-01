<?php

namespace App\Models;

use Database\Factories\TaromboSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $center_person_id
 * @property int|null $tarombo_frame_id
 * @property int|null $source_snapshot_id
 * @property string $view
 * @property string|null $title
 * @property int|null $resolution
 * @property string|null $paper_size
 * @property string|null $orientation
 * @property array<int, int>|null $included_person_ids
 * @property string $path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Person|null $centerPerson
 */
#[Fillable(['user_id', 'center_person_id', 'tarombo_frame_id', 'source_snapshot_id', 'view', 'title', 'resolution', 'paper_size', 'orientation', 'included_person_ids', 'path'])]
class TaromboSnapshot extends Model
{
    /** @use HasFactory<TaromboSnapshotFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function centerPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'center_person_id');
    }

    /** @return BelongsTo<TaromboFrame, $this> */
    public function taromboFrame(): BelongsTo
    {
        return $this->belongsTo(TaromboFrame::class);
    }

    /**
     * The original tree this Compile Gambar result was produced from.
     *
     * @return BelongsTo<TaromboSnapshot, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(TaromboSnapshot::class, 'source_snapshot_id');
    }

    /** Match the same title (or fallback name) that is displayed in the gallery. */
    public function scopeMatchingTitle(Builder $query, string $search): Builder
    {
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

        return $query->where(fn (Builder $titles) => $titles
            ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])
            ->orWhere(fn (Builder $fallback) => $fallback->whereNull('title')
                ->whereHas('centerPerson', fn (Builder $people) => $people->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])))
            ->when(str_contains(mb_strtolower('Pohon Tarombo'), mb_strtolower($search)), fn (Builder $titles) => $titles
                ->orWhere(fn (Builder $fallback) => $fallback->whereNull('title')->whereDoesntHave('centerPerson'))));
    }

    protected function casts(): array
    {
        return ['included_person_ids' => 'array'];
    }
}
