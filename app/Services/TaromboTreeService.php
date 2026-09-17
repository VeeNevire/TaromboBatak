<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Support\IndonesiaRegions;
use App\Support\PersonShareCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaromboTreeService
{
    /**
     * Find the highest available ancestor for a focus person in a tree payload.
     *
     * The traversal only follows parent IDs that are present in the same tree,
     * so detached nodes and malformed cycles cannot become the visual root.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function highestAncestorId(array $rows, int $focusPersonId): ?string
    {
        $rowsById = collect($rows)->keyBy('id');
        $currentId = (string) $focusPersonId;

        if (! $rowsById->has($currentId)) {
            return null;
        }

        $visited = [];

        while (! isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $parentId = $rowsById->get($currentId)['parentId'] ?? null;

            if (! is_string($parentId) || ! $rowsById->has($parentId) || isset($visited[$parentId])) {
                break;
            }

            $currentId = $parentId;
        }

        return $currentId;
    }

    /**
     * Build a diagram payload from relationships belonging to one tree version.
     * Person records provide identity data; nodes provide contextual genealogy.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForFamilyTree(FamilyTree $familyTree, int|Collection|null $margaId = null): array
    {
        $nodes = app(FamilyTreeInheritanceService::class)->nodesFor($familyTree);
        $people = Person::query()
            ->whereIn('id', $nodes->pluck('person_id'))
            ->when($margaId !== null, fn (Builder $query) => $margaId instanceof Collection
                ? $query->whereIn('marga_id', $margaId)
                : $query->where('marga_id', $margaId))
            ->with([
                'marga',
                'wives.father.marga',
                'creator:id,name',
                'claimingUsers:id,name,role,current_person_id',
            ])
            ->get()
            ->keyBy('id');
        $nodes = $nodes->filter(fn (array $node) => $people->has($node['person_id']))->values();
        $includedPersonIds = $nodes->pluck('person_id')->flip();
        $children = $nodes->groupBy('father_person_id');

        return $nodes->map(function (array $node) use ($people, $includedPersonIds, $children): array {
            $person = $people->get($node['person_id']);

            return [
                'id' => (string) $person->id,
                'shareCode' => app(PersonShareCode::class)->for($person),
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'hasMarga' => $person->marga_id !== null,
                'parentId' => $node['pending_father']
                    || $node['father_person_id'] === null
                    || ! $includedPersonIds->has($node['father_person_id'])
                    ? null
                    : (string) $node['father_person_id'],
                'birthYear' => $person->birth_year,
                'birthOrder' => $node['birth_order'],
                'chain' => $node['chain'],
                'pending' => $node['pending_father'],
                'gender' => $person->gender,
                'spouse' => $person->spouse,
                'spouses' => $this->spousesFor($person),
                'image' => $person->image,
                'bio' => $person->bio,
                'createdBy' => $person->creator?->name,
                'claimedAccounts' => $this->claimedAccountsFor($person),
                'relatedStories' => $person->related_stories ?? [],
                'location' => $this->locationFor($person),
                'childrenNames' => $children->get($person->id, collect())
                    ->sortBy('birth_order')
                    ->map(function (array $child) use ($people): ?string {
                        $childPerson = $people->get($child['person_id']);

                        return $childPerson?->birth_year
                            ? $childPerson->name.' ('.$childPerson->birth_year.')'
                            : $childPerson?->name;
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * Build the rows needed by the radial tarombo diagram.
     *
     * @param  Builder<Person>  $query
     * @return array<int, array<string, mixed>>
     */
    public function rows(Builder $query, ?int $familyTreeId = null): array
    {
        return $query
            ->with([
                'marga',
                'wives.father.marga',
                'creator:id,name',
                'claimingUsers:id,name,role,current_person_id',
                'children' => fn ($query) => $query
                    ->when($familyTreeId !== null, fn ($query) => $query
                        ->whereHas('familyTrees', fn ($query) => $query->whereKey($familyTreeId))),
            ])
            ->get()
            ->map(fn (Person $person) => [
                'id' => (string) $person->id,
                'shareCode' => app(PersonShareCode::class)->for($person),
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'hasMarga' => $person->marga_id !== null,
                'parentId' => $person->father_id !== null ? (string) $person->father_id : null,
                'birthYear' => $person->birth_year,
                'birthOrder' => $person->birth_order,
                'chain' => $person->chain,
                'pending' => (bool) $person->pending_father,
                'gender' => $person->gender,
                'spouse' => $person->spouse,
                'spouses' => $this->spousesFor($person),
                'image' => $person->image,
                'bio' => $person->bio,
                'createdBy' => $person->creator?->name,
                'claimedAccounts' => $this->claimedAccountsFor($person),
                'relatedStories' => $person->related_stories ?? [],
                'location' => $this->locationFor($person),
                'childrenNames' => $person->children
                    ->sortBy('birth_year')
                    ->map(fn (Person $child) => $child->birth_year
                        ? $child->name.' ('.$child->birth_year.')'
                        : $child->name)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, fatherName: string|null, fatherMarga: string|null}>
     */
    protected function spousesFor(Person $person): array
    {
        return $person->wives
            ->map(fn (Person $wife): array => [
                'id' => (string) $wife->id,
                'name' => $wife->name,
                'fatherName' => $wife->father?->name,
                'fatherMarga' => $wife->father?->marga?->name,
            ])
            ->all();
    }

    /**
     * Build an ancestry-preserving, bounded, public-safe tree payload.
     *
     * @return array{rows: array<int, array<string, mixed>>, truncated: bool}
     */
    public function publicRows(): array
    {
        $maxDepth = max(1, (int) config('tarombo.public_max_depth'));
        $maxNodes = max(1, (int) config('tarombo.public_max_nodes'));
        $people = new Collection;
        $truncated = false;

        $roots = Person::query()
            ->public()
            ->whereNull('father_id')
            ->with('marga')
            ->orderBy('id')
            ->limit($maxNodes + 1)
            ->get();

        if ($roots->count() > $maxNodes) {
            $truncated = true;
            $roots = $roots->take($maxNodes);
        }

        $people = $people->concat($roots);
        $frontier = $roots->pluck('id');

        for ($depth = 2; $depth <= $maxDepth && $frontier->isNotEmpty(); $depth++) {
            $remaining = $maxNodes - $people->count();

            if ($remaining <= 0) {
                $truncated = Person::query()->public()->whereIn('father_id', $frontier)->exists() || $truncated;
                break;
            }

            $children = Person::query()
                ->public()
                ->whereIn('father_id', $frontier)
                ->with('marga')
                ->orderBy('father_id')
                ->orderBy('birth_order')
                ->orderBy('id')
                ->limit($remaining + 1)
                ->get();

            if ($children->count() > $remaining) {
                $truncated = true;
                $children = $children->take($remaining);
            }

            $people = $people->concat($children);
            $frontier = $children->pluck('id');
        }

        if ($frontier->isNotEmpty() && Person::query()->public()->whereIn('father_id', $frontier)->exists()) {
            $truncated = true;
        }

        return [
            'rows' => $people
                ->map(fn (Person $person) => [
                    'id' => (string) $person->id,
                    'name' => $person->name,
                    'alias' => $person->alias,
                    'marga' => $person->marga->name ?? 'Batak',
                    'hasMarga' => $person->marga_id !== null,
                    'parentId' => $person->father_id !== null ? (string) $person->father_id : null,
                    'birthOrder' => $person->birth_order,
                    'chain' => $person->chain,
                    'pending' => (bool) $person->pending_father,
                    'location' => $this->locationFor($person),
                ])
                ->values()
                ->all(),
            'truncated' => $truncated,
        ];
    }

    /**
     * Return only the centered person and a bounded descendant subtree.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForPerson(
        Person $person,
        ?int $maxDepth = null,
        ?int $maxNodes = null,
    ): array {
        $maxDepth = max(1, $maxDepth ?? (int) config('tarombo.person_max_depth'));
        $maxNodes = max(1, $maxNodes ?? (int) config('tarombo.person_max_nodes'));
        $ids = collect([$person->id]);
        $frontier = collect([$person->id]);

        for ($depth = 2; $depth <= $maxDepth && $frontier->isNotEmpty() && $ids->count() < $maxNodes; $depth++) {
            $children = Person::query()
                ->whereIn('father_id', $frontier)
                ->orderBy('birth_order')
                ->orderBy('id')
                ->limit($maxNodes - $ids->count())
                ->pluck('id');
            $ids = $ids->concat($children);
            $frontier = $children;
        }

        return $this->rows(Person::query()->whereIn('id', $ids)->orderBy('id'));
    }

    /**
     * Build one close family view: the person, their father and siblings, and
     * their direct children. Maternal children are placed below their mother
     * so a wife's family view remains meaningful in a patrilineal tree.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForCloseFamily(Person $person): array
    {
        $ids = collect([$person->id]);

        if ($person->father_id !== null) {
            $ids->push($person->father_id);
            $ids = $ids->merge(
                Person::query()->where('father_id', $person->father_id)->pluck('id'),
            );
        }

        $maternalChildIds = Person::query()
            ->where('mother_id', $person->id)
            ->pluck('id');
        $ids = $ids
            ->merge(Person::query()->where('father_id', $person->id)->pluck('id'))
            ->merge($maternalChildIds)
            ->unique()
            ->values();

        return collect($this->rows(Person::query()->whereIn('id', $ids)->orderBy('id')))
            ->map(function (array $row) use ($maternalChildIds, $person): array {
                if ($maternalChildIds->contains((int) $row['id'])) {
                    $row['parentId'] = (string) $person->id;
                }

                return $row;
            })
            ->all();
    }

    /**
     * Return the complete patrilineal path from the topmost ancestor through
     * the selected person.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForPersonWithAncestors(Person $person): array
    {
        $lineage = $person->lineage()->push($person);
        $ids = $lineage->pluck('id');
        $fatherIds = $lineage->pluck('father_id')->filter()->unique();

        // The upper tree renders the ancestor path together with each path
        // member's siblings. Include those siblings in one query so the
        // frontend can build the corresponding branches at every level.
        if ($fatherIds->isNotEmpty()) {
            $ids = $ids->merge(
                Person::query()
                    ->whereIn('father_id', $fatherIds)
                    ->pluck('id'),
            )->unique()->values();
        }

        return $this->rows(
            Person::query()
                ->whereIn('id', $ids)
                ->orderBy('id'),
        );
    }

    /**
     * Build the tarombo rows for a marga's upper (ancestor path) or lower
     * (descendants) tree, anchored on the marga's identity person. Falls back
     * to every marga member when no identity has been chosen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForMarga(Marga $marga, string $direction): array
    {
        $identity = $marga->identityPerson;

        if ($identity === null) {
            return $this->rows(
                Person::query()
                    ->where('marga_id', $marga->id)
                    ->orderBy('id'),
            );
        }

        $identityRows = collect(
            $direction === 'upper'
                ? $this->rowsForPersonWithAncestors($identity)
                : $this->rowsForPerson(
                    $identity,
                    maxDepth: (int) config('tarombo.public_max_depth'),
                    maxNodes: (int) config('tarombo.public_max_nodes'),
                ),
        );

        return $identityRows
            ->when($direction === 'lower', fn (Collection $rows) => $rows->merge(
                $this->rows(
                    Person::query()
                        ->where('marga_id', $marga->id)
                        ->orderBy('id'),
                ),
            ))
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Build the marga legend for the radial tarombo diagram.
     *
     * @return array<int, array{name: string, color: string}>
     */
    public function margas(int|array|null $margaId = null, bool $publicOnly = false): array
    {
        return Marga::query()
            ->when($margaId !== null, fn (Builder $query) => is_array($margaId)
                ? $query->whereIn('id', $margaId)
                : $query->where('id', $margaId))
            ->when($publicOnly, fn (Builder $query) => $query->whereHas('people', fn (Builder $people) => $people->where('is_public', true)))
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color ?? '#b34b1e',
            ])
            ->all();
    }

    /** @return array{province: string|null, regency: string|null, district: string|null, village: string|null} */
    private function locationFor(Person $person): array
    {
        $province = collect(IndonesiaRegions::all())->firstWhere('code', $person->province_code);
        $regency = collect($province['regencies'] ?? [])->firstWhere('code', $person->regency_code);
        $district = collect(IndonesiaRegions::districtsFor($person->regency_code ?? ''))
            ->firstWhere('code', $person->district_code);
        $village = collect(IndonesiaRegions::villagesFor($person->district_code ?? ''))
            ->firstWhere('code', $person->village_code);

        return [
            'province' => $province['name'] ?? null,
            'regency' => $regency['name'] ?? null,
            'district' => $district['name'] ?? null,
            'village' => $village['name'] ?? null,
        ];
    }

    /** @return array<int, array{id: int, name: string, role: string}> */
    private function claimedAccountsFor(Person $person): array
    {
        return $person->claimingUsers
            ->sortBy('id')
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ])
            ->values()
            ->all();
    }
}
