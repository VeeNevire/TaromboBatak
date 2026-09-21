<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $marga_id
 * @property Carbon|null $last_read_at
 * @property-read User $user
 * @property-read Marga $marga
 */
#[Fillable(['user_id', 'marga_id', 'last_read_at'])]
class MargaChatRead extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Marga, $this> */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }
}
