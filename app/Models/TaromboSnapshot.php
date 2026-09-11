<?php

namespace App\Models;

use Database\Factories\TaromboSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $center_person_id
 * @property int|null $tarombo_frame_id
 * @property string $view
 * @property string $path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Person|null $centerPerson
 */
#[Fillable(['user_id', 'center_person_id', 'tarombo_frame_id', 'view', 'path'])]
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
}
