<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaromboTreeSettingsRequest;
use App\Models\ContactRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeShare;
use App\Models\IdentityRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeFamilyNameService;
use App\Services\FamilyTreeInheritanceService;
use App\Services\TaromboStatisticsService;
use App\Services\TaromboTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedMargaId, $selectedTreePeople, $margaTree, $accountTreePersonIds] = $this->treeData($request);

        return Inertia::render('tarombo/index', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
            'identity' => $identity,
            'familyTreeOptions' => $familyTreeOptions,
            'selectedFamilyTreeId' => $selectedFamilyTreeId,
            'selectedMargaId' => $selectedMargaId,
            'selectedTreePeople' => $selectedTreePeople,
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
        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedMargaId, $selectedTreePeople, $margaTree, $accountTreePersonIds] = $this->treeData($request);

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
            'selectedTreePeople' => $selectedTreePeople,
            'margaTree' => $margaTree,
            'accountTreePersonIds' => $accountTreePersonIds,
            'familyName' => $this->familyName($selectedFamilyTreeId, $margaTree),
            'treeSettings' => $request->user()?->tarombo_tree_settings,
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
        $request->user()->update(['tarombo_tree_settings' => null]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tampilan pohon dikembalikan ke bawaan.',
        ]);

        return back();
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
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>, 3: array<string, mixed>, 4: array<int, array<string, mixed>>, 5: int|null, 6: int|null, 7: array<int, array<string, mixed>>, 8: array<string, mixed>|null, 9: array<int, string>}
     */
    private function treeData(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('currentPerson');
        $service = app(TaromboTreeService::class);
        $accountFamilyTrees = $this->accountFamilyTrees($user);
        $accountTreePersonIds = $this->accountTreePersonIds($accountFamilyTrees);
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

        $direction = $request->string('marga_direction', 'lower')->toString();
        abort_if(
            $selectedMarga !== null && ! in_array($direction, ['upper', 'lower'], true),
            404,
        );

        $selectedFamilyTreeId = $selectedFamilyTree?->id;
        $selectedMargaId = $selectedMarga?->id;
        $selectedTreePeople = match (true) {
            $selectedFamilyTree instanceof FamilyTree => $service->rowsForFamilyTree($selectedFamilyTree),
            $selectedMarga instanceof Marga => $service->rowsForMarga(
                $selectedMarga,
                $direction,
                maxDepth: (int) config('tarombo.dashboard_max_depth'),
                maxNodes: (int) config('tarombo.dashboard_max_nodes'),
            ),
            default => [],
        };
        $selectedTreePeople = $this->withContactState($selectedTreePeople, $user);
        $rows = $selectedTreePeople;
        $visiblePersonIds = collect($rows)->pluck('id');

        $alternativeTrees = $accountFamilyTrees
            ->filter(fn (FamilyTree $tree) => $tree->based_on_id !== null
                && $visiblePersonIds->contains((string) $tree->root_person_id))
            ->map(function (FamilyTree $tree) use ($service, $user): ?array {
                $people = collect($this->withContactState(
                    $service->rowsForFamilyTree($tree),
                    $user,
                ));
                $rootId = (string) $tree->root_person_id;

                if (! $people->contains('id', $rootId)) {
                    return null;
                }

                $childrenByParent = $people
                    ->filter(fn (array $person) => $person['parentId'] !== null)
                    ->groupBy('parentId');
                $connectedIds = collect();
                $queue = [$rootId];

                while ($queue !== []) {
                    $personId = array_shift($queue);

                    if ($connectedIds->contains($personId)) {
                        continue;
                    }

                    $connectedIds->push($personId);
                    array_push(
                        $queue,
                        ...$childrenByParent->get($personId, collect())->pluck('id'),
                    );
                }

                $people = $people->whereIn('id', $connectedIds)->values();

                return [
                    'id' => $tree->id,
                    'name' => $tree->name ?? 'Versi alternatif',
                    'rootPersonId' => $rootId,
                    'people' => $people->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $margaTree = $selectedMarga instanceof Marga ? [
            'margaName' => $selectedMarga->name,
            'identityPersonId' => $selectedMarga->identity_person_id !== null
                ? (string) $selectedMarga->identity_person_id
                : null,
            'direction' => $direction,
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
            $selectedTreePeople,
            $margaTree,
            $accountTreePersonIds,
        ];
    }

    /**
     * Person ids that appear in any of the account's Silsilah Milik Akun.
     * Resolved through inheritance so versions based on another tree are included.
     *
     * @param  Collection<int, FamilyTree>  $accountFamilyTrees
     * @return array<int, string>
     */
    private function accountTreePersonIds(Collection $accountFamilyTrees): array
    {
        $inheritance = app(FamilyTreeInheritanceService::class);

        return $accountFamilyTrees
            ->flatMap(fn (FamilyTree $tree) => $inheritance->nodesFor($tree)->pluck('person_id'))
            ->map(fn (int|string $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, FamilyTree> */
    private function accountFamilyTrees(User $user): Collection
    {
        return FamilyTree::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where(
                fn (Builder $access) => $access
                    ->whereBelongsTo($user)
                    ->orWhereHas('shares', fn (Builder $shares) => $shares
                        ->whereBelongsTo($user, 'recipient')
                        ->where('status', FamilyTreeShare::STATUS_ACCEPTED)),
            ))
            ->whereNotNull('root_person_id')
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
