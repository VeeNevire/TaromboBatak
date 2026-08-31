<?php

namespace App\Models;

use Database\Factories\MargaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 * @property string|null $image
 * @property int|null $identity_person_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'color', 'image', 'identity_person_id'])]
class Marga extends Model
{
    /** @use HasFactory<MargaFactory> */
    use HasFactory;

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function identityPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'identity_person_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function managedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'marga_user');
    }

    /** @return HasMany<MargaAccessRequest, $this> */
    public function accessRequests(): HasMany
    {
        return $this->hasMany(MargaAccessRequest::class);
    }
}
