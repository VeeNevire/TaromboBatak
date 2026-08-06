<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Services\FamilyEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
            ->with(['marga', 'father'])
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
                'parent' => $person->father?->name,
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
     * Show the create family entry form.
     */
    public function create(): Response
    {
        return Inertia::render('people/form', [
            'person' => null,
            'margas' => $this->margaOptions(),
            'nameSuggestions' => $this->nameSuggestions(),
        ]);
    }

    /**
     * Store a whole family entry (father, mother, and sibling rows).
     */
    public function store(StorePersonRequest $request): RedirectResponse
    {
        app(FamilyEntryService::class)->save($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Keluarga berhasil ditambahkan.')]);

        return to_route('people.index');
    }

    /**
     * Show the family entry (jejak keluarga) reached from the Info button.
     */
    public function show(Person $person): Response
    {
        return Inertia::render('people/show', [
            'person' => $this->familyPayload($person),
            'margas' => $this->margaOptions(),
            'nameSuggestions' => $this->nameSuggestions(),
        ]);
    }

    /**
     * Show the edit family entry form.
     */
    public function edit(Person $person): Response
    {
        return Inertia::render('people/form', [
            'person' => $this->familyPayload($person),
            'margas' => $this->margaOptions(),
            'nameSuggestions' => $this->nameSuggestions(),
        ]);
    }

    /**
     * JSON preview of the close family ("silsilah keluarga") for a person:
     * the ayah & ibu branches (with their parents and siblings) and the
     * children row where the focused person is highlighted by birth order.
     */
    public function preview(Person $person): JsonResponse
    {
        return response()->json($this->familyPreviewPayload($person));
    }

    /**
     * Show the close family ("silsilah keluarga") as a full page.
     */
    public function silsilah(Person $person): Response
    {
        return Inertia::render('people/silsilah', $this->familyPreviewPayload($person));
    }

    /**
     * Build the "silsilah keluarga" payload: the ayah & ibu branches
     * (with their parents and siblings) and the children row where the
     * focused person is highlighted by birth order.
     *
     * @return array<string, mixed>
     */
    protected function familyPreviewPayload(Person $person): array
    {
        $people = Person::query()->with('marga')->get();
        $byId = $people->keyBy('id');

        $father = $person->father_id !== null ? ($byId[$person->father_id] ?? null) : null;
        $mother = $person->mother_id !== null ? ($byId[$person->mother_id] ?? null) : null;

        $children = $people
            ->filter(fn (Person $row) => $row->father_id === $person->father_id)
            ->sortBy('birth_order')
            ->values();

        $colorsByMarga = [];
        $fallbackIndex = 0;

        foreach ($people as $row) {
            $name = $row->marga->name ?? null;

            if ($name !== null && ! isset($colorsByMarga[$name])) {
                $colorsByMarga[$name] = $row->marga->color ?? $this->fallbackColor($fallbackIndex++);
            }
        }

        $referenced = collect([$person, $father, $mother])
            ->filter()
            ->merge($children);

        $margas = $referenced
            ->pluck('marga.name')
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $name) => [
                'name' => $name,
                'color' => $colorsByMarga[$name] ?? $this->fallbackColor(0),
            ])
            ->all();

        return [
            'centerId' => (string) $person->id,
            'person' => $this->previewNode($person, $byId),
            'father' => $father ? $this->previewNode($father, $byId) : null,
            'mother' => $mother ? $this->previewNode($mother, $byId) : null,
            'children' => $children
                ->map(fn (Person $row) => $this->previewBasic($row))
                ->all(),
            'margas' => $margas,
        ];
    }

    /**
     * Build a preview node with the person's own parents and siblings.
     *
     * @param  Collection<int, Person>  $byId
     * @return array<string, mixed>
     */
    protected function previewNode(Person $person, $byId): array
    {
        $grandfather = $person->father_id !== null ? ($byId[$person->father_id] ?? null) : null;
        $grandmother = $person->mother_id !== null ? ($byId[$person->mother_id] ?? null) : null;

        return [
            ...$this->previewBasic($person),
            'parents' => [
                $grandfather ? $this->previewBasic($grandfather) : null,
                $grandmother ? $this->previewBasic($grandmother) : null,
            ],
            'siblings' => $this->siblingsOf($person, $byId),
        ];
    }

    /**
     * @param  Collection<int, Person>  $byId
     * @return array<int, array<string, mixed>>
     */
    protected function siblingsOf(Person $person, $byId): array
    {
        return $byId
            ->filter(fn (Person $row) => $row->id !== $person->id && $row->father_id === $person->father_id)
            ->sortBy('birth_order')
            ->values()
            ->map(fn (Person $row) => $this->previewBasic($row))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewBasic(Person $person): array
    {
        return [
            'id' => (string) $person->id,
            'name' => $person->name,
            'alias' => $person->alias,
            'marga' => $person->marga?->name ?? 'Batak',
            'margaColor' => $person->marga?->color,
            'birthYear' => $person->birth_year,
            'birthOrder' => $person->birth_order,
            'image' => $person->image,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function fallbackColor(int $index): string
    {
        $colors = ['#b34b1e', '#2a527c', '#3e6b48', '#f59e0b', '#7c3aed', '#0e7490'];

        return $colors[$index % count($colors)];
    }

    /**
     * Update the specified person along with their whole family.
     */
    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $data = array_merge($request->validated(), ['id' => $person->id]);

        app(FamilyEntryService::class)->save($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Jejak keluarga berhasil diperbarui.')]);

        return to_route('people.show', $person);
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
     * Build the family payload for the form (focused person + parents + siblings).
     *
     * @return array<string, mixed>
     */
    protected function familyPayload(Person $person): array
    {
        $siblings = $person->father_id !== null
            ? Person::query()
                ->where('father_id', $person->father_id)
                ->orderBy('birth_order')
                ->get()
            : collect([$person]);

        if (! $siblings->contains('id', $person->id)) {
            $siblings = $siblings->push($person)->sortBy('birth_order')->values();
        }

        return [
            'id' => $person->id,
            'name' => $person->name,
            'gender' => $person->gender,
            'alias' => $person->alias,
            'marga_id' => $person->marga_id,
            'father_id' => $person->father_id,
            'mother_id' => $person->mother_id,
            'birth_order' => $person->birth_order,
            'sibling_count' => $person->sibling_count,
            'nomor' => $person->nomor,
            'birth_year' => $person->birth_year,
            'death_year' => $person->death_year,
            'image' => $person->image,
            'bio' => $person->bio,
            'spouse' => $person->spouse,
            'spouse_marga' => $person->spouse_marga,
            'father' => $person->father
                ? [
                    'id' => $person->father->id,
                    'name' => $person->father->name,
                    'birth_year' => $person->father->birth_year,
                    'death_year' => $person->father->death_year,
                ]
                : null,
            'mother' => $person->mother
                ? [
                    'id' => $person->mother->id,
                    'name' => $person->mother->name,
                    'birth_year' => $person->mother->birth_year,
                    'death_year' => $person->mother->death_year,
                ]
                : null,
            'children' => $siblings
                ->map(fn (Person $sibling) => [
                    'id' => $sibling->id,
                    'name' => $sibling->name,
                    'gender' => $sibling->gender,
                    'spouse' => $sibling->spouse,
                    'spouse_marga' => $sibling->spouse_marga,
                    'birth_order' => $sibling->birth_order,
                    'nomor' => $sibling->nomor,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Existing person names used for the autocomplete / reuse suggestions.
     *
     * @return array<int, string>
     */
    protected function nameSuggestions(?int $margaId = null): array
    {
        return Person::query()
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->whereNotNull('name')
            ->where('name', '!=', 'N/A')
            ->orderBy('name')
            ->limit(300)
            ->distinct()
            ->pluck('name')
            ->all();
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
}