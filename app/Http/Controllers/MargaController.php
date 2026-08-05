<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMargaRequest;
use App\Http\Requests\UpdateMargaRequest;
use App\Models\Marga;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MargaController extends Controller
{
    /**
     * List all marga with their member count.
     */
    public function index(): Response
    {
        $margas = Marga::query()
            ->withCount('people')
            ->orderBy('people_count', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
                'description' => $marga->description,
                'color' => $marga->color,
                'people_count' => $marga->people_count,
            ]);

        return Inertia::render('marga/index', [
            'margas' => $margas,
        ]);
    }

    /**
     * Show the public marga page.
     */
    public function public(): Response
    {
        $margas = Marga::query()
            ->withCount('people')
            ->orderByDesc('people_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color,
                'count' => $marga->people_count,
            ]);

        return Inertia::render('marga/public', [
            'margas' => $margas,
            'stats' => [
                'totalMargas' => Marga::count(),
                'totalPeople' => Person::count(),
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
     * Store a newly created marga.
     */
    public function store(StoreMargaRequest $request): RedirectResponse
    {
        Marga::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marga berhasil ditambahkan.')]);

        return to_route('marga.index');
    }

    /**
     * Update the specified marga.
     */
    public function update(UpdateMargaRequest $request, Marga $marga): RedirectResponse
    {
        $marga->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marga berhasil diperbarui.')]);

        return to_route('marga.index');
    }

    /**
     * Remove the specified marga.
     */
    public function destroy(Marga $marga): RedirectResponse
    {
        $marga->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marga berhasil dihapus.')]);

        return to_route('marga.index');
    }
}
