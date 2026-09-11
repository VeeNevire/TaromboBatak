<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $feed_post_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FeedPost $feedPost
 * @property-read User $user
 */
#[Fillable(['feed_post_id', 'user_id'])]
class FeedPostLike extends Model
{
    /** @return BelongsTo<FeedPost, $this> */
    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
