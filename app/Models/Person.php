<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $alias
 * @property int|null $marga_id
 * @property int|null $parent_id
 * @property string|null $birth_year
 * @property string|null $death_year
 * @property string|null $image
 * @property string|null $bio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Marga|null $marga
 * @property-read Person|null $parent
 * @property-read Collection<int, Person> $children
 */
#[Fillable(['name', 'alias', 'marga_id', 'parent_id', 'birth_year', 'death_year', 'image', 'bio'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Marga, $this>
     */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'parent_id');
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Person::class, 'parent_id');
    }
}
