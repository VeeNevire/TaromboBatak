<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyEntryService
{
    /**
     * Persist a whole family entry (father, mother, and all sibling rows)
     * inside a single transaction, then recompute the chain numbers for the
     * affected patrilineal lineage.
     *
     * @param  array<string, mixed>  $data  Validated request data.
     * @param  int|null  $forcedMargaId  When set, every family member is forced
     *                                   into this marga and free-text marga
     *                                   creation is disabled.
     * @param  int|null  $createdBy  User id stamped on newly created records
     *                               so their owner can edit them later.
     * @return array{father: Person|null, mother: Person|null, children: Collection<int, Person>}
     */
    public function save(array $data, ?int $forcedMargaId = null, ?int $createdBy = null): array
    {
        $oldFathers = $this->validateExistingRows($data);

        $result = DB::transaction(function () use ($data, $forcedMargaId, $createdBy) {
            $fatherMargaId = $forcedMargaId
                ?? $this->resolveMargaId(
                    ($data['father'] ?? [])['marga_id'] ?? null,
                    ($data['father'] ?? [])['new_marga'] ?? null,
                )
                ?? $data['marga_id']
                ?? null;

            $fatherNameMd = data_get($data, 'father.name');
            $fatherNameGiven = $fatherNameMd !== null
                && $this->normalizeName($fatherNameMd) !== null;
            $fatherGiven = ! empty($data['father_id']) || $fatherNameGiven;
            $pending = ! $fatherGiven;

            $father = $fatherGiven
                ? $this->resolveParent(
                    $data['father_id'] ?? null,
                    $data['father'] ?? null,
                    $fatherMargaId,
                    $forcedMargaId,
                    $createdBy,
                )
                : null;

            $mother = $this->resolveParent(
                $data['mother_id'] ?? null,
                $data['mother'] ?? null,
                null,
                $forcedMargaId,
                $createdBy,
            );

            $this->validateParentLinks($data, $father, $mother);
            $this->validatePublication($data, $father);

            $children = $this->upsertChildren(
                $data['children'] ?? [],
                $fatherMargaId,
                $father?->id,
                $mother?->id,
                $data['sibling_count'] ?? null,
                $data['birth_order'] ?? null,
                $data,
                $forcedMargaId,
                $createdBy,
                $pending,
            );

            // Upsert own children of the focus person
            $focus = $this->resolveFocus($children, $data);
            $ownChildren = $this->upsertChildren(
                $data['ownChildren'] ?? [],
                $focus->marga_id,
                $focus->id,
                null,
                $data['ownChildren'] ? count($data['ownChildren']) : null,
                null,
                $data,
                $forcedMargaId,
                $createdBy,
                false,
            );

            return [
                'father' => $father,
                'pending' => $pending,
                'mother' => $mother,
                'children' => $children,
                'ownChildren' => $ownChildren,
                'focus' => $focus,
            ];
        });

        $numbering = app(ChainNumberingService::class);

        foreach ($oldFathers as $oldFather) {
            $numbering->recomputeFromAncestor($oldFather->fresh());
        }

        if ($result['father'] !== null) {
            $numbering->recomputeFromAncestor($result['father']->fresh());
        } elseif ($result['pending']) {
            // Keluarga yang belum tersambung tidak berchain; recompute tiap
            // rumpun baru sekalian membersihkan chain lama bila pernah ada.
            foreach ($result['children'] as $child) {
                $numbering->recomputeFromAncestor($child);
            }
        }

        return $result;
    }

    /**
     * Ensure existing row IDs still belong to the family being edited.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Person>
     */
    protected function validateExistingRows(array $data): Collection
    {
        $focusId = isset($data['id']) ? (int) $data['id'] : null;
        $siblingRows = $data['children'] ?? [];
        $childRows = $data['ownChildren'] ?? [];
        $siblingRows = is_array($siblingRows) ? $siblingRows : [];
        $childRows = is_array($childRows) ? $childRows : [];
        $submittedSiblingIds = collect($siblingRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id);
        $submittedChildIds = collect($childRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($focusId === null) {
            if ($submittedSiblingIds->isNotEmpty() || $submittedChildIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'children' => 'Data keluarga baru tidak boleh memakai ID anggota yang sudah ada.',
                ]);
            }

            return new Collection;
        }

        $focus = Person::query()->findOrFail($focusId);
        $allowedSiblingIds = $focus->father_id === null || $focus->pending_father
            ? collect([$focus->id])
            : Person::query()
                ->where('father_id', $focus->father_id)
                ->where(fn ($query) => $query
                    ->where('pending_father', false)
                    ->orWhere('id', $focus->id))
                ->pluck('id');
        $allowedChildIds = Person::query()
            ->where('father_id', $focus->id)
            ->pluck('id');
        $errors = [];

        foreach ($siblingRows as $index => $row) {
            if (isset($row['id']) && ! $allowedSiblingIds->contains((int) $row['id'])) {
                $errors["children.$index.id"] = 'Anggota ini bukan bagian dari keluarga yang sedang diedit.';
            }
        }

        foreach ($childRows as $index => $row) {
            if (isset($row['id']) && ! $allowedChildIds->contains((int) $row['id'])) {
                $errors["ownChildren.$index.id"] = 'Anggota ini bukan anak dari person yang sedang diedit.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return Person::query()
            ->whereIn('id', $submittedSiblingIds)
            ->whereNotNull('father_id')
            ->with('father')
            ->get()
            ->pluck('father')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Reject self-parenting and patrilineal cycles before any person is changed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateParentLinks(array $data, ?Person $father, ?Person $mother): void
    {
        if (! isset($data['id'])) {
            return;
        }

        $focusId = (int) $data['id'];

        foreach (['father_id' => $father, 'mother_id' => $mother] as $field => $parent) {
            if ($parent !== null && $this->parentPathContains($parent, $focusId)) {
                throw ValidationException::withMessages([
                    $field => 'Relasi orang tua ini akan membentuk siklus silsilah.',
                ]);
            }
        }
    }

    protected function parentPathContains(Person $candidate, int $focusId): bool
    {
        $stack = [$candidate];
        $seen = [];

        while ($stack !== []) {
            /** @var Person $current */
            $current = array_pop($stack);

            if ($current->id === $focusId) {
                return true;
            }

            if (isset($seen[$current->id])) {
                continue;
            }

            $seen[$current->id] = true;
            $current->loadMissing(['father', 'mother']);

            if ($current->father !== null) {
                $stack[] = $current->father;
            }

            if ($current->mother !== null) {
                $stack[] = $current->mother;
            }
        }

        return false;
    }

    /**
     * Public records must keep a complete public patrilineal path.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validatePublication(array $data, ?Person $father): void
    {
        if (! array_key_exists('is_public', $data)) {
            return;
        }

        $isPublic = filter_var($data['is_public'], FILTER_VALIDATE_BOOL);

        if ($isPublic && $father !== null && ! $father->is_public) {
            throw ValidationException::withMessages([
                'is_public' => 'Ayah harus dipublikasikan lebih dahulu agar jalur silsilah publik tetap lengkap.',
            ]);
        }

        if (! $isPublic && isset($data['id']) && Person::query()
            ->where('father_id', (int) $data['id'])
            ->public()
            ->exists()) {
            throw ValidationException::withMessages([
                'is_public' => 'Person ini masih memiliki keturunan publik dan belum dapat dibuat private.',
            ]);
        }
    }

    /**
     * Resolve the focus person from the children collection.
     * For edit: the person being edited (by id). For create: the person at birth_order position.
     *
     * @param  Collection<int, Person>  $children
     * @param  array<string, mixed>  $data
     */
    protected function resolveFocus(Collection $children, array $data): ?Person
    {
        if (isset($data['id'])) {
            $id = (int) $data['id'];

            return $children->firstWhere('id', $id);
        }

        $order = max(1, (int) ($data['birth_order'] ?? 1)) - 1;

        return $children->values()->get($order) ?? $children->first();
    }

    /**
     * Resolve a parent record from a linked id or a free-text entry.
     * Reuses an existing record with the same name to avoid duplicates.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function resolveParent(?int $parentId, ?array $data, ?int $margaId, ?int $forcedMargaId = null, ?int $createdBy = null): ?Person
    {
        if ($parentId && $forcedMargaId === null) {
            $parent = Person::find($parentId);

            if ($parent) {
                $this->applyYears($parent, $data);

                return $parent;
            }
        }

        $name = $this->normalizeName($data['name'] ?? null);

        if ($name === null) {
            return null;
        }

        $matches = Person::query()
            ->where('name', $name)
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->limit(2)
            ->get();
        $parent = $matches->count() === 1 ? $matches->first() : null;

        if ($parent === null) {
            $parent = Person::create(array_filter([
                'name' => $name,
                'marga_id' => $margaId,
                'created_by' => $createdBy,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
            ], fn ($value) => $value !== null));

            return $parent;
        }

        // Isi placeholder "N/A" dengan nama ayah yang baru diketik.
        if ($parent->isNa()) {
            $parent->update(['name' => $name]);
        }

        $this->applyYears($parent, $data);

        if ($margaId !== null && $parent->marga_id === null) {
            $parent->update(['marga_id' => $margaId]);
        }

        return $parent;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $data
     * @return Collection<int, Person>
     */
    protected function upsertChildren(
        array $rows,
        ?int $fatherMargaId,
        ?int $fatherId,
        ?int $motherId,
        ?int $siblingCount,
        mixed $focusOrder,
        array $data,
        ?int $forcedMargaId = null,
        ?int $createdBy = null,
        bool $pending = false,
    ): Collection {
        $children = new Collection;

        foreach (array_values($rows) as $index => $row) {
            $isFocus = $focusOrder !== null && (int) $focusOrder === $index + 1;

            $focusedFields = $isFocus ? [
                'alias' => $data['alias'] ?? null,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
                'image' => $data['image'] ?? null,
                'bio' => $data['bio'] ?? null,
                'is_public' => $data['is_public'] ?? null,
            ] : [];

            $childMargaId = $forcedMargaId
                ?? $this->resolveMargaId(
                    $row['marga_id'] ?? null,
                    $row['new_marga'] ?? null,
                )
                ?? $fatherMargaId;

            $attributes = array_filter([
                'name' => $this->normalizeName($row['name'] ?? null) ?? 'N/A',
                'gender' => $row['gender'] ?? null,
                'spouse' => $row['spouse'] ?? null,
                'spouse_marga' => $row['spouse_marga'] ?? null,
                'marga_id' => $childMargaId,
                'father_id' => $fatherId,
                'mother_id' => $motherId,
                'birth_order' => $index + 1,
                'sibling_count' => $siblingCount,
                'pending_father' => $pending,
                ...$focusedFields,
            ], fn ($value) => $value !== null);

            $child = isset($row['id']) ? Person::find((int) $row['id']) : null;

            if ($child) {
                $child->update($attributes);
            } else {
                $child = Person::create([...$attributes, 'created_by' => $createdBy]);
            }

            $children->push($child);
        }

        return $children;
    }

    /**
     * Resolve a marga from an existing id or a new name. When a name is
     * provided it is reused when it already exists, otherwise a new marga is
     * created on the fly.
     */
    protected function resolveMargaId(mixed $margaId, mixed $newMarga): ?int
    {
        $name = $this->normalizeName($newMarga);

        if ($name !== null) {
            return Marga::firstOrCreate(['name' => $name])->id;
        }

        if ($margaId) {
            return (int) $margaId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function applyYears(Person $person, ?array $data): void
    {
        if ($data === null) {
            return;
        }

        $updates = array_filter([
            'birth_year' => $data['birth_year'] ?? null,
            'death_year' => $data['death_year'] ?? null,
        ], fn ($value) => $value !== null);

        if ($updates !== []) {
            $person->update($updates);
        }
    }

    /**
     * Normalize a free-text name. Returns null when the field is empty or
     * explicitly marked as "N/A" (placeholder for unknown data).
     */
    protected function normalizeName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $trimmed = trim($name);

        if ($trimmed === '' || mb_strtoupper($trimmed) === 'N/A') {
            return null;
        }

        return $trimmed;
    }
}
