<?php

namespace App\Http\Controllers;

use App\Models\Marga;
use App\Models\Person;
use App\Services\TaromboStatisticsService;
use App\Services\TaromboTreeService;
use Illuminate\Database\Eloquent\Builder;
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
        $user = $request->user();
        $isStaff = $user->isStaff();
        $service = app(TaromboTreeService::class);

        $rows = $service->rows(
            Person::query()
                ->when(! $isStaff, fn (Builder $query) => $query->where('marga_id', $user->marga_id))
                ->orderBy('id'),
        );

        $margas = $service->margas($isStaff ? null : $user->marga_id);

        return Inertia::render('tarombo/index', [
            'people' => $rows,
            'margas' => $margas,
        ]);
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
}
