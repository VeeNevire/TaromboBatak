<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $account_id
 * @property int|null $actor_id
 * @property string $account_name
 * @property string $account_email
 * @property string $action
 * @property string $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $account
 * @property-read User|null $actor
 */
#[Fillable(['account_id', 'actor_id', 'account_name', 'account_email', 'action', 'description', 'metadata'])]
#[Hidden(['metadata'])]
class ActivityLog extends Model
{
    /** @return BelongsTo<User, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
