<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $body
 * @property string $audience
 * @property Carbon|null $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $author
 * @property-read Collection<int, FeedComment> $comments
 * @property-read Collection<int, FeedPostLike> $likes
 * @property-read Collection<int, FeedPostImage> $images
 */
#[Fillable(['user_id', 'body', 'audience', 'edited_at'])]
class FeedPost extends Model
{
    use HasFactory;

    protected $attributes = ['audience' => 'public'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Marga, $this> */
    public function audienceMargas(): BelongsToMany
    {
        return $this->belongsToMany(Marga::class, 'feed_post_marga');
    }

    /** @param Builder<FeedPost> $query */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if ($user === null) {
            $query->where('audience', 'public');

            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('audience', 'public')->orWhere('user_id', $user->id);
            if ($user->marga_id !== null) {
                $query->orWhere(function (Builder $query) use ($user) {
                    $query->where('audience', 'marga')
                        ->whereHas('audienceMargas', fn (Builder $margas) => $margas->whereKey($user->marga_id));
                });
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<FeedComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    /** @return HasMany<FeedPostLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(FeedPostLike::class);
    }

    /** @return HasMany<FeedPostImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(FeedPostImage::class)->orderBy('position');
    }
}
