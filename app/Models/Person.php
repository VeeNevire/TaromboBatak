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
use Illuminate\Support\Collection as SupportCollection;

/**
 * @property int $id
 * @property string $name
 * @property string|null $gender
 * @property string|null $alias
 * @property int|null $marga_id
 * @property int|null $created_by
 * @property int|null $father_id
 * @property int|null $mother_id
 * @property int|null $birth_order
 * @property int|null $sibling_count
 * @property bool $is_leader
 * @property string|null $nomor
 * @property bool $nomor_manual
 * @property string|null $birth_year
 * @property string|null $death_year
 * @property string|null $image
 * @property string|null $bio
 * @property string|null $spouse
 * @property string|null $spouse_marga
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Marga|null $marga
 * @property-read Person|null $father
 * @property-read Person|null $mother
 * @property-read User|null $creator
 * @property-read Collection<int, Person> $children
 * @property-read Collection<int, Person> $siblings
 */
#[Fillable(['name', 'gender', 'alias', 'marga_id', 'created_by', 'father_id', 'mother_id', 'birth_order', 'sibling_count', 'is_leader', 'nomor', 'nomor_manual', 'birth_year', 'death_year', 'image', 'bio', 'spouse', 'spouse_marga'])]
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function father(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'father_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'mother_id');
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Person::class, 'father_id');
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function siblings(): HasMany
    {
        return $this->hasMany(Person::class, 'father_id')
            ->whereKeyNot($this->getKey())
            ->orderBy('birth_order');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_leader' => 'boolean',
            'nomor_manual' => 'boolean',
        ];
    }

    /**
     * Determine whether this person is not truly known yet (placeholder "N/A").
     */
    public function isNa(): bool
    {
        return is_null($this->name) || trim($this->name) === '' || mb_strtoupper(trim($this->name)) === 'N/A';
    }

    /**
     * Patrilineal ancestors from the topmost ancestor down to this person's
     * father. Ordered oldest-first so the chain reads from the marga leader
     * down to the immediate parent.
     *
     * @return SupportCollection<int, Person>
     */
    public function lineage(): SupportCollection
    {
        $chain = collect();
        $current = $this;
        $seen = [];

        while ($current->father_id !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $father = $current->father;

            if ($father === null) {
                break;
            }

            $chain->push($father);
            $current = $father;
        }

        return $chain->reverse()->values();
    }
}
