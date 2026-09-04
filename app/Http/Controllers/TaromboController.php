<?php

namespace App\Http\Controllers;

use App\Models\FamilyTree;
use App\Models\FamilyTreeShare;
use App\Models\IdentityRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboStatisticsService;
use App\Services\TaromboTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedMargaId, $selectedTreePeople, $margaTree] = $this->treeData($request);

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
        ]);
    }

    /**
     * Show a single tarombo view without the dashboard layout (full screen).
     */
    public function fullscreen(Request $request, string $view): Response
    {
        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedMargaId, $selectedTreePeople, $margaTree] = $this->treeData($request);

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
        ]);
    }

    /**
     * Build the scoped tarombo rows and marga legend for the current user.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>, 3: array<string, mixed>, 4: array<int, array<string, mixed>>, 5: int|null, 6: int|null, 7: array<int, array<string, mixed>>, 8: array<string, mixed>|null}
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

        $direction = $request->string('marga_direction', 'lower')->toString();
        abort_if(
            $selectedMarga !== null && ! in_array($direction, ['upper', 'lower'], true),
            404,
        );

        $selectedFamilyTreeId = $selectedFamilyTree?->id;
        $selectedMargaId = $selectedMarga?->id;
        $selectedTreePeople = match (true) {
            $selectedFamilyTree instanceof FamilyTree => $service->rowsForFamilyTree($selectedFamilyTree),
            $selectedMarga instanceof Marga => $this->rowsForMarga($service, $selectedMarga, $direction),
            default => [],
        };
        $rows = $selectedTreePeople;
        $visiblePersonIds = collect($rows)->pluck('id');

        $alternativeTrees = $accountFamilyTrees
            ->filter(fn (FamilyTree $tree) => $tree->based_on_id !== null
                && $visiblePersonIds->contains((string) $tree->root_person_id))
            ->map(function (FamilyTree $tree) use ($service): ?array {
                $people = collect($service->rowsForFamilyTree($tree));
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
            'identityPersonId' => (string) $selectedMarga->identity_person_id,
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
                    'group' => 'account',
                ])
                ->concat($approvedMargas->map(fn (Marga $marga) => [
                    'id' => $marga->id,
                    'value' => 'marga:'.$marga->id,
                    'name' => 'Keluarga '.($marga->identityPerson?->name ?? $marga->name),
                    'rootName' => $marga->identityPerson?->name ?? $marga->name,
                    'group' => 'marga',
                ]))
                ->values()
                ->all(),
            $selectedFamilyTreeId,
            $selectedMargaId,
            $selectedTreePeople,
            $margaTree,
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, FamilyTree> */
    private function accountFamilyTrees(User $user): \Illuminate\Database\Eloquent\Collection
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

    /** @return \Illuminate\Database\Eloquent\Collection<int, Marga> */
    private function approvedMargas(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $margaIds = $user->isStaff()
            ? null
            : ($user->isContributor() ? $user->accessibleMargaIds() : $user->approvedMargaAccessIds());

        if (! $user->isStaff() && $margaIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return Marga::query()
            ->whereNotNull('identity_person_id')
            ->when($margaIds !== null, fn (Builder $query) => $query->whereKey($margaIds))
            ->with('identityPerson:id,name,father_id,marga_id')
            ->orderBy('name')
            ->get(['id', 'name', 'identity_person_id']);
    }

    /** @return array<int, array<string, mixed>> */
    private function rowsForMarga(TaromboTreeService $service, Marga $marga, string $direction): array
    {
        $identity = $marga->identityPerson;
        abort_if($identity === null, 404, 'Identitas marga belum dipilih.');

        $identityRows = collect(
            $direction === 'upper'
                ? $service->rowsForPersonWithAncestors($identity)
                : $service->rowsForPerson(
                    $identity,
                    maxDepth: (int) config('tarombo.public_max_depth'),
                    maxNodes: (int) config('tarombo.public_max_nodes'),
                ),
        );

        return $identityRows
            ->when($direction === 'lower', fn (Collection $rows) => $rows->merge(
                $service->rows(
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
