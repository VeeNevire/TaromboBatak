<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $feed_post_id
 * @property string $path
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FeedPost $feedPost
 */
#[Fillable(['feed_post_id', 'path', 'position'])]
class FeedPostImage extends Model
{
    /** @return BelongsTo<FeedPost, $this> */
    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class);
    }

    /**
     * Publicly reachable URL for the stored image.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
