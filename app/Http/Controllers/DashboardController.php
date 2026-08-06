<?php

namespace App\Http\Controllers;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with tarombo statistics.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $peopleQuery = Person::query()
            ->when(! $isAdmin, fn (Builder $query) => $query->where('marga_id', $user->marga_id));

        $totalPeople = (clone $peopleQuery)->count();
        $totalMargas = $isAdmin
            ? Marga::count()
            : ($user->marga_id ? 1 : 0);
        $totalGenerations = $this->maxGenerationDepth($peopleQuery);

        $margaDistribution = Marga::query()
            ->when(! $isAdmin, fn (Builder $query) => $query->where('id', $user->marga_id))
            ->withCount('people')
            ->orderByDesc('people_count')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color,
                'count' => $marga->people_count,
            ]);

        $recentPeople = (clone $peopleQuery)
            ->with('marga')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga?->name,
                'marga_color' => $person->marga?->color,
                'birth_year' => $person->birth_year,
                'created_at' => $person->created_at?->format('d M Y'),
            ]);

        $roots = (clone $peopleQuery)
            ->whereNull('father_id')
            ->limit(4)
            ->pluck('name');

        return Inertia::render('dashboard', [
            'stats' => [
                'totalPeople' => $totalPeople,
                'totalMargas' => $totalMargas,
                'totalGenerations' => $totalGenerations,
            ],
            'margaDistribution' => $margaDistribution,
            'recentPeople' => $recentPeople,
            'rootNames' => $roots,
        ]);
    }

    /**
     * Compute the maximum generation depth starting from root ancestors.
     *
     * @param  Builder<Person>  $query
     */
    protected function maxGenerationDepth(Builder $query): int
    {
        $parents = (clone $query)
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
