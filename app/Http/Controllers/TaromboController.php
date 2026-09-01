<?php

namespace App\Http\Controllers;

use App\Models\ContributionRequest;
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

        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedTreePeople] = $this->treeData($request);

        return Inertia::render('tarombo/index', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
            'identity' => $identity,
            'familyTreeOptions' => $familyTreeOptions,
            'selectedFamilyTreeId' => $selectedFamilyTreeId,
            'selectedTreePeople' => $selectedTreePeople,
        ]);
    }

    /**
     * Show a single tarombo view without the dashboard layout (full screen).
     */
    public function fullscreen(Request $request, string $view): Response
    {
        [$people, $margas, $alternativeTrees, $identity, $familyTreeOptions, $selectedFamilyTreeId, $selectedTreePeople] = $this->treeData($request);
        $service = app(TaromboTreeService::class);

        $margaTree = null;

        if ($request->filled('marga_id') || $request->filled('marga_direction')) {
            $requestedMargaId = $request->integer('marga_id');
            abort_unless(
                $request->user()->isStaff()
                || ($request->user()->isContributor()
                    && $request->user()->accessibleMargaIds()->contains($requestedMargaId))
                || (! $request->user()->isContributor()
                    && $request->user()->approvedMargaAccessIds()->contains($requestedMargaId)),
                403,
            );

            $direction = $request->string('marga_direction')->toString();
            abort_unless(in_array($direction, ['upper', 'lower'], true), 404);

            $marga = Marga::query()->with('identityPerson')->findOrFail($requestedMargaId);
            abort_if($marga->identity_person_id === null || $marga->identityPerson === null, 404, 'Identitas marga belum dipilih.');

            // The normal scoped payload can omit the marga identity for a
            // non-staff account. A marga tree must always contain its own
            // identity and the branch required by the selected direction,
            // otherwise the React tree cannot resolve its visual root/path.
            $identityRows = collect(
                $direction === 'upper'
                    ? $service->rowsForPersonWithAncestors($marga->identityPerson)
                    : $service->rowsForPerson($marga->identityPerson),
            );
            $people = collect($people)
                ->merge($identityRows)
                ->unique('id')
                ->values()
                ->all();

            $margaTree = [
                'margaName' => $marga->name,
                'identityPersonId' => (string) $marga->identity_person_id,
                'direction' => $direction,
            ];
        }

        return Inertia::render('tarombo/fullscreen', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
            'view' => $view,
            'identity' => $identity,
            'initialPersonId' => $request->query('person'),
            'familyTreeOptions' => $familyTreeOptions,
            'selectedFamilyTreeId' => $selectedFamilyTreeId,
            'selectedTreePeople' => $selectedTreePeople,
            'margaTree' => $margaTree,
        ]);
    }

    /**
     * Build the scoped tarombo rows and marga legend for the current user.
     *
     * @return array{0: mixed, 1: mixed, 2: mixed, 3: array<string, mixed>, 4: array<int, array<string, mixed>>, 5: int|null, 6: array<int, array<string, mixed>>|null}
     */
    private function treeData(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('currentPerson');
        $isStaff = $user->isStaff();
        $service = app(TaromboTreeService::class);
        $accessibleMargaIds = $isStaff || $user->isContributor()
            ? $user->accessibleMargaIds()
            : $user->accessibleMargaIds()
                ->merge($user->approvedMargaAccessIds())
                ->unique()
                ->values();
        $allowedPersonIds = $isStaff
            ? null
            : ($accessibleMargaIds->isNotEmpty()
                ? $this->personIdsForMargaAndFemaleBranches($accessibleMargaIds)
                : collect());

        $rows = $service->rows(
            Person::query()
                ->when($allowedPersonIds !== null, fn (Builder $query) => $query->whereKey($allowedPersonIds))
                ->orderBy('id'),
        );
        $selectableFamilyTrees = $this->selectableFamilyTrees($user);
        $selectedFamilyTreeId = $request->filled('family_tree')
            ? $request->integer('family_tree')
            : null;
        $selectedFamilyTree = $selectedFamilyTreeId !== null
            ? $selectableFamilyTrees->firstWhere('id', $selectedFamilyTreeId)
            : null;

        abort_if(
            $selectedFamilyTreeId !== null && $selectedFamilyTree === null,
            403,
            'Anda tidak memiliki akses ke silsilah yang dipilih.',
        );
        $selectedTreePeople = $selectedFamilyTree instanceof FamilyTree
            ? $service->rowsForFamilyTree($selectedFamilyTree)
            : null;

        $visiblePersonIds = collect($rows)->pluck('id')->map(fn (string $id) => (int) $id);
        $alternativeTrees = FamilyTree::query()
            ->whereNotNull('based_on_id')
            ->whereIn('root_person_id', $visiblePersonIds)
            ->when(! $isStaff, fn (Builder $query) => $query->where(
                fn (Builder $access) => $access
                    ->whereBelongsTo($user)
                    ->orWhereHas('contributionRequests', fn (Builder $requests) => $requests
                        ->where('status', ContributionRequest::STATUS_APPROVED)
                        ->whereHas('matchedFather', fn (Builder $father) => $father
                            ->where('marga_id', $user->marga_id))),
            ))
            ->orderBy('root_person_id')
            ->orderBy('created_at')
            ->get()
            ->map(function (FamilyTree $tree) use ($service, $allowedPersonIds): ?array {
                $people = collect($service->rowsForFamilyTree($tree))
                    ->when(
                        $allowedPersonIds !== null,
                        fn (Collection $rows) => $rows->whereIn(
                            'id',
                            $allowedPersonIds->map(fn (int $id) => (string) $id),
                        ),
                    );
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

        $identityRequest = IdentityRequest::query()
            ->with(['person:id,name', 'reviewer:id,name'])
            ->where('requester_id', $user->id)
            ->latest()
            ->first();

        return [
            $rows,
            $service->margas($isStaff ? null : $accessibleMargaIds->all()),
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
            $selectableFamilyTrees
                ->map(fn (FamilyTree $tree) => [
                    'id' => $tree->id,
                    'name' => $tree->name ?? $tree->rootPerson?->name ?? 'Silsilah',
                    'rootName' => $tree->rootPerson?->name ?? 'Akar belum ditentukan',
                    'group' => $tree->approved_for_selection ? 'marga' : 'account',
                ])
                ->values()
                ->all(),
            $selectedFamilyTreeId,
            $selectedTreePeople,
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, FamilyTree> */
    private function selectableFamilyTrees(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return FamilyTree::query()
            ->whereNotNull('root_person_id')
            ->where(function (Builder $selectable): void {
                $selectable
                    ->whereNotNull('based_on_id')
                    ->orWhereHas('rootPerson', fn (Builder $root) => $root
                        ->whereNull('father_id')
                        ->whereNull('mother_id')
                        ->whereDoesntHave('familyTreeNodes', fn (Builder $nodes) => $nodes
                            ->where(fn (Builder $parentage) => $parentage
                                ->whereNotNull('father_node_id')
                                ->orWhereNotNull('mother_node_id'))));
            })
            ->when(! $user->isAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $access) use ($user): void {
                    $access->whereBelongsTo($user)
                        ->orWhereHas('shares', fn (Builder $shares) => $shares
                            ->whereBelongsTo($user, 'recipient')
                            ->where('status', FamilyTreeShare::STATUS_ACCEPTED));

                    if ($user->isContributor()) {
                        $access->orWhereHas('contributionRequests', fn (Builder $requests) => $requests
                            ->where('status', ContributionRequest::STATUS_APPROVED)
                            ->whereHas('matchedFather', fn (Builder $father) => $father
                                ->where('marga_id', $user->marga_id)));
                    } elseif (! $user->isStaff() && ! $user->isContributor()) {
                        $access->orWhereHas('contributionRequests', fn (Builder $requests) => $requests
                            ->where('status', ContributionRequest::STATUS_APPROVED)
                            ->whereHas('matchedFather', fn (Builder $father) => $father
                                ->whereIn('marga_id', $user->approvedMargaAccessIds())));
                    }

                    if (! $user->isStaff()) {
                        $margaIds = $user->isContributor()
                            ? $user->accessibleMargaIds()
                            : $user->approvedMargaAccessIds();

                        $access->orWhere(function (Builder $adminTree) use ($margaIds): void {
                            $adminTree
                                ->whereHas('user', fn (Builder $owner) => $owner->whereIn('role', ['admin', 'subadmin']))
                                ->whereHas('nodes.person', fn (Builder $person) => $person->whereIn('marga_id', $margaIds));
                        });
                    }
                });
            })
            ->with(['rootPerson:id,name'])
            ->withExists(['contributionRequests as approved_for_selection' => function (Builder $requests) use ($user): void {
                $requests->where('status', ContributionRequest::STATUS_APPROVED)
                    ->when(
                        ! $user->isAdmin() && ! $user->isContributor(),
                        fn (Builder $query) => $query->whereHas('matchedFather', fn (Builder $father) => $father
                            ->whereIn('marga_id', $user->approvedMargaAccessIds())),
                    )
                    ->when(
                        $user->isContributor(),
                        fn (Builder $query) => $query->whereHas('matchedFather', fn (Builder $father) => $father
                            ->where('marga_id', $user->marga_id)),
                    );
            }])
            ->latest('updated_at')
            ->get(['id', 'user_id', 'root_person_id', 'name', 'updated_at']);
    }

    /** @return Collection<int, int> */
    private function personIdsForMargaAndFemaleBranches(Collection $margaIds): Collection
    {
        $visibleIds = Person::query()
            ->whereIn('marga_id', $margaIds)
            ->pluck('id')
            ->map(fn (int $id) => $id);
        $frontier = Person::query()
            ->whereIn('marga_id', $margaIds)
            ->where('gender', 'P')
            ->pluck('id');

        while ($frontier->isNotEmpty()) {
            $children = Person::query()
                ->whereIn('father_id', $frontier)
                ->whereNotIn('id', $visibleIds)
                ->pluck('id');

            if ($children->isEmpty()) {
                break;
            }

            $visibleIds = $visibleIds->merge($children)->unique()->values();
            $frontier = $children;
        }

        return $visibleIds->values();
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
