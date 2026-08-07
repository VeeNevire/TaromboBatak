<?php

namespace App\Http\Controllers;

use App\Models\Marga;
use App\Models\Person;
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
        $isAdmin = $user->isAdmin();
        $service = app(TaromboTreeService::class);

        $rows = $service->rows(
            Person::query()
                ->when(! $isAdmin, fn (Builder $query) => $query->where('marga_id', $user->marga_id))
                ->orderBy('id'),
        );

        $margas = $service->margas($isAdmin ? null : $user->marga_id);

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

        return Inertia::render('tarombo/public', [
            'people' => $service->rows(Person::query()->orderBy('id')),
            'margas' => $service->margas(),
            'stats' => [
                'totalPeople' => Person::count(),
                'totalMargas' => Marga::count(),
                'totalGenerations' => $this->maxGenerationDepth(),
            ],
        ]);
    }

    /**
     * Compute the maximum generation depth starting from root ancestors.
     */
    protected function maxGenerationDepth(): int
    {
        $parents = Person::query()
            ->select('id', 'father_id')
            ->get()
            ->mapWithKeys(fn (Person $person) => [$person->id => $person->father_id]);

        $depth = 0;

        foreach ($parents as $id => $_) {
            $count = 0;
            $current = $id;

            while (isset($parents[$current]) && $count < 1000) {
                $current = $parents[$current];
                $count++;
            }

            $depth = max($depth, $count);
        }

        return $depth + 1;
    }
}
