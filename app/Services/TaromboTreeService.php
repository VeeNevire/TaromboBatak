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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaromboTreeService
{
    /**
     * Marga ids a contributor manages, keyed by user id, so building many rows
     * reads them once instead of once per person.
     *
     * @var array<int, Collection<int, int>>
     */
    private array $contributorMargaIds = [];

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
     * With $personId, only that person's row is built, loading just them,
     * their father and their children: the profile dialog's detail for a tree
     * drawn from summaryRowsForFamilyTree().
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForFamilyTree(FamilyTree $familyTree, int|Collection|null $margaId = null, ?int $personId = null): array
    {
        $nodes = app(FamilyTreeInheritanceService::class)->nodesFor($familyTree);
        $personIds = $personId === null
            ? $nodes->pluck('person_id')
            : $nodes
                ->filter(fn (array $node) => (int) $node['person_id'] === $personId
                    || (int) $node['father_person_id'] === $personId)
                ->flatMap(fn (array $node) => [$node['person_id'], $node['father_person_id']])
                ->filter()
                ->unique()
                ->values();
        $people = Person::query()
            ->withExists('identityMargas')
            ->with('identityMargas:id,name,color,identity_person_id')
            ->whereIn('id', $personIds)
            ->when($margaId !== null, fn (Builder $query) => $margaId instanceof Collection
                ? $query->whereIn('marga_id', $margaId)
                : $query->where('marga_id', $margaId))
            ->with([
                'marga',
                'father:id,name,marga_id',
                'father.marga:id,name',
                'wives.marga:id,name',
                'wives.father.marga',
                'wives.mother.marga',
                'husbands.marga:id,name',
                'husbands.father.marga',
                'husbands.mother.marga',
                'mother.marga:id,name',
                'mother.father.marga',
                'mother.mother.marga',
                'creator:id,name,role',
                'lastEditor:id,name',
                'claimingUsers:id,name,role,current_person_id',
                'childrenAsMother',
            ])
            ->get()
            ->keyBy('id');
        $nodes = $nodes->filter(fn (array $node) => $people->has($node['person_id']))->values();
        $includedPersonIds = $nodes->pluck('person_id')->flip();
        $children = $nodes->groupBy('father_person_id');

        // Fathers may sit in a different marga and be filtered out of $people;
        // load them separately so the modal can still name the father.
        $fatherPersons = Person::query()
            ->whereIn('id', $nodes->pluck('father_person_id')->filter()->unique()->diff($people->keys()))
            ->with('marga:id,name')
            ->get(['id', 'name', 'marga_id'])
            ->keyBy('id');
        $shareCodes = app(PersonShareCode::class);

        // The modal lists the father's wives as the person's mothers, so load
        // them once for every father instead of per row.
        $fatherWives = Person::query()
            ->whereIn('id', $nodes->pluck('father_person_id')
                ->merge($people->pluck('father_id'))
                ->filter()
                ->unique())
            ->with(['wives.marga:id,name', 'wives.father.marga', 'wives.mother.marga'])
            ->get(['id'])
            ->mapWithKeys(fn (Person $father) => [$father->id => $father->wives]);
        $childMothers = $this->childMothersByFather($people->keys());

        return $nodes
            ->when($personId !== null, fn (Collection $nodes) => $nodes->filter(
                fn (array $node) => (int) $node['person_id'] === $personId,
            ))
            ->map(function (array $node) use ($people, $includedPersonIds, $children, $fatherPersons, $fatherWives, $childMothers, $shareCodes): array {
                $person = $people->get($node['person_id']);
                $nodeFatherId = $node['pending_father'] ? null : $node['father_person_id'];
                $hasFather = $nodeFatherId !== null || $person->father_id !== null;
                $fatherPerson = $nodeFatherId !== null
                    ? ($people->get($nodeFatherId) ?? $fatherPersons->get($nodeFatherId) ?? $person->father)
                    : $person->father;
                $childPersons = $children->get($person->id, collect())
                    ->sortBy('birth_order')
                    ->map(fn (array $child) => $people->get($child['person_id']))
                    ->filter();

                if ($childPersons->isEmpty()) {
                    $childPersons = $person->childrenAsMother->sortBy('birth_year');
                }

                $childrenSplit = $this->splitChildrenByGender($childPersons);

                return [
                    'id' => (string) $person->id,
                    'treeNodeId' => (int) $node['node_id'],
                    'shareCode' => $shareCodes->for($person),
                    'name' => $person->name,
                    'alias' => $person->alias,
                    'marga' => $person->marga->name ?? 'Batak',
                    'margaColor' => $person->marga?->color,
                    'hasMarga' => $person->marga_id !== null,
                    'isMargaIdentity' => (bool) $person->identity_margas_exists,
                    'identityMargaColor' => $person->identityMargas->first()?->color,
                    'identityMargaName' => $person->identityMargas->pluck('name')->implode(', ') ?: null,
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
                    'spouseMarga' => $person->spouse_marga,
                    'spouses' => $this->spousesFor($person, $childMothers->get($person->id)),
                    'mothers' => $this->mothersFor($fatherWives->get($fatherPerson?->id, collect()), $person->mother),
                    'image' => $person->image,
                    'bio' => $person->bio,
                    'createdBy' => $person->creator?->name,
                    'lastEditedBy' => $person->lastEditor?->name,
                    'lastEditedAt' => $person->updated_by ? $person->updated_at?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') : null,
                    'createdAt' => $person->created_at?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i WIB'),
                    'canEdit' => $this->canEdit($person, $hasFather),
                    'canAppendBranch' => Auth::user()?->can('appendBranch', $person) ?? false,
                    'canCopyCode' => $this->canCopyCode($person),
                    'fatherName' => $fatherPerson?->name,
                    'fatherMarga' => $fatherPerson?->marga?->name,
                    'claimedAccounts' => $this->claimedAccountsFor($person),
                    'relatedStories' => $person->related_stories ?? [],
                    'location' => $this->locationFor($person),
                    'childrenNames' => $childPersons
                        ->map(fn (?Person $child): ?string => $child?->birth_year
                            ? $child->name.' ('.$child->birth_year.')'
                            : $child?->name)
                        ->filter()
                        ->values()
                        ->all(),
                    'sonsNames' => $childrenSplit['sons'],
                    'daughtersNames' => $childrenSplit['daughters'],
                ];
            })->values()->all();
    }

    /**
     * summaryRowsForFamilyTree(), cached until the tree's data changes.
     *
     * The cached rows carry a fingerprint of the tables they are built from,
     * so any edit (including bulk queries that skip model events) shows on
     * the next visit. One entry per tree is overwritten, never accumulated.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cachedSummaryRowsForFamilyTree(FamilyTree $familyTree): array
    {
        // canEdit is the only viewer-dependent field: true for staff, and the
        // same creator/father rule for every other signed-in account.
        $scope = Auth::user()?->isStaff() ? 'staff' : (Auth::check() ? 'member' : 'guest');
        $key = 'tarombo:family-tree-summary:'.$familyTree->id.':'.$scope;
        $fingerprint = $this->summaryFingerprint($familyTree);
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint) {
            return $cached['rows'];
        }

        $rows = $this->summaryRowsForFamilyTree($familyTree);
        Cache::put($key, ['fingerprint' => $fingerprint, 'rows' => $rows], now()->addDay());

        return $rows;
    }

    /**
     * Row counts and latest updates of everything a summary row reads: the
     * tree's own nodes and those of the trees it is based on, people, marga
     * and the spouse pivot (which has no timestamps, so its ids are summed).
     */
    private function summaryFingerprint(FamilyTree $familyTree): string
    {
        $treeIds = [];

        for ($treeId = $familyTree->id; $treeId !== null && ! in_array($treeId, $treeIds, true);) {
            $treeIds[] = $treeId;
            $treeId = FamilyTree::query()->whereKey($treeId)->value('based_on_id');
        }

        $stats = 'count(*) as total, max(id) as last_id, max(updated_at) as last_update';

        return md5((string) json_encode([
            $treeIds,
            DB::table('family_tree_nodes')->whereIn('family_tree_id', $treeIds)->selectRaw($stats)->first(),
            DB::table('people')->selectRaw($stats)->first(),
            DB::table('margas')->selectRaw($stats)->first(),
            DB::table('person_wife')->selectRaw('count(*) as total, sum(husband_id) as husbands, sum(wife_id) as wives')->first(),
        ]));
    }

    /**
     * The light rows an account tree draws: identity, placement and the few
     * flags its nodes show. A tree can hold thousands of people, so the
     * profile dialog loads everything else for one person at a time through
     * rowsForFamilyTree($familyTree, personId: ...).
     *
     * @return array<int, array<string, mixed>>
     */
    public function summaryRowsForFamilyTree(FamilyTree $familyTree): array
    {
        $nodes = app(FamilyTreeInheritanceService::class)->nodesFor($familyTree);
        $people = Person::query()
            ->withExists(['identityMargas', 'claimingUsers', 'childrenAsMother'])
            ->with([
                'identityMargas:id,name,color,identity_person_id',
                'marga:id,name,color',
                'wives.marga:id,name',
                'wives.father.marga:id,name',
                'husbands.marga:id,name',
                'husbands.father.marga:id,name',
                'creator:id,role',
            ])
            ->whereIn('id', $nodes->pluck('person_id'))
            ->get()
            ->keyBy('id');
        $nodes = $nodes->filter(fn (array $node) => $people->has($node['person_id']))->values();
        $includedPersonIds = $nodes->pluck('person_id')->flip();
        $childCounts = $nodes->countBy('father_person_id');
        $childMothers = $this->childMothersByFather($people->keys());

        return $nodes->map(function (array $node) use ($people, $includedPersonIds, $childCounts, $childMothers): array {
            $person = $people->get($node['person_id']);
            $hasFather = ($node['pending_father'] ? null : $node['father_person_id']) !== null
                || $person->father_id !== null;

            return [
                'id' => (string) $person->id,
                'summary' => true,
                'treeNodeId' => (int) $node['node_id'],
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'margaColor' => $person->marga?->color,
                'hasMarga' => $person->marga_id !== null,
                'isMargaIdentity' => (bool) $person->identity_margas_exists,
                'identityMargaColor' => $person->identityMargas->first()?->color,
                'identityMargaName' => $person->identityMargas->pluck('name')->implode(', ') ?: null,
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
                'spouseMarga' => $person->spouse_marga,
                'spouses' => $this->spousePeople($person, $childMothers->get($person->id))
                    ->map(fn (Person $spouse): array => [
                        'id' => (string) $spouse->id,
                        'name' => $spouse->name,
                        'marga' => $spouse->marga?->name,
                        'fatherMarga' => $spouse->father?->marga?->name,
                    ])
                    ->values()
                    ->all(),
                'image' => $person->image,
                'canEdit' => $this->canEdit($person, $hasFather),
                'isClaimed' => (bool) $person->claiming_users_exists,
                'hasChildren' => $childCounts->get($person->id, 0) > 0
                    || (bool) $person->children_as_mother_exists,
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
        $shareCodes = app(PersonShareCode::class);

        $people = $query
            ->withExists('identityMargas')
            ->with('identityMargas:id,name,color,identity_person_id')
            ->with([
                'marga',
                'father:id,name,marga_id',
                'father.marga:id,name',
                'wives.marga:id,name',
                'wives.father.marga',
                'wives.mother.marga',
                'husbands.marga:id,name',
                'husbands.father.marga',
                'husbands.mother.marga',
                'father.wives.marga:id,name',
                'father.wives.father.marga',
                'father.wives.mother.marga',
                'mother.marga:id,name',
                'mother.father.marga',
                'mother.mother.marga',
                'creator:id,name,role',
                'lastEditor:id,name',
                'claimingUsers:id,name,role,current_person_id',
                'children' => fn ($query) => $query
                    ->when($familyTreeId !== null, fn ($query) => $query
                        ->whereHas('familyTrees', fn ($query) => $query->whereKey($familyTreeId))),
                'childrenAsMother' => fn ($query) => $query
                    ->when($familyTreeId !== null, fn ($query) => $query
                        ->whereHas('familyTrees', fn ($query) => $query->whereKey($familyTreeId))),
            ])
            ->get();
        $childMothers = $this->childMothersByFather(collect($people->modelKeys()));

        return $people
            ->map(function (Person $person) use ($shareCodes, $childMothers): array {
                $hasFather = $person->father_id !== null;
                $children = $person->children->isNotEmpty() ? $person->children : $person->childrenAsMother;
                $sortedChildren = $children->sortBy('birth_year');
                $childrenSplit = $this->splitChildrenByGender($sortedChildren);

                return [
                    'id' => (string) $person->id,
                    'shareCode' => $shareCodes->for($person),
                    'name' => $person->name,
                    'alias' => $person->alias,
                    'marga' => $person->marga->name ?? 'Batak',
                    'margaColor' => $person->marga?->color,
                    'hasMarga' => $person->marga_id !== null,
                    'isMargaIdentity' => (bool) $person->identity_margas_exists,
                    'identityMargaColor' => $person->identityMargas->first()?->color,
                    'identityMargaName' => $person->identityMargas->pluck('name')->implode(', ') ?: null,
                    'parentId' => $hasFather ? (string) $person->father_id : null,
                    'motherId' => $person->mother_id === null ? null : (string) $person->mother_id,
                    'birthYear' => $person->birth_year,
                    'birthOrder' => $person->birth_order,
                    'chain' => $person->chain,
                    'pending' => (bool) $person->pending_father,
                    'gender' => $person->gender,
                    'spouse' => $person->spouse,
                    'spouseMarga' => $person->spouse_marga,
                    'spouses' => $this->spousesFor($person, $childMothers->get($person->id)),
                    'mothers' => $this->mothersFor($person->father?->wives ?? collect(), $person->mother),
                    'image' => $person->image,
                    'bio' => $person->bio,
                    'createdBy' => $person->creator?->name,
                    'lastEditedBy' => $person->lastEditor?->name,
                    'lastEditedAt' => $person->updated_by ? $person->updated_at?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') : null,
                    'createdAt' => $person->created_at?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i WIB'),
                    'canEdit' => $this->canEdit($person, $hasFather),
                    'canAppendBranch' => Auth::user()?->can('appendBranch', $person) ?? false,
                    'canCopyCode' => $this->canCopyCode($person),
                    'fatherName' => $person->father?->name,
                    'fatherMarga' => $person->father?->marga?->name,
                    'claimedAccounts' => $this->claimedAccountsFor($person),
                    'relatedStories' => $person->related_stories ?? [],
                    'location' => $this->locationFor($person),
                    'childrenNames' => $sortedChildren
                        ->map(fn (Person $child) => $child->birth_year
                            ? $child->name.' ('.$child->birth_year.')'
                            : $child->name)
                        ->values()
                        ->all(),
                    'sonsNames' => $childrenSplit['sons'],
                    'daughtersNames' => $childrenSplit['daughters'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, marga: string|null, fatherName: string|null, fatherMarga: string|null, motherName: string|null, motherMarga: string|null, birthOrder: int|null}>
     */
    protected function spousesFor(Person $person, ?Collection $childMothers = null): array
    {
        return $this->spousePeople($person, $childMothers)
            ->map(fn (Person $spouse): array => $this->spouseRow($spouse))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Person>|null  $childMothers
     * @return Collection<int, Person>
     */
    private function spousePeople(Person $person, ?Collection $childMothers = null): Collection
    {
        // A child's recorded mother is listed as the father's wife in the
        // child's modal, so list her here too even without a pivot row.
        $wives = $person->wives->concat($childMothers ?? [])->unique('id');

        return $wives->isNotEmpty() ? $wives : $person->husbands;
    }

    /**
     * Mothers recorded on the children of each given father, keyed by father
     * id, loaded in two queries for the whole payload.
     *
     * @param  Collection<int, int>  $fatherIds
     * @return Collection<int, Collection<int, Person>>
     */
    private function childMothersByFather(Collection $fatherIds): Collection
    {
        if ($fatherIds->isEmpty()) {
            return collect();
        }

        $pairs = Person::query()
            ->whereIn('father_id', $fatherIds)
            ->whereNotNull('mother_id')
            ->orderBy('birth_order')
            ->orderBy('id')
            ->get(['father_id', 'mother_id']);

        if ($pairs->isEmpty()) {
            return collect();
        }

        $mothers = Person::query()
            ->whereIn('id', $pairs->pluck('mother_id')->unique())
            ->with(['marga:id,name', 'father.marga', 'mother.marga'])
            ->get()
            ->keyBy('id');

        return $pairs
            ->groupBy('father_id')
            ->map(fn (Collection $rows) => $rows
                ->pluck('mother_id')
                ->unique()
                ->map(fn ($motherId) => $mothers->get($motherId))
                ->filter()
                ->values());
    }

    /**
     * The father's wives plus the person's own recorded mother, matching the
     * "Ibu" entries of the family form.
     *
     * @param  Collection<int, Person>  $fatherWives
     * @return array<int, array{id: string, name: string, marga: string|null, fatherName: string|null, fatherMarga: string|null, motherName: string|null, motherMarga: string|null, birthOrder: int|null}>
     */
    protected function mothersFor(Collection $fatherWives, ?Person $mother): array
    {
        return collect($fatherWives)
            ->when($mother !== null, fn (Collection $mothers) => $mothers->push($mother))
            ->unique('id')
            ->map(fn (Person $wife): array => $this->spouseRow($wife))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, name: string, marga: string|null, fatherName: string|null, fatherMarga: string|null, motherName: string|null, motherMarga: string|null, birthOrder: int|null}
     */
    private function spouseRow(Person $spouse): array
    {
        return [
            'id' => (string) $spouse->id,
            'name' => $spouse->name,
            'birthOrder' => $spouse->birth_order,
            'marga' => $spouse->marga?->name,
            'fatherName' => $spouse->father?->name,
            'fatherMarga' => $spouse->father?->marga?->name,
            'motherName' => $spouse->mother?->name,
            'motherMarga' => $spouse->mother?->marga?->name,
        ];
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
            ->withExists('identityMargas')
            ->with('identityMargas:id,name,color,identity_person_id')
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
                ->withExists('identityMargas')
                ->with('identityMargas:id,name,color,identity_person_id')
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
                    'margaColor' => $person->marga?->color,
                    'hasMarga' => $person->marga_id !== null,
                    'isMargaIdentity' => (bool) $person->identity_margas_exists,
                    'identityMargaColor' => $person->identityMargas->first()?->color,
                    'identityMargaName' => $person->identityMargas->pluck('name')->implode(', ') ?: null,
                    'parentId' => $person->father_id !== null ? (string) $person->father_id : null,
                    'birthOrder' => $person->birth_order,
                    'chain' => $person->chain,
                    'pending' => (bool) $person->pending_father,
                    'canEdit' => false,
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
        bool $publicOnly = false,
    ): array {
        $maxDepth = max(1, $maxDepth ?? (int) config('tarombo.person_max_depth'));
        $maxNodes = max(1, $maxNodes ?? (int) config('tarombo.person_max_nodes'));
        $ids = collect([$person->id]);
        $frontier = collect([$person->id]);

        for ($depth = 2; $depth <= $maxDepth && $frontier->isNotEmpty() && $ids->count() < $maxNodes; $depth++) {
            $children = Person::query()
                ->when($publicOnly, fn (Builder $query) => $query->public())
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
    public function rowsForMarga(Marga $marga, string $direction, ?int $maxDepth = null, ?int $maxNodes = null, ?int $descendantGenerations = null): array
    {
        $identity = $marga->identityPerson;

        if ($identity === null) {
            if ($descendantGenerations !== null) {
                return [];
            }

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
                    maxDepth: $descendantGenerations !== null ? $descendantGenerations + 1 : ($maxDepth ?? (int) config('tarombo.public_max_depth')),
                    maxNodes: $maxNodes ?? (int) config('tarombo.public_max_nodes'),
                ),
        );

        return $identityRows
            ->when($direction === 'lower' && $descendantGenerations === null, fn (Collection $rows) => $rows->merge(
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
        if ($person->province_code === null && $person->regency_code === null
            && $person->district_code === null && $person->village_code === null) {
            return ['province' => null, 'regency' => null, 'district' => null, 'village' => null];
        }

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

    /**
     * Whether the signed-in account may edit this person. A person that has a
     * father and was not created by a regular "user" account is read-only
     * (staff exempt).
     */
    private function canEdit(Person $person, bool $hasFather): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        if ($hasFather && $person->creator?->role !== 'user') {
            return false;
        }

        return true;
    }

    /**
     * Whether the signed-in account may copy this person's share code. Only
     * contributors who manage this person's marga may do so (staff exempt).
     */
    private function canCopyCode(Person $person): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        // Same rule as User::isContributorOf(), with the managed margas cached.
        return $person->marga_id !== null
            && $user->isContributor()
            && ($this->contributorMargaIds[$user->id] ??= $user->accessibleMargaIds())->contains($person->marga_id);
    }

    /**
     * Split a collection of children into "son" and "daughter" name lists.
     * A child without a recorded gender is treated as a son.
     *
     * @param  Collection<int, Person>  $children
     * @return array{sons: array<int, string>, daughters: array<int, string>}
     */
    private function splitChildrenByGender(Collection $children): array
    {
        $format = fn (Person $child): string => $child->birth_year
            ? $child->name.' ('.$child->birth_year.')'
            : $child->name;

        $isDaughter = fn (Person $child): bool => strtoupper((string) $child->gender) === 'P';

        return [
            'sons' => $children->reject($isDaughter)->map($format)->values()->all(),
            'daughters' => $children->filter($isDaughter)->map($format)->values()->all(),
        ];
    }
}
