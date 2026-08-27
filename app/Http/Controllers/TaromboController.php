<?php

namespace App\Http\Controllers;

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\Marga;
use App\Models\Person;
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
        [$people, $margas, $alternativeTrees] = $this->treeData($request);

        return Inertia::render('tarombo/index', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
        ]);
    }

    /**
     * Show a single tarombo view without the dashboard layout (full screen).
     */
    public function fullscreen(Request $request, string $view): Response
    {
        [$people, $margas, $alternativeTrees] = $this->treeData($request);

        return Inertia::render('tarombo/fullscreen', [
            'people' => $people,
            'margas' => $margas,
            'alternativeTrees' => $alternativeTrees,
            'view' => $view,
        ]);
    }

    /**
     * Build the scoped tarombo rows and marga legend for the current user.
     *
     * @return array{0: mixed, 1: mixed, 2: mixed}
     */
    private function treeData(Request $request): array
    {
        $user = $request->user();
        $isStaff = $user->isStaff();
        $service = app(TaromboTreeService::class);
        $allowedPersonIds = $isStaff
            ? null
            : ($user->marga_id !== null
                ? $this->personIdsForMargaAndFemaleBranches($user->marga_id)
                : collect());

        $rows = $service->rows(
            Person::query()
                ->when($allowedPersonIds !== null, fn (Builder $query) => $query->whereKey($allowedPersonIds))
                ->orderBy('id'),
        );

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

        return [
            $rows,
            $service->margas($isStaff ? null : $user->marga_id),
            $alternativeTrees,
        ];
    }

    /** @return Collection<int, int> */
    private function personIdsForMargaAndFemaleBranches(int $margaId): Collection
    {
        $visibleIds = Person::query()
            ->where('marga_id', $margaId)
            ->pluck('id')
            ->map(fn (int $id) => $id);
        $frontier = Person::query()
            ->where('marga_id', $margaId)
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
