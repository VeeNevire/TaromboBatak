<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A browser or installed app that agreed to receive Tarombo push
 * notifications. Guests may subscribe too, so the user is optional.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id',
    'endpoint',
    'endpoint_hash',
    'public_key',
    'auth_token',
    'content_encoding',
    'user_agent',
])]
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
