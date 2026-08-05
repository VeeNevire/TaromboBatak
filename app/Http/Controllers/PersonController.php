<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Marga;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    /**
     * List people with search and marga filter.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $people = Person::query()
            ->with(['marga', 'parent'])
            ->when(! $isAdmin, fn ($query) => $query->where('marga_id', $user->marga_id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('alias', 'like', '%'.$request->string('search').'%');
                });
            })
            ->when($request->filled('marga_id'), function ($query) use ($request) {
                $query->where('marga_id', $request->integer('marga_id'));
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga?->name,
                'marga_color' => $person->marga?->color,
                'parent' => $person->parent?->name,
                'birth_year' => $person->birth_year,
                'created_at' => $person->created_at?->format('d M Y'),
            ]);

        $margas = Marga::query()
            ->when(! $isAdmin, fn ($query) => $query->where('id', $user->marga_id))
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
            ]);

        return Inertia::render('people/index', [
            'people' => $people,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'marga_id' => $request->input('marga_id'),
            ],
            'margas' => $margas,
            'canManage' => $isAdmin,
        ]);
    }

    /**
     * Show the create person form.
     */
    public function create(): Response
    {
        return Inertia::render('people/form', [
            'person' => null,
            'margas' => $this->margaOptions(),
            'parents' => $this->parentOptions(),
        ]);
    }

    /**
     * Store a newly created person.
     */
    public function store(StorePersonRequest $request): RedirectResponse
    {
        Person::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Anggota berhasil ditambahkan.')]);

        return to_route('people.index');
    }

    /**
     * Show the edit person form.
     */
    public function edit(Person $person): Response
    {
        return Inertia::render('people/form', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga_id' => $person->marga_id,
                'parent_id' => $person->parent_id,
                'birth_year' => $person->birth_year,
                'death_year' => $person->death_year,
                'image' => $person->image,
                'bio' => $person->bio,
            ],
            'margas' => $this->margaOptions(),
            'parents' => $this->parentOptions($person->id),
        ]);
    }

    /**
     * Update the specified person.
     */
    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Anggota berhasil diperbarui.')]);

        return to_route('people.index');
    }

    /**
     * Remove the specified person.
     */
    public function destroy(Person $person): RedirectResponse
    {
        $person->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Anggota berhasil dihapus.')]);

        return to_route('people.index');
    }

    /**
     * Marga options for the form select.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function margaOptions(): array
    {
        return Marga::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
            ])
            ->all();
    }

    /**
     * Parent options for the form select (excluding the person itself).
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function parentOptions(?int $exceptId = null): array
    {
        return Person::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
            ])
            ->all();
    }
}
