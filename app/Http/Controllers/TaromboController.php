<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaromboTreeSettingsRequest;
use App\Models\ContactRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\FamilyTreeShare;
use App\Models\IdentityRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeFamilyNameService;
use App\Services\TaromboStatisticsService;
use App\Services\TaromboTreeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

class TaromboController extends Controller
{
    /**
     * Show the tarombo tree for authenticated users (scoped to their marga).
     */
    public function index(Request $request): Response
    {
        if ($request->user() === null) {
            $service = app(TaromboTreeService::class);
            $tree = $service->publicRows();

            return Inertia::render('tarombo/public', [
                'people' => $tree['rows'],
                'margas' => $service->margas(publicOnly: true),
                'truncated' => $tree['truncated'],
                'stats' => [
                    'totalPeople' => count($tree['rows']),
                    'totalMargas' => Marga::query()
                        ->whereHas('people', fn (Builder $query) => $query->where('is_public', true))
                        ->count(),
                    'totalGenerations' => app(TaromboStatisticsService::class)
                        ->maxGenerationDepth(Person::query()->public()),
                ],
            ]);
        }

        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedMargaId, $margaTree, $accountTreePersonIds] = $this->treeData($request);

        return Inertia::render('tarombo/index', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
            'identity' => $identity,
            'familyTreeOptions' => $familyTreeOptions,
            'selectedFamilyTreeId' => $selectedFamilyTreeId,
            'selectedMargaId' => $selectedMargaId,
            'margaTree' => $margaTree,
            'accountTreePersonIds' => $accountTreePersonIds,
            'familyName' => $this->familyName($selectedFamilyTreeId, $margaTree),
        ]);
    }

    /**
     * Show a single tarombo view without the dashboard layout (full screen).
     */
    public function fullscreen(Request $request, string $view): Response
    {
        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedMargaId, $margaTree, $accountTreePersonIds] = $this->treeData($request);

        return Inertia::render('tarombo/fullscreen', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
            'view' => $view,
            'identity' => $identity,
            'initialPersonId' => $request->query('person'),
            'familyTreeOptions' => $familyTreeOptions,
            'selectedFamilyTreeId' => $selectedFamilyTreeId,
            'selectedMargaId' => $selectedMargaId,
            'margaTree' => $margaTree,
            'accountTreePersonIds' => $accountTreePersonIds,
            'familyName' => $this->familyName($selectedFamilyTreeId, $margaTree),
            // Tree display settings are reserved for admin and sub-admin accounts.
            'treeSettings' => $request->user()->isStaff()
                ? $request->user()->tarombo_tree_settings
                : null,
        ]);
    }

    public function updateTreeSettings(UpdateTaromboTreeSettingsRequest $request): RedirectResponse
    {
        $request->user()->update(['tarombo_tree_settings' => $request->validated()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengaturan tampilan pohon disimpan.',
        ]);

        return back();
    }

    public function resetTreeSettings(Request $request): RedirectResponse
    {
        throw_unless($request->user()->isStaff(), AuthorizationException::class);

        $request->user()->update(['tarombo_tree_settings' => null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tampilan pohon dikembalikan ke bawaan.',
        ]);

        return back();
    }

    /**
     * The people of one alternative family-tree version. The tarombo page only
     * lists versions by name; each one is loaded here when it is opened.
     */
    public function alternativeTree(Request $request, FamilyTree $familyTree): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $familyTree->based_on_id !== null
                && $this->accountFamilyTreesQuery($user)->whereKey($familyTree->id)->exists(),
            403,
            'Versi silsilah ini tidak tersedia di Silsilah Milik Akun.',
        );

        $people = collect($this->withContactState(
            app(TaromboTreeService::class)->rowsForFamilyTree($familyTree),
            $user,
        ));

        return response()->json([
            'people' => $this->connectedFromRoot($people, (string) $familyTree->root_person_id),
        ]);
    }

    /**
     * The "Nama Keluarga" heading the tree: for an account tree, the name
     * entered on its root branch; for a marga's lower tree, the name of the
     * family tree rooted at the marga's identity person. Both fall back to the
     * tree's own name. The upper tree has no single family, so it has none.
     *
     * @param  array{identityPersonId: string|null, direction: string}|null  $margaTree
     */
    private function familyName(?int $familyTreeId, ?array $margaTree): ?string
    {
        if ($familyTreeId !== null) {
            $tree = FamilyTree::query()->find($familyTreeId);
            $personId = $tree?->root_person_id;
        } elseif ($margaTree !== null && $margaTree['direction'] === 'lower' && $margaTree['identityPersonId'] !== null) {
            $personId = (int) $margaTree['identityPersonId'];
            $tree = FamilyTree::query()
                ->where('root_person_id', $personId)
                ->whereNull('based_on_id')
                ->oldest('id')
                ->first();
        } else {
            return null;
        }

        if ($tree === null) {
            return null;
        }

        return $personId !== null
            ? app(FamilyTreeFamilyNameService::class)->forPerson($tree, (int) $personId)
            : $tree->name;
    }

    /**
     * Build the scoped tarombo rows and marga legend for the current user.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array{id: int, name: string, rootPersonId: string}>, 3: array<string, mixed>, 4: array<int, array<string, mixed>>, 5: int|null, 6: int|null, 7: array<string, mixed>|null, 8: array<int, string>}
     */
    private function treeData(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('currentPerson');
        $service = app(TaromboTreeService::class);
        $accountFamilyTrees = $this->accountFamilyTrees($user);
        $approvedMargas = $this->approvedMargas($user);
        $requestedFamilyTreeId = $request->filled('family_tree')
            ? $request->integer('family_tree')
            : null;
        $requestedMargaId = $request->filled('marga_id')
            ? $request->integer('marga_id')
            : null;

        abort_if(
            $requestedFamilyTreeId !== null && $requestedMargaId !== null,
            422,
            'Pilih satu sumber silsilah.',
        );

        $selectedFamilyTree = $requestedFamilyTreeId !== null
            ? $accountFamilyTrees->firstWhere('id', $requestedFamilyTreeId)
            : null;
        $selectedMarga = $requestedMargaId !== null
            ? $approvedMargas->firstWhere('id', $requestedMargaId)
            : null;

        abort_if(
            $requestedFamilyTreeId !== null && $selectedFamilyTree === null,
            403,
            'Silsilah ini tidak tersedia di Silsilah Milik Akun.',
        );
        abort_if(
            $requestedMargaId !== null && $selectedMarga === null,
            403,
            'Marga ini tidak tersedia di Daftar Silsilah Marga.',
        );

        if ($requestedFamilyTreeId === null && $requestedMargaId === null) {
            $selectedFamilyTree = $accountFamilyTrees
                ->sortBy([
                    ['is_primary', 'desc'],
                    ['updated_at', 'desc'],
                    ['id', 'desc'],
                ])
                ->first();

            if ($selectedFamilyTree === null) {
                $selectedMarga = $approvedMargas->first();
            }
        }

        $request->validate(['marga_depth' => ['sometimes', 'integer', 'in:5']]);
        $direction = $request->string('marga_direction', 'lower')->toString();
        $descendantGenerations = $direction === 'lower' && $request->has('marga_depth') ? 5 : null;
        abort_if(
            $selectedMarga !== null && ! in_array($direction, ['upper', 'lower'], true),
            404,
        );

        $selectedFamilyTreeId = $selectedFamilyTree?->id;
        $selectedMargaId = $selectedMarga?->id;
        $rows = $this->withContactState(match (true) {
            $selectedFamilyTree instanceof FamilyTree => $service->rowsForFamilyTree($selectedFamilyTree),
            $selectedMarga instanceof Marga => $service->rowsForMarga(
                $selectedMarga,
                $direction,
                maxDepth: (int) config('tarombo.dashboard_max_depth'),
                maxNodes: (int) config('tarombo.dashboard_max_nodes'),
                descendantGenerations: $descendantGenerations,
            ),
            default => [],
        }, $user);
        $visiblePersonIds = collect($rows)->pluck('id')->flip();

        // Versions are listed by name only; their people are loaded by
        // alternativeTree() when a version is opened in the tree.
        $alternativeTrees = $accountFamilyTrees
            ->filter(fn (FamilyTree $tree) => $tree->based_on_id !== null
                && $visiblePersonIds->has((string) $tree->root_person_id))
            ->map(fn (FamilyTree $tree): array => [
                'id' => $tree->id,
                'name' => $tree->name ?? 'Versi alternatif',
                'rootPersonId' => (string) $tree->root_person_id,
            ])
            ->values()
            ->all();

        // Only a marga's lower tree uses these ids, to keep the detached
        // branches limited to people in the account's own silsilah.
        $accountTreePersonIds = $selectedMarga instanceof Marga && $direction === 'lower'
            ? $this->accountTreePersonIds($accountFamilyTrees)
            : [];

        $margaTree = $selectedMarga instanceof Marga ? [
            'margaId' => $selectedMarga->id,
            'margaName' => $selectedMarga->name,
            'identityPersonId' => $selectedMarga->identity_person_id !== null
                ? (string) $selectedMarga->identity_person_id
                : null,
            'direction' => $direction,
            'descendantGenerations' => $descendantGenerations,
            'canReorderSiblings' => $user->isStaff()
                || ($user->isContributor() && $user->accessibleMargaIds()->contains($selectedMarga->id)),
        ] : null;

        $identityRequest = IdentityRequest::query()
            ->with(['person:id,name', 'reviewer:id,name'])
            ->where('requester_id', $user->id)
            ->latest()
            ->first();

        return [
            $rows,
            $service->margas(),
            $alternativeTrees,
            [
                'canSelectAnyPerson' => $user->isAdmin(),
                'currentUserId' => $user->id,
                'currentPersonId' => $user->current_person_id !== null ? (string) $user->current_person_id : null,
                'currentPersonName' => $user->currentPerson?->name,
                'request' => $identityRequest ? [
                    'id' => $identityRequest->id,
                    'personId' => (string) $identityRequest->person_id,
                    'personName' => $identityRequest->person->name,
                    'status' => $identityRequest->status,
                    'reviewer' => $identityRequest->reviewer?->name,
                    'reviewedAt' => $identityRequest->reviewed_at?->format('d M Y H:i'),
                    'reason' => $identityRequest->rejection_reason,
                ] : null,
            ],
            $accountFamilyTrees
                ->map(fn (FamilyTree $tree) => [
                    'id' => $tree->id,
                    'value' => 'account:'.$tree->id,
                    'name' => $tree->name ?? $tree->rootPerson?->name ?? 'Silsilah',
                    'rootName' => $tree->rootPerson?->name ?? 'Akar belum ditentukan',
                    'rootPersonId' => $tree->root_person_id,
                    'group' => 'account',
                    'canManage' => $user->isStaff() || $tree->user_id === $user->id,
                ])
                ->concat($approvedMargas
                    ->filter(fn (Marga $marga) => $marga->identity_person_id !== null)
                    ->map(fn (Marga $marga) => [
                        'id' => $marga->id,
                        'value' => 'marga:'.$marga->id,
                        'name' => 'Keluarga '.($marga->identityPerson?->name ?? $marga->name),
                        'rootName' => $marga->identityPerson?->name ?? $marga->name,
                        'rootPersonId' => $marga->identity_person_id,
                        'group' => 'marga',
                    ]))
                ->values()
                ->all(),
            $selectedFamilyTreeId,
            $selectedMargaId,
            $margaTree,
            $accountTreePersonIds,
        ];
    }

    /**
     * Person ids that appear in any of the account's Silsilah Milik Akun.
     * A version inherits every person of the tree it is based on (see
     * FamilyTreeInheritanceService), so the whole based-on chain is read in
     * one node query instead of resolving every tree separately.
     *
     * @param  Collection<int, FamilyTree>  $accountFamilyTrees
     * @return array<int, string>
     */
    private function accountTreePersonIds(Collection $accountFamilyTrees): array
    {
        $treeIds = collect($accountFamilyTrees->modelKeys());
        $baseTreeIds = $accountFamilyTrees->pluck('based_on_id')->filter();

        while (($baseTreeIds = $baseTreeIds->unique()->diff($treeIds))->isNotEmpty()) {
            $treeIds = $treeIds->merge($baseTreeIds);
            $baseTreeIds = FamilyTree::query()->whereKey($baseTreeIds)->pluck('based_on_id')->filter();
        }

        return FamilyTreeNode::query()
            ->whereIn('family_tree_id', $treeIds)
            ->distinct()
            ->orderBy('person_id')
            ->pluck('person_id')
            ->map(fn (int|string $id): string => (string) $id)
            ->all();
    }

    /**
     * Rows reachable from the root through parent links, in their original order.
     *
     * @param  SupportCollection<int, array<string, mixed>>  $people
     * @return array<int, array<string, mixed>>
     */
    private function connectedFromRoot(SupportCollection $people, string $rootId): array
    {
        if (! $people->contains('id', $rootId)) {
            return [];
        }

        $childrenByParent = $people
            ->filter(fn (array $person) => $person['parentId'] !== null)
            ->groupBy('parentId');
        $connected = [];
        $queue = [$rootId];

        for ($index = 0; $index < count($queue); $index++) {
            $personId = $queue[$index];

            if (isset($connected[$personId])) {
                continue;
            }

            $connected[$personId] = true;
            array_push($queue, ...$childrenByParent->get($personId, collect())->pluck('id'));
        }

        return $people
            ->filter(fn (array $person) => isset($connected[$person['id']]))
            ->values()
            ->all();
    }

    /**
     * Family trees in the account's Silsilah Milik Akun: every tree for an
     * admin, otherwise the account's own trees and accepted shares.
     *
     * @return Builder<FamilyTree>
     */
    private function accountFamilyTreesQuery(User $user): Builder
    {
        return FamilyTree::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where(
                fn (Builder $access) => $access
                    ->whereBelongsTo($user)
                    ->orWhereHas('shares', fn (Builder $shares) => $shares
                        ->whereBelongsTo($user, 'recipient')
                        ->where('status', FamilyTreeShare::STATUS_ACCEPTED)),
            ))
            ->whereNotNull('root_person_id');
    }

    /** @return Collection<int, FamilyTree> */
    private function accountFamilyTrees(User $user): Collection
    {
        return $this->accountFamilyTreesQuery($user)
            ->with(['rootPerson:id,name'])
            ->latest('updated_at')
            ->get(['id', 'user_id', 'root_person_id', 'based_on_id', 'name', 'is_primary', 'updated_at'])
            ->filter(fn (FamilyTree $tree) => $tree->rootPerson !== null)
            ->values();
    }

    /** @return Collection<int, Marga> */
    private function approvedMargas(User $user): Collection
    {
        $margaIds = $user->isStaff()
            ? null
            : ($user->isContributor() ? $user->accessibleMargaIds() : $user->approvedMargaAccessIds());

        if (! $user->isStaff() && $margaIds->isEmpty()) {
            return new Collection;
        }

        return Marga::query()
            ->when($margaIds !== null, fn (Builder $query) => $query->whereKey($margaIds))
            ->with('identityPerson:id,name,father_id,marga_id')
            ->orderBy('name')
            ->get(['id', 'name', 'identity_person_id']);
    }

    /**
     * Add the current user's contact-list state to each claimed account.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withContactState(array $rows, User $viewer): array
    {
        $accountIds = collect($rows)
            ->flatMap(fn (array $row) => $row['claimedAccounts'] ?? [])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== $viewer->id)
            ->unique()
            ->values();

        if ($accountIds->isEmpty()) {
            return $rows;
        }

        $approvedContactIds = ContactRequest::query()
            ->where('status', ContactRequest::STATUS_APPROVED)
            ->where(function (Builder $query) use ($viewer) {
                $query->where('requester_id', $viewer->id)
                    ->orWhere('recipient_id', $viewer->id);
            })
            ->get(['requester_id', 'recipient_id'])
            ->map(fn (ContactRequest $request): int => $request->requester_id === $viewer->id
                ? $request->recipient_id
                : $request->requester_id);

        $contactIds = User::query()
            ->whereKey($accountIds)
            ->where('role', '!=', 'admin')
            ->where(function (Builder $query) use ($viewer, $approvedContactIds) {
                $query->when(
                    $viewer->marga_id !== null,
                    fn (Builder $contacts) => $contacts->where('marga_id', $viewer->marga_id),
                    fn (Builder $contacts) => $contacts->whereRaw('1 = 0'),
                )->orWhereIn('id', $approvedContactIds);
            })
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return collect($rows)
            ->map(function (array $row) use ($contactIds, $viewer): array {
                $row['claimedAccounts'] = collect($row['claimedAccounts'] ?? [])
                    ->map(fn (array $account): array => [
                        ...$account,
                        'isContact' => (int) $account['id'] === $viewer->id
                            || in_array((int) $account['id'], $contactIds, true),
                    ])
                    ->all();

                return $row;
            })
            ->all();
    }

    /**
     * Show the public tarombo tree built from all database records.
     */
    public function public(): Response
    {
        $service = app(TaromboTreeService::class);
        $tree = $service->publicRows();

        return Inertia::render('tarombo/public', [
            'people' => $tree['rows'],
            'margas' => $service->margas(publicOnly: true),
            'truncated' => $tree['truncated'],
            'stats' => [
                'totalPeople' => Person::query()->public()->count(),
                'totalMargas' => Marga::query()->whereHas('people', fn (Builder $query) => $query->where('is_public', true))->count(),
                'totalGenerations' => app(TaromboStatisticsService::class)
                    ->maxGenerationDepth(Person::query()->public()),
            ],
        ]);
    }

    /**
     * Show the public tarombo tree in a dedicated full-page layout.
     */
    public function publicFullscreen(Request $request): Response
    {
        $service = app(TaromboTreeService::class);
        $tree = $service->publicRows();

        return Inertia::render('tarombo/public-fullscreen', [
            'people' => $tree['rows'],
            'margas' => $service->margas(publicOnly: true),
            'truncated' => $tree['truncated'],
            'initialPersonId' => (string) $request->query('person', ''),
        ]);
    }
}
