<?php

namespace App\Http\Controllers;

use App\Models\Marga;
use App\Models\Person;
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

        $rows = $this->buildRows(
            Person::query()
                ->when(! $isAdmin, fn (Builder $query) => $query->where('marga_id', $user->marga_id))
                ->orderBy('id'),
        );

        $margas = $this->buildMargas($isAdmin ? null : $user->marga_id);

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
        return Inertia::render('tarombo/public', [
            'people' => $this->buildRows(Person::query()->orderBy('id')),
            'margas' => $this->buildMargas(),
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
            ->select('id', 'parent_id')
            ->get()
            ->mapWithKeys(fn (Person $person) => [$person->id => $person->parent_id]);

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

    /**
     * @param  Builder<Person>  $query
     * @return array<int, array<string, mixed>>
     */
    protected function buildRows(Builder $query): array
    {
        return $query
            ->with(['marga', 'children'])
            ->get()
            ->map(fn (Person $person) => [
                'id' => (string) $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'parentId' => $person->parent_id !== null ? (string) $person->parent_id : null,
                'birthYear' => $person->birth_year,
                'image' => $person->image,
                'bio' => $person->bio,
                'childrenNames' => $person->children
                    ->sortBy('birth_year')
                    ->map(fn (Person $child) => $child->birth_year
                        ? $child->name.' ('.$child->birth_year.')'
                        : $child->name)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, color: string}>
     */
    protected function buildMargas(?int $margaId = null): array
    {
        return Marga::query()
            ->when($margaId !== null, fn (Builder $query) => $query->where('id', $margaId))
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color ?? '#b34b1e',
            ])
            ->all();
    }
}
