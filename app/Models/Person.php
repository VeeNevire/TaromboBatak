<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property bool $pending_father
 * @property bool $is_public
 * @property int|null $mother_id
 * @property int|null $birth_order
 * @property int|null $sibling_count
 * @property string|null $chain
 * @property string|null $birth_year
 * @property string|null $death_year
 * @property string|null $image
 * @property string|null $bio
 * @property array<int, array{title: string, url: string}>|null $related_stories
 * @property string|null $spouse
 * @property string|null $spouse_marga
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Marga|null $marga
 * @property-read Person|null $father
 * @property-read Person|null $mother
 * @property-read User|null $creator
 * @property-read Collection<int, FamilyTree> $familyTrees
 * @property-read Collection<int, FamilyTreeNode> $familyTreeNodes
 * @property-read Collection<int, Person> $children
 * @property-read Collection<int, Person> $siblings
 * @property-read Collection<int, Person> $wives
 */
#[Fillable(['name', 'gender', 'alias', 'marga_id', 'created_by', 'father_id', 'mother_id', 'birth_order', 'sibling_count', 'chain', 'birth_year', 'death_year', 'image', 'bio', 'related_stories', 'spouse', 'spouse_marga', 'pending_father', 'is_public'])]
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
     * @return BelongsToMany<FamilyTree, $this>
     */
    public function familyTrees(): BelongsToMany
    {
        return $this->belongsToMany(FamilyTree::class);
    }

    /**
     * @return HasMany<FamilyTreeNode, $this>
     */
    public function familyTreeNodes(): HasMany
    {
        return $this->hasMany(FamilyTreeNode::class);
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
     * @return BelongsToMany<Person, $this>
     */
    public function wives(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_wife', 'husband_id', 'wife_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function siblings(): HasMany
    {
        return $this->hasMany(Person::class, 'father_id')
            ->where('pending_father', false)
            ->whereKeyNot($this->getKey())
            ->orderBy('birth_order');
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pending_father' => 'boolean',
            'is_public' => 'boolean',
            'related_stories' => 'array',
        ];
    }

    /**
     * Determine whether this person is not truly known yet (placeholder "N/A").
     */
    public function isNa(): bool
    {
        return trim($this->name) === '' || mb_strtoupper(trim($this->name)) === 'N/A';
    }

    /**
     * Number of segments in the chain (1 = root, 2 = child, ...). Null when
     * the person has no chain (e.g. spouses / mothers).
     */
    public function generation(): ?int
    {
        if ($this->chain === null) {
            return null;
        }

        return substr_count($this->chain, '-') + 1;
    }

    /**
     * All descendants found by chain prefix matching ("1-1-" for chain "1-1").
     *
     * @return SupportCollection<int, Person>
     */
    public function descendantsByChain(): SupportCollection
    {
        if ($this->chain === null) {
            return new SupportCollection;
        }

        return self::query()
            ->where('chain', 'like', $this->chain.'-%')
            ->orderBy('chain')
            ->get();
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

    /**
     * People who cannot be selected as this person's father: the person
     * themself, their siblings, and every patrilineal descendant.
     *
     * @return array<int, int>
     */
    public function ineligibleFatherIds(): array
    {
        $ids = [$this->id];

        if ($this->father_id !== null) {
            $ids = array_merge(
                $ids,
                self::query()
                    ->where('father_id', $this->father_id)
                    ->whereKeyNot($this->id)
                    ->pluck('id')
                    ->all(),
            );
        }

        $queue = [$this->id];
        $seen = [$this->id => true];

        while ($queue !== []) {
            $children = self::query()
                ->whereIn('father_id', $queue)
                ->pluck('id')
                ->reject(fn (int $id) => isset($seen[$id]))
                ->values()
                ->all();

            foreach ($children as $childId) {
                $seen[$childId] = true;
            }

            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        return array_values(array_unique($ids));
    }
}
