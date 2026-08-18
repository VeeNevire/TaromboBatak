<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;
use App\Services\FamilyEntryService;
use App\Services\TaromboTreeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
        $isStaff = $user->isStaff();

        $people = Person::query()
            ->with(['marga', 'father'])
            ->when(! $isStaff, fn ($query) => $query->where('marga_id', $user->marga_id))
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
                'chain' => $person->chain,
                'pending' => (bool) $person->pending_father,
                'created_at' => $person->created_at?->format('d M Y'),
                'editable' => $isStaff || ($person->created_by !== null && $person->created_by === $user->id),
            ]);

        $margas = Marga::query()
            ->when(! $isStaff, fn ($query) => $query->where('id', $user->marga_id))
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
            'canManage' => $isStaff,
            'hasMarga' => $isStaff || $user->marga_id !== null,
        ]);
    }

    /**
     * Show the create family entry form.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $isStaff = $user->isStaff();

        Gate::authorize('create', Person::class);

        return Inertia::render('people/form', [
            'person' => null,
            'margas' => $isStaff ? $this->margaOptions() : $this->margaOptionsForUser($user),
            'nameSuggestions' => $this->nameSuggestions($isStaff ? null : $user->marga_id),
            'lockedMarga' => $isStaff ? null : $this->lockedMarga($user),
            'lineage' => $this->createLineage($user, $isStaff),
            'canPublish' => $isStaff,
        ]);
    }

    /**
     * Roots of the user's marga (or all margas for staff) shown read-only on
     * the create form as the marga's lineage context.
     *
     * @return array<int, array{id: int, name: string, marga_id: int|null, marga: string|null, chain: string|null, children: array<int, array{id: int, name: string, gender: string|null, marga: string|null, chain: string|null, birth_order: int|null}>}>
     */
    protected function createLineage(User $user, bool $isStaff): array
    {
        return Person::query()
            ->whereNull('father_id')
            ->with([
                'marga',
                'children' => fn ($query) => $query->orderBy('birth_order'),
            ])
            ->when(! $isStaff && $user->marga_id !== null, fn ($query) => $query->where('marga_id', $user->marga_id))
            ->orderByRaw('chain IS NULL')
            ->orderByRaw('CAST(chain AS UNSIGNED)')
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'marga_id' => $person->marga_id,
                'marga' => $person->marga?->name,
                'chain' => $person->chain,
                'children' => $person->children
                    ->map(fn (Person $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'gender' => $child->gender,
                        'marga' => $child->marga?->name,
                        'chain' => $child->chain,
                        'birth_order' => $child->birth_order,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Store a whole family entry (father, mother, and sibling rows).
     */
    public function store(StorePersonRequest $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', Person::class);

        $validated = $request->validated();
        $validated['children'] = $request->input('children', []);
        $validated['ownChildren'] = $request->input('ownChildren', []);

        if ($user->isStaff()) {
            app(FamilyEntryService::class)->save($validated);
        } else {
            abort_unless($user->marga_id !== null, 403, 'Akun Anda belum memiliki marga.');

            app(FamilyEntryService::class)->save(
                $validated,
                forcedMargaId: $user->marga_id,
                createdBy: $user->id,
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Keluarga berhasil ditambahkan.')]);

        return to_route('people.index');
    }

    /**
     * Show the family entry (jejak keluarga) reached from the Info button.
     */
    public function show(Person $person): Response
    {
        Gate::authorize('view', $person);

        return Inertia::render('people/show', [
            'person' => $this->familyPayload($person),
            'margas' => $this->margaOptions(),
            'nameSuggestions' => $this->nameSuggestions(),
            'canPublish' => true,
        ]);
    }

    /**
     * Show the edit family entry form.
     */
    public function edit(Request $request, Person $person): Response
    {
        $user = $request->user();
        $isStaff = $user->isStaff();

        Gate::authorize('update', $person);

        return Inertia::render('people/form', [
            'person' => $this->familyPayload($person),
            'margas' => $isStaff ? $this->margaOptions() : $this->margaOptionsForUser($user),
            'nameSuggestions' => $this->nameSuggestions($isStaff ? null : $user->marga_id),
            'lockedMarga' => $isStaff ? null : $this->lockedMarga($user),
            'canPublish' => $isStaff,
        ]);
    }

    /**
     * JSON preview of the close family ("silsilah keluarga") for a person:
     * the ayah & ibu branches (with their parents and siblings) and the
     * children row where the focused person is highlighted by birth order.
     */
    public function preview(Person $person): JsonResponse
    {
        Gate::authorize('view', $person);

        return response()->json($this->familyPreviewPayload($person));
    }

    /**
     * Show the silsilah of a person as an interactive descendant tree that
     * can be re-rooted by clicking any person in the tree.
     */
    public function silsilah(Person $person): Response
    {
        Gate::authorize('view', $person);

        $service = app(TaromboTreeService::class);

        return Inertia::render('people/silsilah', [
            'people' => $service->rowsForPerson($person),
            'centerPersonId' => (string) $person->id,
            'person' => [
                'id' => (string) $person->id,
                'name' => $person->name,
                'alias' => $person->alias,
                'marga' => $person->marga->name ?? 'Batak',
                'birthOrder' => $person->birth_order,
            ],
        ]);
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

        $father = $person->pending_father || $person->father_id === null
            ? null
            : ($byId[$person->father_id] ?? null);
        $mother = $person->mother_id !== null ? ($byId[$person->mother_id] ?? null) : null;

        $children = $person->pending_father
            ? collect([$person])
            : $people
                ->filter(fn (Person $row) => $row->father_id === $person->father_id && ! $row->pending_father)
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
     * @param  Collection<int|string, Person>  $byId
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
     * @param  Collection<int|string, Person>  $byId
     * @return array<int, array<string, mixed>>
     */
    protected function siblingsOf(Person $person, $byId): array
    {
        return $byId
            ->filter(fn (Person $row) => $row->id !== $person->id && $row->father_id === $person->father_id && ! $row->pending_father)
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
            'marga' => $person->marga->name ?? 'Batak',
            'margaColor' => $person->marga?->color,
            'birthYear' => $person->birth_year,
            'birthOrder' => $person->birth_order,
            'image' => $person->image,
        ];
    }

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
        $user = $request->user();
        $isStaff = $user->isStaff();

        $validated = $request->validated();
        $validated['children'] = $request->input('children', []);
        $validated['ownChildren'] = $request->input('ownChildren', []);
        $validated['id'] = $person->id;

        Gate::authorize('update', $person);

        if (! $isStaff) {
            app(FamilyEntryService::class)->save(
                $validated,
                forcedMargaId: $user->marga_id,
                createdBy: $user->id,
            );
        } else {
            app(FamilyEntryService::class)->save($validated);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Jejak keluarga berhasil diperbarui.')]);

        return to_route('people.show', $person);
    }

    /**
     * Remove the specified person.
     */
    public function destroy(Person $person): RedirectResponse
    {
        Gate::authorize('delete', $person);

        if (Person::query()
            ->where('father_id', $person->id)
            ->orWhere('mother_id', $person->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'person' => 'Anggota yang masih menjadi orang tua tidak dapat dihapus.',
            ]);
        }

        $father = $person->father;
        $person->delete();

        if ($father !== null) {
            app(ChainNumberingService::class)->recomputeFromAncestor($father);
        }

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
        if ($person->pending_father) {
            $siblings = collect([$person]);
            $lineageIds = [$person->id];
        } else {
            $siblings = $person->father_id !== null
                ? Person::query()
                    ->where('father_id', $person->father_id)
                    ->where(fn ($query) => $query
                        ->where('pending_father', false)
                        ->orWhere('id', $person->id))
                    ->orderBy('birth_order')
                    ->get()
                : collect([$person]);

            if (! $siblings->contains('id', $person->id)) {
                $siblings = $siblings->push($person)->sortBy('birth_order')->values();
            }

            $lineageIds = $person->lineage()->pluck('id')->push($person->id)->all();
        }

        $lineage = Person::query()
            ->whereIn('id', $lineageIds)
            ->with([
                'marga',
                'children' => fn ($query) => $query->orderBy('birth_order'),
            ])
            ->get()
            ->sortBy(fn (Person $row) => array_search($row->id, $lineageIds))
            ->values();

        return [
            'id' => $person->id,
            'name' => $person->name,
            'gender' => $person->gender,
            'alias' => $person->alias,
            'marga_id' => $person->marga_id,
            'father_id' => $person->pending_father ? null : $person->father_id,
            'mother_id' => $person->mother_id,
            'birth_order' => $person->birth_order,
            'sibling_count' => $person->sibling_count,
            'chain' => $person->chain,
            'pending' => (bool) $person->pending_father,
            'is_public' => (bool) $person->is_public,
            'birth_year' => $person->birth_year,
            'death_year' => $person->death_year,
            'image' => $person->image,
            'bio' => $person->bio,
            'spouse' => $person->spouse,
            'spouse_marga' => $person->spouse_marga,
            'father' => $person->pending_father
                ? null
                : ($person->father
                    ? [
                        'id' => $person->father->id,
                        'name' => $person->father->name,
                        'marga_id' => $person->father->marga_id,
                        'marga' => $person->father->marga?->name,
                        'chain' => $person->father->chain,
                        'birth_year' => $person->father->birth_year,
                        'death_year' => $person->father->death_year,
                    ]
                    : null),
            'mother' => $person->mother
                ? [
                    'id' => $person->mother->id,
                    'name' => $person->mother->name,
                    'marga_id' => $person->mother->marga_id,
                    'marga' => $person->mother->marga?->name,
                    'birth_year' => $person->mother->birth_year,
                    'death_year' => $person->mother->death_year,
                ]
                : null,
            'lineage' => $lineage
                ->map(fn (Person $row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'marga' => $row->marga?->name,
                    'chain' => $row->chain,
                    'is_self' => $row->id === $person->id,
                    'children' => $row->children
                        ->map(fn (Person $child) => [
                            'id' => $child->id,
                            'name' => $child->name,
                            'gender' => $child->gender,
                            'marga' => $child->marga?->name,
                            'chain' => $child->chain,
                            'birth_order' => $child->birth_order,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'children' => $siblings
                ->map(fn (Person $sibling) => [
                    'id' => $sibling->id,
                    'name' => $sibling->name,
                    'gender' => $sibling->gender,
                    'spouse' => $sibling->spouse,
                    'spouse_marga' => $sibling->spouse_marga,
                    'marga_id' => $sibling->marga_id,
                    'marga' => $sibling->marga?->name,
                    'birth_order' => $sibling->birth_order,
                    'chain' => $sibling->chain,
                    'pending' => (bool) $sibling->pending_father,
                ])
                ->values()
                ->all(),
            'ownChildren' => $person->children()
                ->orderBy('birth_order')
                ->get()
                ->map(fn (Person $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'gender' => $child->gender,
                    'spouse' => $child->spouse,
                    'spouse_marga' => $child->spouse_marga,
                    'marga_id' => $child->marga_id,
                    'marga' => $child->marga?->name,
                    'new_marga' => '',
                    'birth_order' => $child->birth_order,
                    'chain' => $child->chain,
                    'pending' => (bool) $child->pending_father,
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

    /**
     * Marga options scoped to a single user's marga.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function margaOptionsForUser(User $user): array
    {
        if ($user->marga_id === null) {
            return [];
        }

        return Marga::query()
            ->where('id', $user->marga_id)
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
            ])
            ->all();
    }

    /**
     * Locked marga payload sent to the form so family entries stay scoped.
     *
     * @return array{id: int, name: string}|null
     */
    protected function lockedMarga(User $user): ?array
    {
        $marga = $user->marga;

        return $marga !== null ? ['id' => $marga->id, 'name' => $marga->name] : null;
    }
}
