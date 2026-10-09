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
 * @property string|null $province_code
 * @property string|null $regency_code
 * @property string|null $district_code
 * @property string|null $village_code
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
#[Fillable(['name', 'gender', 'alias', 'marga_id', 'province_code', 'regency_code', 'district_code', 'village_code', 'created_by', 'father_id', 'mother_id', 'birth_order', 'sibling_count', 'chain', 'birth_year', 'death_year', 'image', 'bio', 'related_stories', 'spouse', 'spouse_marga', 'pending_father', 'is_public'])]
class Person extends Model
{
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

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
     * @return HasMany<Marga, $this>
     */
    public function identityMargas(): HasMany
    {
        return $this->hasMany(Marga::class, 'identity_person_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accounts that have verified this person as their identity.
     *
     * @return HasMany<User, $this>
     */
    public function claimingUsers(): HasMany
    {
        return $this->hasMany(User::class, 'current_person_id');
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
     * @return HasMany<Person, $this>
     */
    public function childrenAsMother(): HasMany
    {
        return $this->hasMany(Person::class, 'mother_id');
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
     * @return BelongsToMany<Person, $this>
     */
    public function husbands(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_wife', 'wife_id', 'husband_id')
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
        if ($this->father_id === null) {
            return collect();
        }

        // UNION deduplicates ancestor edges, so malformed cycles terminate.
        // Read the ancestor graph in one query instead of lazy-loading each father.
        $table = $this->getConnection()->getQueryGrammar()->wrapTable($this->getTable());
        $ancestors = $this->newQuery()->fromQuery("WITH RECURSIVE ancestor_edges AS (
            SELECT id, father_id FROM {$table} WHERE id = ?
            UNION
            SELECT parent.id, parent.father_id FROM {$table} AS parent
            INNER JOIN ancestor_edges ON parent.id = ancestor_edges.father_id
        ) SELECT person.* FROM {$table} AS person
          INNER JOIN ancestor_edges ON person.id = ancestor_edges.id", [$this->father_id])->keyBy('id');
        $chain = collect();
        $current = $this;
        $seen = [];

        while ($current->father_id !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $father = $ancestors->get($current->father_id);

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

        $table = $this->getConnection()->getQueryGrammar()->wrapTable($this->getTable());
        $descendants = $this->getConnection()->select("WITH RECURSIVE descendant_ids AS (
            SELECT id FROM {$table} WHERE id = ?
            UNION
            SELECT child.id FROM {$table} AS child
            INNER JOIN descendant_ids ON child.father_id = descendant_ids.id
        ) SELECT id FROM descendant_ids", [$this->id]);
        $ids = array_merge($ids, array_map(fn (object $row): int => (int) $row->id, $descendants));

        return array_values(array_unique($ids));
    }
}
