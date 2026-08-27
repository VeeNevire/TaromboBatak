<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $center_person_id
 * @property string $view
 * @property string $path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Person|null $centerPerson
 */
#[Fillable(['user_id', 'center_person_id', 'view', 'path'])]
class TaromboSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\TaromboSnapshotFactory> */
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
}
