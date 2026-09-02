<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdateFamilyTreeNameRequest;
use App\Http\Requests\UpdateFamilyTreeStructureRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeDeletionRequest;
use App\Models\FamilyTreeNode;
use App\Models\FamilyTreeShare;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Notifications\FatherMatchSubmitted;
use App\Services\ChainNumberingService;
use App\Services\FamilyEntryService;
use App\Services\FamilyTreeStructureService;
use App\Services\FamilyTreeVersionService;
use App\Services\TaromboTreeService;
use App\Support\IndonesiaRegions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    /**
     * List people with search and marga filter.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isGuest = $user === null;
        $isStaff = $user?->isStaff() ?? false;

        $people = Person::query()
            ->with([
                'marga',
                'father' => fn ($query) => $query->when($isGuest, fn ($father) => $father->public()),
            ])
            ->withCount('children')
            ->when($isGuest, fn ($query) => $query->public())
            ->when(
                ! $isGuest && ! $isStaff && ! $user->isContributor(),
                fn ($query) => $query
                    ->where('marga_id', $user->marga_id)
                    ->whereHas(
                        'familyTrees',
                        fn ($familyTrees) => $familyTrees->where('family_trees.user_id', $user->id),
                    ),
            )
            ->when(
                ! $isGuest && ! $isStaff && $user->isContributor(),
                fn ($query) => $query->whereIn('marga_id', $user->accessibleMargaIds()),
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($personQuery) use ($search) {
                    $personQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('alias', 'like', "%{$search}%")
                        ->orWhereHas('marga', fn ($margaQuery) => $margaQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('marga_id'), fn ($query) => $query->where('marga_id', $request->integer('marga_id')))
            ->orderBy('name');

        $mapPerson = fn (Person $person) => [
            'id' => $person->id,
            'name' => $person->name,
            'alias' => $person->alias,
            'marga' => $person->marga?->name,
            'marga_id' => $person->marga_id,
            'marga_color' => $person->marga?->color,
            'parent' => $person->father?->name,
            'children_count' => $person->children_count,
            'birth_year' => $person->birth_year,
            'chain' => $person->chain,
            'pending' => (bool) $person->pending_father,
            'created_at' => $person->created_at?->format('d M Y'),
            'editable' => $user?->can('update', $person) ?? false,
        ];

        if ($isGuest) {
            $paginatedPeople = $people
                ->paginate(12)
                ->withQueryString()
                ->through($mapPerson);
        } else {
            $people = $people->get()->map($mapPerson);
        }

        $margas = $isGuest
            ? collect()
            : Marga::query()
                ->when(! $isStaff, fn ($query) => $query->whereIn('id', $user->accessibleMargaIds()))
                ->orderBy('name')
                ->get()
                ->map(fn (Marga $marga) => [
                    'id' => $marga->id,
                    'name' => $marga->name,
                ]);

        return Inertia::render('people/index', [
            'people' => $isGuest ? $paginatedPeople : [
                'data' => $people->values()->all(),
                'links' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => $people->count(),
                'from' => $people->isNotEmpty() ? 1 : null,
                'to' => $people->isNotEmpty() ? $people->count() : null,
                'next_page_url' => null,
                'prev_page_url' => null,
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'marga_id' => $request->input('marga_id'),
            ],
            'margas' => $margas,
            'canManage' => $isStaff,
            'hasMarga' => ! $isGuest && ($isStaff || $user->accessibleMargaIds()->isNotEmpty()),
            'isGuest' => $isGuest,
        ]);
    }

    public function publicPreview(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('people.create');
        }

        $margas = Marga::query()
            ->whereNotNull('identity_person_id')
            ->whereHas('people', fn ($query) => $query->public())
            ->with('identityPerson:id,name')
            ->withCount(['people as people_count' => fn ($query) => $query->public()])
            ->orderBy('name')
            ->get(['id', 'name', 'identity_person_id'])
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
                'identity_person_id' => $marga->identity_person_id,
                'identity_person_name' => $marga->identityPerson?->name,
                'people_count' => $marga->people_count,
            ])
            ->values();

        return Inertia::render('people/public-preview', [
            'margas' => $margas,
        ]);
    }

    /**
     * Show the create family entry form.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $isStaff = $user->isStaff();

        Gate::authorize('create', Person::class);

        return Inertia::render('people/form', [
            'person' => null,
            'regions' => IndonesiaRegions::all(),
            'margas' => $isStaff ? $this->margaOptions() : $this->margaOptionsForUser($user),
            'nameSuggestions' => $this->nameSuggestions(
                $isStaff ? null : ($user->isContributor() ? $user->accessibleMargaIds() : $user->marga_id),
            ),
            'fatherSuggestions' => $this->fatherSuggestions(
                margaId: $isStaff ? null : ($user->isContributor() ? $user->accessibleMargaIds() : $user->marga_id),
            ),
            'lockedMarga' => $isStaff || $user->isContributor() ? null : $this->lockedMarga($user),
            'lineage' => $this->createLineage($user, $isStaff),
            'familyTrees' => $this->familyTrees($user),
            'approvedMargaTrees' => $this->approvedMargaTrees($user),
            'margaAccessMargaId' => $user->isStaff() || $user->isContributor() ? null : $user->marga_id,
            'margaAccessStatus' => $this->margaAccessStatus($user, $user->marga_id),
            ...$this->familyTreeSharingPayload($user),
            'canPublish' => $isStaff,
        ]);
    }

    /**
     * Roots of the user's marga (or all margas for staff) shown read-only on
     * the create form as the marga's lineage context.
     *
     * @return array<int, array{id: int, name: string, marga_id: int|null, marga: string|null, chain: string|null, children: array<int, array{id: int, name: string, gender: string|null, marga: string|null, chain: string|null, birth_order: int|null}>}>
     */
    protected function createLineage(User $user, bool $isStaff): array
    {
        $canBrowseMarga = $isStaff || $user->isContributor();
        $margaIds = $isStaff ? null : $user->accessibleMargaIds();

        return Person::query()
            ->with([
                'marga',
                'children' => fn ($query) => $query
                    ->when($margaIds !== null, fn ($childQuery) => $childQuery->whereIn('marga_id', $margaIds))
                    ->when(! $canBrowseMarga, fn ($childQuery) => $this->scopePeopleVisibleToUser($childQuery, $user))
                    ->where(fn ($childQuery) => $childQuery
                        ->where('gender', 'L')
                        ->orWhereNull('gender'))
                    ->orderBy('birth_order'),
            ])
            ->where(fn ($query) => $query
                ->where('gender', 'L')
                ->orWhereNull('gender'))
            ->when($isStaff, fn ($query) => $query->whereNull('father_id'))
            ->when($margaIds !== null, fn ($query) => $query
                ->whereIn('marga_id', $margaIds)
                ->whereDoesntHave(
                    'father',
                    fn ($father) => $father->whereIn('marga_id', $margaIds),
                ))
            ->when(! $canBrowseMarga, fn ($query) => $this->scopePeopleVisibleToUser($query, $user))
            ->orderByRaw('chain IS NULL')
            ->orderByRaw('CAST(chain AS UNSIGNED)')
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'marga_id' => $person->marga_id,
                'marga' => $person->marga?->name,
                'chain' => $person->chain,
                'children' => $person->children
                    ->map(fn (Person $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'gender' => $child->gender,
                        'marga' => $child->marga?->name,
                        'chain' => $child->chain,
                        'birth_order' => $child->birth_order,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Store a whole family entry (father, mother, and sibling rows).
     */
    public function store(StorePersonRequest $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', Person::class);

        $validated = $request->validated();
        $validated = $this->normalizeRelatedStories($validated);
        $validated = $this->preparePersonImage($request, $validated);
        $validated['children'] = $request->input('children', []);
        $validated['ownChildren'] = $request->input('ownChildren', []);
        $validated['removed_child_ids'] = $request->input('removed_child_ids', []);
        $validated['removed_own_child_ids'] = $request->input('removed_own_child_ids', []);

        if ($user->isStaff()) {
            app(FamilyEntryService::class)->save($validated, createdBy: $user->id);
        } else {
            $forcedMargaId = $user->isContributor()
                ? (int) ($validated['marga_id'] ?? 0)
                : (int) ($user->marga_id ?? 0);
            abort_unless(
                $forcedMargaId > 0 && $user->accessibleMargaIds()->contains($forcedMargaId),
                403,
                'Marga ini tidak termasuk dalam marga yang dapat Anda kelola.',
            );

            $result = DB::transaction(function () use ($validated, $user, $forcedMargaId) {
                $result = app(FamilyEntryService::class)->save(
                    $validated,
                    forcedMargaId: $forcedMargaId,
                    createdBy: $user->id,
                    deferExistingFatherMatch: true,
                );
                $this->createFatherMatchRequest($result, $user);

                return $result;
            });
        }

        $message = isset($result) && $result['matchedFather'] !== null
            ? 'Keluarga disimpan. Pencocokan Ayah menunggu persetujuan kontributor.'
            : __('Keluarga berhasil ditambahkan.');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('people.index');
    }

    /**
     * Show the family entry (jejak keluarga) reached from the Info button.
     */
    public function show(Request $request, Person $person): Response
    {
        $user = $request->user();
        Gate::authorize('view', $person);
        $versionTrees = $this->familyTrees($user, $person);
        $selectedVersionName = data_get(
            collect($versionTrees)->firstWhere('id', $request->integer('version_tree')),
            'name',
        );

        return Inertia::render('people/show', [
            'person' => $this->familyPayloadVisibleToUser(
                $this->familyPayload(
                    $person,
                    $user->isStaff() ? null : ($user->isContributor() ? $person->marga_id : $user->marga_id),
                ),
                $user,
            ),
            'regions' => IndonesiaRegions::all(),
            'margas' => $user->isStaff() ? $this->margaOptions() : $this->margaOptionsForUser($user),
            'nameSuggestions' => $this->nameSuggestions($user->isContributor() ? $user->accessibleMargaIds() : null),
            'fatherSuggestions' => $this->fatherSuggestions($person, $user->isContributor() ? $person->marga_id : null),
            'familyTrees' => $this->familyTrees($user),
            'approvedMargaTrees' => $this->approvedMargaTrees($user),
            'margaAccessMargaId' => $user->isStaff() || $user->isContributor() ? null : $user->marga_id,
            'margaAccessStatus' => $this->margaAccessStatus($user, $user->marga_id),
            'versionTrees' => $versionTrees,
            'selectedVersionName' => $selectedVersionName,
            ...$this->familyTreeSharingPayload($user),
            'canPublish' => $user->isStaff(),
            'readOnly' => ! $user->isStaff(),
        ]);
    }

    /**
     * Show the edit family entry form.
     */
    public function edit(Request $request, Person $person): Response
    {
        $user = $request->user();
        $isStaff = $user->isStaff();

        Gate::authorize('update', $person);
        $versionTrees = $this->familyTrees($user, $person);
        $selectedVersionName = data_get(
            collect($versionTrees)->firstWhere('id', $request->integer('version_tree')),
            'name',
        );
        abort_if(
            $request->filled('version_tree') && $selectedVersionName === null,
            404,
            'Versi silsilah tidak tersedia untuk orang ini.',
        );
        $selectedVersionId = $selectedVersionName !== null ? $request->integer('version_tree') : null;

        $personMargaScope = $isStaff ? null : ($user->isContributor() ? $person->marga_id : $user->marga_id);
        $familyPayload = $selectedVersionId !== null
                ? $this->familyPayloadForVersion($person, $selectedVersionId, $personMargaScope)
                : $this->familyPayload($person, $personMargaScope);

        return Inertia::render('people/form', [
            'person' => $this->familyPayloadVisibleToUser($familyPayload, $user),
            'regions' => IndonesiaRegions::all(),
            'margas' => $isStaff ? $this->margaOptions() : $this->margaOptionsForUser($user),
            'nameSuggestions' => $this->nameSuggestions(
                $isStaff ? null : ($user->isContributor() ? $user->accessibleMargaIds() : $user->marga_id),
            ),
            'fatherSuggestions' => $this->fatherSuggestions(
                $person,
                $isStaff ? null : ($user->isContributor() ? $person->marga_id : $user->marga_id),
            ),
            'lockedMarga' => $isStaff || $user->isContributor() ? null : $this->lockedMarga($user),
            'familyTrees' => $this->familyTrees($user),
            'approvedMargaTrees' => $this->approvedMargaTrees($user),
            'margaAccessMargaId' => $user->isStaff() || $user->isContributor() ? null : $user->marga_id,
            'margaAccessStatus' => $this->margaAccessStatus($user, $user->marga_id),
            'versionTrees' => $versionTrees,
            'selectedVersionName' => $selectedVersionName,
            'selectedVersionId' => $selectedVersionId,
            ...$this->familyTreeSharingPayload($user),
            'canPublish' => $isStaff,
        ]);
    }

    /**
     * JSON preview of the close family ("silsilah keluarga") for a person:
     * the ayah & ibu branches (with their parents and siblings) and the
     * children row where the focused person is highlighted by birth order.
     */
    public function preview(Person $person): JsonResponse
    {
        Gate::authorize('view', $person);

        return response()->json($this->familyPreviewPayload($person));
    }

    /**
     * Show the silsilah of a person as an interactive descendant tree that
     * can be re-rooted by clicking any person in the tree.
     */
    public function silsilah(Request $request, Person $person): Response|RedirectResponse
    {
        Gate::authorize('view', $person);

        $familyTrees = FamilyTree::query()
            ->whereHas('nodes', fn ($query) => $query->where('person_id', $person->id))
            ->when(! $request->user()->isStaff(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['user:id,name', 'rootPerson:id,name,marga_id', 'nodes.person:id,name', 'shares.recipient:id,name,email', 'contributionRequests:id,family_tree_id,status'])
            ->latest('updated_at')
            ->get();

        if ($familyTrees->count() === 1) {
            return to_route('family-trees.show', $familyTrees->first());
        }

        if ($familyTrees->isNotEmpty()) {
            return Inertia::render('people/tree-selector', [
                'person' => [
                    'id' => $person->id,
                    'name' => $person->name,
                ],
                'familyTrees' => $familyTrees->map(fn (FamilyTree $tree) => [
                    'id' => $tree->id,
                    'name' => $tree->name ?? $this->familyTreeRootName($tree) ?? 'Silsilah',
                    'rootName' => $this->familyTreeRootName($tree),
                    'updatedAt' => $tree->updated_at->toISOString(),
                ])->all(),
            ]);
        }

        $service = app(TaromboTreeService::class);

        return Inertia::render('people/silsilah', [
            'people' => $service->rowsForPerson($person),
            'centerPersonId' => (string) $person->id,
            'person' => [
                'id' => (string) $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'birthOrder' => $person->birth_order,
            ],
        ]);
    }

    /**
     * Open one account-owned family tree from its history card.
     */
    public function showFamilyTree(Request $request, FamilyTree $familyTree): Response
    {
        $user = $request->user();
        $canEdit = $user->isStaff() || $familyTree->user_id === $user->id;

        abort_unless(
            $user->can('view', $familyTree) || $this->approvedFamilyTreeMatchesUserMarga($familyTree, $user),
            403,
            'Anda tidak memiliki akses ke silsilah ini.',
        );

        $service = app(TaromboTreeService::class);
        $visibleMargaIds = $user->isStaff()
            ? null
            : ($user->isContributor()
                ? $user->accessibleMargaIds()
                : $user->accessibleMargaIds()->merge($user->approvedMargaAccessIds())->unique()->values());
        $rows = $service->rowsForFamilyTree(
            $familyTree,
            $visibleMargaIds,
        );
        $rootRow = collect($rows)->firstWhere('id', (string) $familyTree->root_person_id)
            ?? collect($rows)->firstWhere('parentId', null)
            ?? collect($rows)->first();
        abort_if($rootRow === null, 404);
        $root = Person::query()->with('marga')->findOrFail((int) $rootRow['id']);

        return Inertia::render('people/silsilah', [
            'people' => $rows,
            'centerPersonId' => (string) $root->id,
            'familyTree' => [
                'id' => $familyTree->id,
                'name' => $familyTree->name ?? $root->name,
                'rootPersonId' => $familyTree->root_person_id,
            ],
            'canEditFamilyTree' => $canEdit,
            'person' => [
                'id' => (string) $root->id,
                'name' => $root->name,
                'alias' => $root->alias,
                'marga' => $root->marga->name ?? 'Batak',
                'birthOrder' => $rootRow['birthOrder'],
            ],
        ]);
    }

    protected function approvedFamilyTreeMatchesUserMarga(FamilyTree $familyTree, User $user): bool
    {
        $accessibleMargaIds = $user->isContributor()
            ? $user->accessibleMargaIds()
            : $user->approvedMargaAccessIds();

        if ($accessibleMargaIds->isNotEmpty()
            && $familyTree->user()->whereIn('role', ['admin', 'subadmin'])->exists()
            && $familyTree->nodes()
                ->whereHas('person', fn ($query) => $query->whereIn('marga_id', $accessibleMargaIds))
                ->exists()) {
            return true;
        }

        $margaId = $familyTree->rootPerson()->value('marga_id');

        return $margaId !== null
            && ($user->isStaff() || $user->isContributor() || $user->approvedMargaAccessIds()->contains($margaId))
            && $familyTree->contributionRequests()
                ->where('status', ContributionRequest::STATUS_APPROVED)
                ->when(
                    ! $user->isStaff() && ! $user->isContributor(),
                    fn ($query) => $query->whereHas('matchedFather', fn ($father) => $father
                        ->whereIn('marga_id', $user->approvedMargaAccessIds())),
                )
                ->when(
                    $user->isContributor(),
                    fn ($query) => $query->whereHas('matchedFather', fn ($father) => $father
                        ->where('marga_id', $user->marga_id)),
                )
                ->exists()
            && $familyTree->nodes()
                ->whereHas('person', fn ($query) => $query->where('marga_id', $margaId))
                ->exists();
    }

    /**
     * Create an editable alternative that starts with the source version's
     * people and contextual links, while retaining independent relationships.
     */
    public function duplicateFamilyTree(Request $request, FamilyTree $familyTree): RedirectResponse
    {
        abort_unless(
            $request->user()->isStaff() || $familyTree->user_id === $request->user()->id,
            403,
            'Anda tidak memiliki akses ke silsilah ini.',
        );

        $rootName = $familyTree->rootPerson()->value('name') ?? 'Silsilah';
        $name = ($familyTree->name ?? $rootName).' - Versi alternatif';
        $copy = app(FamilyTreeVersionService::class)->duplicate($familyTree, $request->user(), $name);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Versi alternatif berhasil dibuat.')]);

        return to_route('family-trees.show', $copy);
    }

    /** Create the first alternative for a family whose V1 is the main graph. */
    public function duplicateFamilyVersion(Request $request, Person $person): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('view', $person);

        $source = FamilyTree::query()
            ->when(! $user->isStaff(), fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('nodes', fn ($nodes) => $nodes->where('person_id', $person->id))
            ->orderByDesc('is_primary')
            ->oldest('id')
            ->first();

        abort_unless($source !== null, 422, 'Belum ada silsilah sumber untuk keluarga ini.');

        $copy = app(FamilyTreeVersionService::class)->duplicateFamily(
            $source,
            $person->id,
            $user,
            'Keluarga '.$person->name.' - Versi alternatif',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Versi alternatif keluarga berhasil dibuat.')]);

        return to_route('people.edit', ['person' => $person, 'version_tree' => $copy->id]);
    }

    /**
     * Edit parent links and sibling order inside one selected tree version.
     */
    public function editFamilyTree(Request $request, FamilyTree $familyTree): Response
    {
        $this->authorizeFamilyTree($request, $familyTree);

        $nodes = $familyTree->nodes()
            ->with('person:id,name,gender')
            ->orderBy('chain')
            ->orderBy('id')
            ->get();

        $rootName = $familyTree->rootPerson()->value('name') ?? 'Silsilah';

        return Inertia::render('people/tree-editor', [
            'familyTree' => [
                'id' => $familyTree->id,
                'name' => $familyTree->name ?? $rootName,
                'sourceName' => $familyTree->source_name,
            ],
            'entries' => $nodes->map(fn ($node) => [
                'id' => $node->id,
                'personId' => $node->person_id,
                'name' => $node->person->name,
                'gender' => $node->person->gender,
                'fatherNodeId' => $node->father_node_id,
                'birthOrder' => $node->birth_order,
                'chain' => $node->chain,
            ])->all(),
        ]);
    }

    /**
     * Persist structural changes only in the selected version.
     */
    public function updateFamilyTree(UpdateFamilyTreeStructureRequest $request, FamilyTree $familyTree): RedirectResponse
    {
        $this->authorizeFamilyTree($request, $familyTree);

        app(FamilyTreeStructureService::class)->update($familyTree, $request->validated('entries'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Struktur versi silsilah berhasil diperbarui.')]);

        return to_route('family-trees.show', $familyTree);
    }

    /**
     * Rename one family tree without changing its structure.
     */
    public function updateFamilyTreeName(UpdateFamilyTreeNameRequest $request, FamilyTree $familyTree): RedirectResponse
    {
        $this->authorizeFamilyTree($request, $familyTree);

        $familyTree->update(['name' => trim($request->validated('name'))]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Nama silsilah berhasil diperbarui.')]);

        return back();
    }

    protected function authorizeFamilyTree(Request $request, FamilyTree $familyTree): void
    {
        abort_unless(
            $request->user()->isStaff() || $familyTree->user_id === $request->user()->id,
            403,
            'Anda tidak memiliki akses ke silsilah ini.',
        );
    }

    /**
     * Build the "silsilah keluarga" payload: the ayah & ibu branches
     * (with their parents and siblings) and the children row where the
     * focused person is highlighted by birth order.
     *
     * @return array<string, mixed>
     */
    protected function familyPreviewPayload(Person $person): array
    {
        $people = Person::query()->with('marga')->get();
        $byId = $people->keyBy('id');

        $father = $person->pending_father || $person->father_id === null
            ? null
            : ($byId[$person->father_id] ?? null);
        $mother = $person->mother_id !== null ? ($byId[$person->mother_id] ?? null) : null;

        $children = $person->pending_father
            ? collect([$person])
            : $people
                ->filter(fn (Person $row) => $row->father_id === $person->father_id && ! $row->pending_father)
                ->sortBy('birth_order')
                ->values();

        $colorsByMarga = [];
        $fallbackIndex = 0;

        foreach ($people as $row) {
            $name = $row->marga->name ?? null;

            if ($name !== null && ! isset($colorsByMarga[$name])) {
                $colorsByMarga[$name] = $row->marga->color ?? $this->fallbackColor($fallbackIndex++);
            }
        }

        $referenced = collect([$person, $father, $mother])
            ->filter()
            ->merge($children);

        $margas = $referenced
            ->pluck('marga.name')
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $name) => [
                'name' => $name,
                'color' => $colorsByMarga[$name] ?? $this->fallbackColor(0),
            ])
            ->all();

        return [
            'centerId' => (string) $person->id,
            'person' => $this->previewNode($person, $byId),
            'father' => $father ? $this->previewNode($father, $byId) : null,
            'mother' => $mother ? $this->previewNode($mother, $byId) : null,
            'children' => $children
                ->map(fn (Person $row) => $this->previewBasic($row))
                ->all(),
            'margas' => $margas,
        ];
    }

    /**
     * Build a preview node with the person's own parents and siblings.
     *
     * @param  Collection<int|string, Person>  $byId
     * @return array<string, mixed>
     */
    protected function previewNode(Person $person, $byId): array
    {
        $grandfather = $person->father_id !== null ? ($byId[$person->father_id] ?? null) : null;
        $grandmother = $person->mother_id !== null ? ($byId[$person->mother_id] ?? null) : null;

        return [
            ...$this->previewBasic($person),
            'parents' => [
                $grandfather ? $this->previewBasic($grandfather) : null,
                $grandmother ? $this->previewBasic($grandmother) : null,
            ],
            'siblings' => $this->siblingsOf($person, $byId),
        ];
    }

    /**
     * @param  Collection<int|string, Person>  $byId
     * @return array<int, array<string, mixed>>
     */
    protected function siblingsOf(Person $person, $byId): array
    {
        return $byId
            ->filter(fn (Person $row) => $row->id !== $person->id && $row->father_id === $person->father_id && ! $row->pending_father)
            ->sortBy('birth_order')
            ->values()
            ->map(fn (Person $row) => $this->previewBasic($row))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewBasic(Person $person): array
    {
        return [
            'id' => (string) $person->id,
            'name' => $person->name,
            'alias' => $person->alias,
            'marga' => $person->marga->name ?? 'Batak',
            'margaColor' => $person->marga?->color,
            'birthYear' => $person->birth_year,
            'birthOrder' => $person->birth_order,
            'image' => $person->image,
        ];
    }

    protected function fallbackColor(int $index): string
    {
        $colors = ['#b34b1e', '#2a527c', '#3e6b48', '#f59e0b', '#7c3aed', '#0e7490'];

        return $colors[$index % count($colors)];
    }

    /**
     * Update the specified person along with their whole family.
     */
    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $user = $request->user();
        $isStaff = $user->isStaff();

        $validated = $request->validated();
        $validated = $this->normalizeRelatedStories($validated);
        $previousImage = $person->image;
        $validated = $this->preparePersonImage($request, $validated);
        $validated['children'] = $request->input('children', []);
        $validated['ownChildren'] = $request->input('ownChildren', []);
        $validated['removed_child_ids'] = $request->input('removed_child_ids', []);
        $validated['removed_own_child_ids'] = $request->input('removed_own_child_ids', []);
        $validated['id'] = $person->id;

        Gate::authorize('update', $person);

        $versionTreeId = $request->integer('version_tree');
        if ($versionTreeId > 0) {
            $familyTree = FamilyTree::query()->findOrFail($versionTreeId);
            $this->authorizeFamilyTree($request, $familyTree);
            app(FamilyTreeStructureService::class)->updateFromFamilyForm($familyTree, $person, $validated);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Versi silsilah berhasil diperbarui.')]);

            return to_route('people.show', $person);
        }

        if (! $isStaff) {
            $forcedMargaId = $user->isContributor()
                ? (int) ($validated['marga_id'] ?? $person->marga_id ?? 0)
                : (int) ($user->marga_id ?? 0);
            abort_unless(
                $forcedMargaId > 0 && $user->accessibleMargaIds()->contains($forcedMargaId),
                403,
                'Marga ini tidak termasuk dalam marga yang dapat Anda kelola.',
            );

            $result = DB::transaction(function () use ($validated, $user, $forcedMargaId) {
                $result = app(FamilyEntryService::class)->save(
                    $validated,
                    forcedMargaId: $forcedMargaId,
                    createdBy: $user->id,
                    deferExistingFatherMatch: true,
                );
                $this->createFatherMatchRequest($result, $user);

                return $result;
            });
        } else {
            app(FamilyEntryService::class)->save($validated, createdBy: $user->id);
        }

        if (
            array_key_exists('image', $validated) &&
            $validated['image'] !== $previousImage
        ) {
            $this->deleteStoredPersonImage($previousImage);
        }

        $message = isset($result) && $result['matchedFather'] !== null
            ? 'Jejak keluarga disimpan. Pencocokan Ayah menunggu persetujuan kontributor.'
            : __('Jejak keluarga berhasil diperbarui.');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('people.show', $person);
    }

    /**
     * Store an uploaded person image or keep the validated external URL.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function preparePersonImage(Request $request, array $validated): array
    {
        unset($validated['image_mode'], $validated['image_file']);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('people', 'public');
            $validated['image'] = Storage::disk('public')->url($path);

            return $validated;
        }

        if ($request->input('image_mode') === 'upload') {
            unset($validated['image']);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeRelatedStories(array $validated): array
    {
        if (! array_key_exists('related_stories', $validated)) {
            return $validated;
        }

        $stories = is_array($validated['related_stories'])
            ? $validated['related_stories']
            : [];

        $validated['related_stories'] = collect($stories)
            ->filter(fn ($story) => is_array($story)
                && (filled($story['title'] ?? null) || filled($story['url'] ?? null)))
            ->map(fn (array $story) => [
                'title' => trim((string) ($story['title'] ?? '')),
                'url' => trim((string) ($story['url'] ?? '')),
            ])
            ->values()
            ->all();

        return $validated;
    }

    private function deleteStoredPersonImage(?string $image): void
    {
        if (! $image) {
            return;
        }

        $path = parse_url($image, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }

    /** @param array<string, mixed> $result */
    protected function createFatherMatchRequest(array $result, User $user): ?ContributionRequest
    {
        $matchedFather = $result['matchedFather'];

        if (! $matchedFather instanceof Person || $result['children']->isEmpty()) {
            return null;
        }

        $focus = $result['focus'] ?? $result['children']->first();
        $affectedIds = $result['children']->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $contribution = ContributionRequest::create([
            'requester_id' => $user->id,
            'matched_father_id' => $matchedFather->id,
            'subject_person_id' => $focus->id,
            'family_tree_id' => $result['familyTrees']->first()?->id,
            'affected_person_ids' => $affectedIds,
        ]);
        $contribution->load(['requester', 'subjectPerson', 'matchedFather']);

        User::query()
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->where('marga_id', $matchedFather->marga_id)
            ->each(fn (User $contributor) => $contributor->notify(new FatherMatchSubmitted($contribution)));

        return $contribution;
    }

    /**
     * Remove the specified person.
     */
    public function destroy(Person $person): RedirectResponse
    {
        Gate::authorize('delete', $person);

        $children = Person::query()
            ->where('father_id', $person->id)
            ->orWhere('mother_id', $person->id)
            ->orderBy('birth_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($children->isNotEmpty()) {
            $childNames = $children->take(5)->pluck('name')->implode(', ');
            $remainingChildren = $children->count() - $children->take(5)->count();
            $suffix = $remainingChildren > 0 ? " dan {$remainingChildren} lainnya" : '';

            throw ValidationException::withMessages([
                'person' => "Anggota ini masih memiliki {$children->count()} keturunan ({$childNames}{$suffix}). Hapus keturunan paling bawah terlebih dahulu.",
            ]);
        }

        $father = $person->father;
        $person->delete();

        if ($father !== null) {
            app(ChainNumberingService::class)->recomputeFromAncestor($father);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Anggota berhasil dihapus.')]);

        return to_route('people.index');
    }

    /**
     * Build the family payload for the form (focused person + parents + siblings).
     *
     * @return array<string, mixed>
     */
    protected function familyPayload(Person $person, ?int $margaId = null): array
    {
        if ($person->pending_father) {
            $siblings = collect([$person]);
            $lineageIds = [$person->id];
        } else {
            $siblings = $person->father_id !== null
                ? Person::query()
                    ->where('father_id', $person->father_id)
                    ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
                    ->where(fn ($query) => $query
                        ->where('pending_father', false)
                        ->orWhere('id', $person->id))
                    ->with(['mother.marga', 'mother.father.marga'])
                    ->orderBy('birth_order')
                    ->get()
                : collect([$person]);

            if (! $siblings->contains('id', $person->id)) {
                $siblings = $siblings->push($person)->sortBy('birth_order')->values();
            }

            $lineageIds = $person->lineage()->pluck('id')->push($person->id)->all();
        }

        $lineage = Person::query()
            ->whereIn('id', $lineageIds)
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->where(fn ($query) => $query
                ->where('gender', 'L')
                ->orWhereNull('gender'))
            ->with([
                'marga',
                'children' => fn ($query) => $query
                    ->when($margaId !== null, fn ($childQuery) => $childQuery->where('marga_id', $margaId))
                    ->where(fn ($childQuery) => $childQuery
                        ->where('gender', 'L')
                        ->orWhereNull('gender'))
                    ->orderBy('birth_order'),
            ])
            ->get()
            ->sortBy(fn (Person $row) => array_search($row->id, $lineageIds))
            ->values();

        $ownChildrenRows = $person->children()
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->orderBy('birth_order')
            ->get();

        $father = ! $person->pending_father
            && ($margaId === null || $person->father?->marga_id === $margaId)
                ? $person->father
                : null;
        $mother = $margaId === null || $person->mother?->marga_id === $margaId
            ? $person->mother
            : null;

        $inferredMothers = $siblings
            ->map(fn (Person $sibling) => $sibling->mother)
            ->filter()
            ->unique('id')
            ->values();
        $persistedMothers = $father?->wives()
            ->with(['marga', 'father.marga'])
            ->get()
            ?? collect();
        $mothers = $persistedMothers
            ->concat($inferredMothers)
            ->unique('id')
            ->values();

        $descendantMap = $this->descendantMap(
            $siblings->pluck('id')->merge($ownChildrenRows->pluck('id'))->all(),
        );

        return [
            'id' => $person->id,
            'name' => $person->name,
            'gender' => $person->gender,
            'alias' => $person->alias,
            'marga_id' => $person->marga_id,
            'father_id' => $father?->id,
            'mother_id' => $mother?->id,
            'birth_order' => $person->birth_order,
            'sibling_count' => $person->sibling_count,
            'chain' => $person->chain,
            'pending' => (bool) $person->pending_father,
            'is_public' => (bool) $person->is_public,
            'birth_year' => $person->birth_year,
            'death_year' => $person->death_year,
            'province_code' => $person->province_code,
            'regency_code' => $person->regency_code,
            'district_code' => $person->district_code,
            'village_code' => $person->village_code,
            'image' => $person->image,
            'bio' => $person->bio,
            'related_stories' => $person->related_stories ?? [],
            'spouse' => $person->spouse,
            'spouse_marga' => $person->spouse_marga,
            'father' => $father
                ? [
                    'id' => $father->id,
                    'name' => $father->name,
                    'alias' => $father->alias,
                    'marga_id' => $father->marga_id,
                    'marga' => $father->marga?->name,
                    'chain' => $father->chain,
                    'birth_year' => $father->birth_year,
                    'death_year' => $father->death_year,
                ]
                : null,
            'mother' => $mother
                ? [
                    'id' => $mother->id,
                    'name' => $mother->name,
                    'alias' => $mother->alias,
                    'marga_id' => $mother->marga_id,
                    'marga' => $mother->marga?->name,
                    'birth_year' => $mother->birth_year,
                    'death_year' => $mother->death_year,
                ]
                : null,
            'mothers' => $mothers
                ->map(fn (Person $wife) => [
                    'id' => $wife->id,
                    'name' => $wife->name,
                    'alias' => $wife->alias,
                    'marga_id' => $wife->marga_id,
                    'marga' => $wife->marga?->name,
                    'birth_year' => $wife->birth_year,
                    'death_year' => $wife->death_year,
                    'father_name' => $wife->father?->name,
                    'father_marga_id' => $wife->father?->marga_id,
                    'father_marga' => $wife->father?->marga?->name,
                ])
                ->all(),
            'lineage' => $lineage
                ->map(fn (Person $row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'marga' => $row->marga?->name,
                    'chain' => $row->chain,
                    'is_self' => $row->id === $person->id,
                    'editable' => Gate::allows('update', $row),
                    'children' => $row->children
                        ->map(fn (Person $child) => [
                            'id' => $child->id,
                            'name' => $child->name,
                            'gender' => $child->gender,
                            'marga' => $child->marga?->name,
                            'chain' => $child->chain,
                            'birth_order' => $child->birth_order,
                            'editable' => Gate::allows('update', $child),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'children' => $siblings
                ->map(fn (Person $sibling) => [
                    'id' => $sibling->id,
                    'name' => $sibling->name,
                    'alias' => $sibling->alias,
                    'gender' => $sibling->gender,
                    'spouse' => $sibling->spouse,
                    'spouse_marga' => $sibling->spouse_marga,
                    'marga_id' => $sibling->marga_id,
                    'marga' => $sibling->marga?->name,
                    'birth_order' => $sibling->birth_order,
                    'chain' => $sibling->chain,
                    'pending' => (bool) $sibling->pending_father,
                    'descendant_count' => $descendantMap[$sibling->id]['count'] ?? 0,
                    'descendant_names' => $descendantMap[$sibling->id]['names'] ?? [],
                ])
                ->values()
                ->all(),
            'ownChildren' => $ownChildrenRows
                ->map(fn (Person $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'alias' => $child->alias,
                    'gender' => $child->gender,
                    'spouse' => $child->spouse,
                    'spouse_marga' => $child->spouse_marga,
                    'marga_id' => $child->marga_id,
                    'marga' => $child->marga?->name,
                    'new_marga' => '',
                    'birth_order' => $child->birth_order,
                    'chain' => $child->chain,
                    'pending' => (bool) $child->pending_father,
                    'mother_id' => $child->mother_id,
                    'descendant_count' => $descendantMap[$child->id]['count'] ?? 0,
                    'descendant_names' => $descendantMap[$child->id]['names'] ?? [],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Use the same family form payload, but read parent and child placement
     * from the selected version's nodes instead of the shared Person graph.
     *
     * @return array<string, mixed>
     */
    protected function familyPayloadForVersion(Person $person, int $treeId, ?int $margaId = null): array
    {
        $payload = $this->familyPayload($person, $margaId);
        $tree = FamilyTree::query()->with(['nodes.person.marga'])->findOrFail($treeId);
        $nodes = $tree->nodes->keyBy('person_id');
        $focusNode = $nodes->get($person->id);

        if ($focusNode === null) {
            return $payload;
        }

        $baseChildren = collect($payload['children'])->keyBy('id');
        $baseOwnChildren = collect($payload['ownChildren'])->keyBy('id');
        $rowFor = function (FamilyTreeNode $node, $base): array {
            $personRow = $node->person;
            $row = is_array($base) ? $base : [];

            return [
                ...$row,
                'id' => $personRow->id,
                'name' => $personRow->name,
                'alias' => $personRow->alias,
                'gender' => $personRow->gender,
                'marga_id' => $personRow->marga_id,
                'marga' => $personRow->marga?->name,
                'birth_order' => $node->birth_order,
                'chain' => $node->chain,
                'pending' => $node->pending_father,
                'mother_id' => $node->motherNode?->person_id,
            ];
        };

        $fatherNode = $focusNode->fatherNode;
        $payload['birth_order'] = $focusNode->birth_order;
        $payload['chain'] = $focusNode->chain;
        $payload['father_id'] = $fatherNode?->person_id;
        $payload['father'] = $fatherNode?->person
            ? [
                'id' => $fatherNode->person->id,
                'name' => $fatherNode->person->name,
                'alias' => $fatherNode->person->alias,
                'marga_id' => $fatherNode->person->marga_id,
                'marga' => $fatherNode->person->marga?->name,
                'chain' => $fatherNode->chain,
                'birth_year' => $fatherNode->person->birth_year,
                'death_year' => $fatherNode->person->death_year,
            ]
            : null;

        $siblingNodes = $fatherNode?->children()->with('person.marga')->orderBy('birth_order')->orderBy('id')->get()
            ?? collect([$focusNode]);
        $childNodes = $focusNode->children()->with('person.marga')->orderBy('birth_order')->orderBy('id')->get();

        $payload['children'] = $siblingNodes
            ->map(fn (FamilyTreeNode $node) => $rowFor($node, $baseChildren->get($node->person_id, [])))
            ->values()->all();
        $payload['ownChildren'] = $childNodes
            ->map(fn (FamilyTreeNode $node) => $rowFor($node, $baseOwnChildren->get($node->person_id, [])))
            ->values()->all();

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    protected function familyPayloadVisibleToUser(array $payload, User $user): array
    {
        if ($user->isStaff() || $user->isContributor()) {
            return $payload;
        }

        $visiblePersonIds = Person::query()
            ->whereHas('familyTrees', fn ($familyTrees) => $familyTrees
                ->where(fn ($access) => $access
                    ->where('family_trees.user_id', $user->id)
                    ->orWhereHas('shares', fn ($shares) => $shares
                        ->whereBelongsTo($user, 'recipient')
                        ->where('status', FamilyTreeShare::STATUS_ACCEPTED))))
            ->pluck('people.id')
            ->mapWithKeys(fn ($id) => [(int) $id => true]);

        $payload['lineage'] = collect($payload['lineage'] ?? [])
            ->filter(fn (array $entry) => $visiblePersonIds->has((int) $entry['id']))
            ->map(function (array $entry) use ($visiblePersonIds): array {
                $entry['children'] = collect($entry['children'] ?? [])
                    ->filter(fn (array $child) => $visiblePersonIds->has((int) $child['id']))
                    ->values()
                    ->all();

                return $entry;
            })
            ->values()
            ->all();

        return $payload;
    }

    /**
     * Map person ids to their patrilineal descendant count and a bounded list
     * of descendant names (used to preview what a delete would cascade).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{count: int, names: array<int, string>}>
     */
    protected function descendantMap(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $byId = Person::query()->whereIn('id', $ids)->get()->keyBy('id');
        $map = [];

        foreach ($ids as $id) {
            $person = $byId->get($id);

            if ($person === null) {
                $map[$id] = ['count' => 0, 'names' => []];

                continue;
            }

            $descendants = $this->descendantsOf($person);

            $map[$id] = [
                'count' => $descendants->count(),
                'names' => $descendants->take(50)->pluck('name')->values()->all(),
            ];
        }

        return $map;
    }

    /**
     * All patrilineal descendants of a person, ordered by birth order.
     *
     * @return \Illuminate\Support\Collection<int, Person>
     */
    protected function descendantsOf(Person $person): \Illuminate\Support\Collection
    {
        $result = collect();
        $queue = [$person->id];

        while ($queue !== []) {
            $children = Person::query()
                ->whereIn('father_id', $queue)
                ->orderBy('birth_order')
                ->orderBy('id')
                ->get();

            $result = $result->merge($children);
            $queue = $children->pluck('id')->all();
        }

        return $result;
    }

    /**
     * Existing people used by the autocomplete. Keep the person id and
     * father context so equal names remain distinguishable.
     *
     * @return array<int, array{id: int, name: string, alias: string|null, gender: string|null, spouse: string|null, spouse_marga: string|null, marga_id: int|null, marga: string|null, father_id: int|null, father_name: string|null, chain: string|null}>
     */
    protected function nameSuggestions(int|\Illuminate\Support\Collection|null $margaId = null): array
    {
        return Person::query()
            ->with(['father:id,name', 'marga:id,name'])
            ->when($margaId instanceof \Illuminate\Support\Collection, fn ($query) => $query->whereIn('marga_id', $margaId))
            ->when(is_int($margaId), fn ($query) => $query->where('marga_id', $margaId))
            ->whereNotNull('name')
            ->where('name', '!=', 'N/A')
            ->orderBy('name')
            ->orderBy('father_id')
            ->limit(300)
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'gender' => $person->gender,
                'spouse' => $person->spouse,
                'spouse_marga' => $person->spouse_marga,
                'marga_id' => $person->marga_id,
                'marga' => $person->marga?->name,
                'father_id' => $person->father_id,
                'father_name' => $person->father_id !== null
                    ? $person->father->name
                    : null,
                'chain' => $person->chain,
            ])
            ->all();
    }

    /**
     * Male people eligible to become the selected person's father.
     * Legacy fathers without a recorded gender remain selectable.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fatherSuggestions(?Person $person = null, int|\Illuminate\Support\Collection|null $margaId = null): array
    {
        return Person::query()
            ->with(['father:id,name', 'marga:id,name'])
            ->when($margaId instanceof \Illuminate\Support\Collection, fn ($query) => $query->whereIn('marga_id', $margaId))
            ->when(is_int($margaId), fn ($query) => $query->where('marga_id', $margaId))
            ->where('gender', 'L')
            ->when($person !== null, fn ($query) => $query->whereNotIn('id', $person->ineligibleFatherIds()))
            ->whereNotNull('name')
            ->where('name', '!=', 'N/A')
            ->orderBy('name')
            ->orderBy('father_id')
            ->limit(300)
            ->get()
            ->map(fn (Person $father) => [
                'id' => $father->id,
                'name' => $father->name,
                'gender' => $father->gender,
                'marga_id' => $father->marga_id,
                'marga' => $father->marga?->name,
                'father_id' => $father->father_id,
                'father_name' => $father->father?->name,
                'chain' => $father->chain,
            ])
            ->all();
    }

    /**
     * Marga options for the form select.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function margaOptions(): array
    {
        return Marga::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
            ])
            ->all();
    }

    /**
     * Family trees previously created by the signed-in account.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function familyTrees(User $user, ?Person $focus = null): array
    {
        return FamilyTree::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where(fn ($access) => $access
                ->whereBelongsTo($user)
                ->orWhereHas('shares', fn ($shares) => $shares
                    ->whereBelongsTo($user, 'recipient')
                    ->where('status', FamilyTreeShare::STATUS_ACCEPTED))))
            ->whereNotNull('root_person_id')
            ->when($focus !== null, fn ($query) => $query->whereHas(
                'nodes',
                fn ($nodes) => $nodes->where('person_id', $focus->id),
            ))
            ->with(['user:id,name', 'rootPerson:id,name,marga_id', 'nodes.person:id,name', 'shares.recipient:id,name,email', 'contributionRequests:id,family_tree_id,status'])
            ->withExists(['deletionRequests as deletion_pending' => fn ($query) => $query
                ->where('status', FamilyTreeDeletionRequest::STATUS_PENDING)])
            ->latest('updated_at')
            ->get(['id', 'user_id', 'root_person_id', 'name', 'source_name', 'is_primary', 'updated_at'])
            ->filter(fn (FamilyTree $tree) => $tree->rootPerson !== null)
            ->map(fn (FamilyTree $tree): array => $this->familyTreeHistoryEntry($tree, $user))
            ->values()
            ->all();
    }

    /**
     * Family trees visible in the marga list.
     * Staff can see every rooted tree; other accounts are limited to
     * approved trees within their accessible margas.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function approvedMargaTrees(User $user): array
    {
        $margaIds = $user->isStaff()
            ? null
            : ($user->isContributor() ? $user->accessibleMargaIds() : $user->approvedMargaAccessIds());
        if (! $user->isStaff() && $margaIds->isEmpty()) {
            return [];
        }

        return Marga::query()
            ->whereNotNull('identity_person_id')
            ->when($margaIds !== null, fn ($query) => $query->whereKey($margaIds))
            ->with('identityPerson:id,name')
            ->withCount('people')
            ->orderBy('name')
            ->get(['id', 'name', 'identity_person_id'])
            ->map(fn (Marga $marga): array => [
                'id' => $marga->id,
                'name' => $marga->name,
                'identity_person_id' => $marga->identity_person_id,
                'identity_person_name' => $marga->identityPerson?->name,
                'people_count' => $marga->people_count,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    protected function familyTreeHistoryEntry(FamilyTree $tree, User $user): array
    {
        $canManage = $user->isStaff() || $tree->user_id === $user->id;

        return [
            'id' => $tree->id,
            'root_person_id' => $tree->root_person_id,
            'member_person_ids' => $tree->nodes
                ->pluck('person_id')
                ->push($tree->root_person_id)
                ->map(fn ($personId) => (int) $personId)
                ->unique()
                ->values()
                ->all(),
            'root_name' => $this->familyTreeRootName($tree),
            'name' => $tree->name,
            'source_name' => $tree->source_name,
            'is_primary' => $tree->is_primary,
            'access' => $canManage ? 'owner' : 'shared',
            'owner_name' => $tree->user->name,
            'can_manage' => $canManage,
            'can_share' => $canManage,
            'can_append' => $user->can('append', $tree),
            'can_request_marga_tree' => $canManage
                && $user->marga_id !== null
                && $tree->rootPerson?->marga_id === $user->marga_id,
            'marga_request_status' => $tree->contributionRequests
                ->first(fn (ContributionRequest $request) => in_array(
                    $request->status,
                    [ContributionRequest::STATUS_PENDING, ContributionRequest::STATUS_APPROVED],
                    true,
                ))?->status,
            'can_delete' => $user->isAdmin() || $tree->user_id === $user->id,
            'shares' => $canManage ? $tree->shares->map(fn (FamilyTreeShare $share) => [
                'id' => $share->id,
                'recipient_id' => $share->recipient_id,
                'recipient_name' => $share->recipient->name,
                'recipient_email' => $share->recipient->email,
                'status' => $share->status,
            ])->values()->all() : [],
            'deletion_pending' => (bool) $tree->deletion_pending,
            'updated_at' => $tree->updated_at->toISOString(),
        ];
    }

    protected function margaAccessStatus(User $user, ?int $margaId): ?string
    {
        if ($margaId === null || $user->isStaff() || $user->isContributor()) {
            return null;
        }

        return $user->margaAccessRequests()
            ->where('marga_id', $margaId)
            ->latest('id')
            ->value('status');
    }

    protected function scopePeopleVisibleToUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('familyTrees', fn ($familyTrees) => $familyTrees
            ->where(fn ($access) => $access
                ->where('family_trees.user_id', $user->id)
                ->orWhereHas('shares', fn ($shares) => $shares
                    ->whereBelongsTo($user, 'recipient')
                    ->where('status', FamilyTreeShare::STATUS_ACCEPTED))
                ->orWhere(function ($adminTree) use ($user): void {
                    $adminTree
                        ->whereHas('user', fn ($owner) => $owner->whereIn('role', ['admin', 'subadmin']))
                        ->whereHas('nodes.person', fn ($person) => $person
                            ->whereIn('marga_id', $user->approvedMargaAccessIds()));
                })));
    }

    /** @return array{shareableAccounts: array<int, array<string, mixed>>, pendingTreeShares: array<int, array<string, mixed>>} */
    protected function familyTreeSharingPayload(User $user): array
    {
        $shareableAccounts = User::query()
            ->whereKeyNot($user->id)
            ->where('role', '!=', 'admin')
            ->when(! $user->isStaff(), fn ($query) => $query->where('marga_id', $user->marga_id))
            ->with('marga:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'marga_id'])
            ->map(fn (User $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'marga' => $account->marga?->name,
            ])->all();

        $pendingTreeShares = FamilyTreeShare::query()
            ->whereBelongsTo($user, 'recipient')
            ->where('status', FamilyTreeShare::STATUS_PENDING)
            ->with(['sender:id,name', 'familyTree:id,user_id,root_person_id,name'])
            ->latest()
            ->get()
            ->map(fn (FamilyTreeShare $share) => [
                'id' => $share->id,
                'tree_name' => $share->familyTree->name ?? 'Silsilah keluarga',
                'sender_name' => $share->sender->name,
            ])->all();

        return compact('shareableAccounts', 'pendingTreeShares');
    }

    protected function familyTreeRootName(FamilyTree $tree): ?string
    {
        if ($tree->root_person_id === null) {
            return null;
        }

        $tree->loadMissing(['rootPerson:id,name', 'nodes.person:id,name']);

        $nodesById = $tree->nodes->keyBy('id');
        $node = $tree->nodes->firstWhere('person_id', $tree->root_person_id);
        $visited = [];

        while ($node !== null && $node->father_node_id !== null && ! isset($visited[$node->id])) {
            $visited[$node->id] = true;
            $father = $nodesById->get($node->father_node_id);

            if ($father === null) {
                break;
            }

            $node = $father;
        }

        return $node?->person?->name ?? $tree->rootPerson->name;
    }

    /**
     * Marga options scoped to a single user's marga.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function margaOptionsForUser(User $user): array
    {
        $margaIds = $user->accessibleMargaIds();

        if ($margaIds->isEmpty()) {
            return [];
        }

        return Marga::query()
            ->whereIn('id', $margaIds)
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
            ])
            ->all();
    }

    /**
     * Locked marga payload sent to the form so family entries stay scoped.
     *
     * @return array{id: int, name: string}|null
     */
    protected function lockedMarga(User $user): ?array
    {
        $marga = $user->marga;

        return $marga !== null ? ['id' => $marga->id, 'name' => $marga->name] : null;
    }
}
