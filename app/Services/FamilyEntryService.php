<?php

namespace App\Services;

use App\Models\FamilyTree;
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
            $focusPerson = isset($data['id']) ? Person::query()->find((int) $data['id']) : null;
            $ineligibleFatherIds = $focusPerson?->ineligibleFatherIds() ?? [];

            $father = $fatherGiven
                ? $this->resolveParent(
                    $data['father_id'] ?? null,
                    $data['father'] ?? null,
                    $fatherMargaId,
                    $forcedMargaId,
                    $createdBy,
                    'L',
                    $ineligibleFatherIds,
                )
                : null;
            $fatherChanged = $focusPerson !== null && (
                $focusPerson->father_id !== $father?->id
                || (bool) $focusPerson->pending_father !== $pending
            );

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
                $fatherChanged,
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
                false,
                $forcedMargaId,
                $createdBy,
                false,
            );

            $this->deleteRemoved(
                $data['removed_child_ids'] ?? [],
                $data['removed_own_child_ids'] ?? [],
                $focus?->id,
                $forcedMargaId,
                $createdBy,
            );

            $familyTrees = $this->syncFamilyTrees(
                $createdBy,
                $father,
                $mother,
                $children,
                $ownChildren,
                $focus,
            );

            return [
                'father' => $father,
                'pending' => $pending,
                'mother' => $mother,
                'children' => $children,
                'ownChildren' => $ownChildren,
                'focus' => $focus,
                'familyTrees' => $familyTrees,
                'fatherChanged' => $fatherChanged,
            ];
        });

        $numbering = app(ChainNumberingService::class);

        if ($result['fatherChanged'] && $result['focus'] !== null) {
            $numbering->recomputeBranch($result['focus']);

            foreach ($oldFathers as $oldFather) {
                $numbering->recomputeFromAncestor($oldFather->fresh());
            }
        } else {
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
        }

        if (! empty($data['removed_own_child_ids']) && $result['focus'] !== null) {
            // Baris anak (own children) dihapus: rekomputasi chain dari fokus
            // agar keturunan yang tersisa tetap bernomor benar.
            app(ChainNumberingService::class)->recomputeBranch($result['focus']);
        }

        $result['familyTrees']->each->touch();

        return $result;
    }

    /**
     * Keep one account-owned history record for every distinct patrilineal
     * tree touched by the family form.
     *
     * @param  Collection<int, Person>  $children
     * @param  Collection<int, Person>  $ownChildren
     * @return Collection<int, FamilyTree>
     */
    protected function syncFamilyTrees(
        ?int $createdBy,
        ?Person $father,
        ?Person $mother,
        Collection $children,
        Collection $ownChildren,
        ?Person $focus,
    ): Collection {
        if ($createdBy === null || $focus === null) {
            return new Collection;
        }

        $root = $this->topmostAncestor($father ?? $focus);
        $trees = $focus->familyTrees()->get();

        if ($trees->isEmpty()) {
            $tree = $father?->familyTrees()->where('user_id', $createdBy)->first()
                ?? FamilyTree::query()
                    ->where('user_id', $createdBy)
                    ->where('root_person_id', $root->id)
                    ->first()
                ?? FamilyTree::create([
                    'user_id' => $createdBy,
                    'root_person_id' => $root->id,
                ]);
            $trees->push($tree);
        }

        $memberIds = $children
            ->merge($ownChildren)
            ->push($father)
            ->push($mother)
            ->push($focus)
            ->filter()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $current = $father;
        $seen = [];

        while ($current !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $memberIds[] = $current->id;
            $current = $current->father()->first();
        }

        $normalizedTrees = new Collection;

        foreach ($trees as $tree) {
            $existing = FamilyTree::query()
                ->where('user_id', $tree->user_id)
                ->where('root_person_id', $root->id)
                ->whereKeyNot($tree->id)
                ->first();

            if ($existing !== null) {
                $existing->people()->syncWithoutDetaching($tree->people()->pluck('people.id'));
                $tree->delete();
                $tree = $existing;
            } else {
                $tree->update(['root_person_id' => $root->id]);
            }

            $tree->people()->syncWithoutDetaching(array_values(array_unique($memberIds)));
            $normalizedTrees->put($tree->id, $tree);
        }

        return $normalizedTrees->values();
    }

    protected function topmostAncestor(Person $person): Person
    {
        $current = $person;
        $seen = [];

        while ($current->father_id !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $father = $current->father()->first();

            if ($father === null) {
                break;
            }

            $current = $father;
        }

        return $current;
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

            return $children->firstWhere('id', $id) ?? Person::find($id);
        }

        $order = max(1, (int) ($data['birth_order'] ?? 1)) - 1;

        return $children->values()->get($order) ?? $children->first();
    }

    /**
     * Resolve a parent record from a linked id or a free-text entry.
     * Reuses an existing record with the same name to avoid duplicates.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<int, int>  $excludedIds
     */
    protected function resolveParent(
        ?int $parentId,
        ?array $data,
        ?int $margaId,
        ?int $forcedMargaId = null,
        ?int $createdBy = null,
        ?string $expectedGender = null,
        array $excludedIds = [],
    ): ?Person {
        if ($parentId && $forcedMargaId === null) {
            if (in_array($parentId, $excludedIds, true)) {
                throw ValidationException::withMessages([
                    'father_id' => 'Relasi orang tua ini akan membentuk siklus silsilah.',
                ]);
            }

            $parent = Person::query()
                ->whereKey($parentId)
                ->whereNotIn('id', $excludedIds)
                ->when($expectedGender !== null, fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('gender', $expectedGender)
                        ->orWhereNull('gender'),
                ))
                ->first();

            if ($parent) {
                $this->applyExpectedGender($parent, $expectedGender);
                $this->applyYears($parent, $data);
                $this->applyAlias($parent, $data);

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
            ->whereNotIn('id', $excludedIds)
            ->when($expectedGender !== null, fn ($query) => $query->where(
                fn ($query) => $query
                    ->where('gender', $expectedGender)
                    ->orWhereNull('gender'),
            ))
            ->limit(2)
            ->get();
        $parent = $matches->count() === 1 ? $matches->first() : null;

        if ($parent === null) {
            if ($excludedIds !== [] && Person::query()->whereIn('id', $excludedIds)->where('name', $name)->exists()) {
                throw ValidationException::withMessages([
                    'father.name' => 'Ayah tidak boleh merupakan orang itu sendiri, saudara sekandung, atau keturunannya.',
                ]);
            }

            if ($expectedGender !== null && Person::query()->where('name', $name)->whereNotNull('gender')->where('gender', '!=', $expectedGender)->exists()) {
                throw ValidationException::withMessages([
                    'father.name' => 'Ayah harus dipilih dari anggota laki-laki.',
                ]);
            }

            $parent = Person::create(array_filter([
                'name' => $name,
                'alias' => $data['alias'] ?? null,
                'gender' => $expectedGender,
                'marga_id' => $margaId,
                'created_by' => $createdBy,
                'birth_year' => $data['birth_year'] ?? null,
                'death_year' => $data['death_year'] ?? null,
            ], fn ($value) => $value !== null));

            return $parent;
        }

        $this->applyExpectedGender($parent, $expectedGender);

        // Isi placeholder "N/A" dengan nama ayah yang baru diketik.
        if ($parent->isNa()) {
            $parent->update(['name' => $name]);
        }

        $this->applyYears($parent, $data);
        $this->applyAlias($parent, $data);

        if ($margaId !== null && $parent->marga_id === null) {
            $parent->update(['marga_id' => $margaId]);
        }

        return $parent;
    }

    protected function applyExpectedGender(Person $person, ?string $expectedGender): void
    {
        if ($expectedGender !== null && $person->gender === null) {
            $person->update(['gender' => $expectedGender]);
        }
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
        bool $preserveSiblingPaths = false,
        ?int $forcedMargaId = null,
        ?int $createdBy = null,
        bool $pending = false,
    ): Collection {
        $children = new Collection;

        foreach (array_values($rows) as $index => $row) {
            $isFocus = isset($data['id'])
                ? isset($row['id']) && (int) $row['id'] === (int) $data['id']
                : $focusOrder !== null && (int) $focusOrder === $index + 1;

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
                'alias' => $row['alias'] ?? null,
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

            $attributes['father_id'] = $fatherId;
            $attributes['mother_id'] = $motherId;
            $attributes['pending_father'] = $pending;

            if ($preserveSiblingPaths && isset($row['id']) && ! $isFocus) {
                unset(
                    $attributes['father_id'],
                    $attributes['mother_id'],
                    $attributes['birth_order'],
                    $attributes['sibling_count'],
                    $attributes['pending_father'],
                );
            }

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
     * Permanently delete people that were removed from the form, cascading
     * through their whole patrilineal descendant subtree.
     *
     * The focused person (the record being edited) and any parent records are
     * never removed. For non-staff users every removed person must have been
     * created by the submitting user.
     *
     * @param  array<int, mixed>  $siblingIds
     * @param  array<int, mixed>  $ownChildIds
     */
    protected function deleteRemoved(array $siblingIds, array $ownChildIds, ?int $focusId, ?int $forcedMargaId, ?int $createdBy): void
    {
        $ids = array_values(array_unique(array_filter(
            array_merge($siblingIds, $ownChildIds),
            fn ($id) => is_numeric($id) && (int) $id > 0,
        )));

        if ($ids === []) {
            return;
        }

        $ids = array_map('intval', $ids);

        if ($focusId !== null) {
            $ids = array_values(array_diff($ids, [$focusId]));
        }

        if ($ids === []) {
            return;
        }

        if ($forcedMargaId !== null) {
            $owned = Person::query()
                ->whereIn('id', $ids)
                ->where('created_by', $createdBy)
                ->pluck('id')
                ->all();

            abort_unless(
                array_diff($ids, $owned) === [],
                403,
                'Anda tidak memiliki akses untuk menghapus salah satu anggota.',
            );
        }

        $deleteIds = $this->descendantIds($ids);

        Person::query()->whereIn('id', $deleteIds)->delete();
    }

    /**
     * Collect a person id together with every patrilineal descendant id,
     * walking down the father_id links level by level.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function descendantIds(array $ids): array
    {
        $all = $ids;
        $queue = $ids;

        while ($queue !== []) {
            $children = Person::query()
                ->whereIn('father_id', $queue)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $all = array_merge($all, $children);
            $queue = $children;
        }

        return array_values(array_unique($all));
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
     * @param  array<string, mixed>|null  $data
     */
    protected function applyAlias(Person $person, ?array $data): void
    {
        if ($data === null || ! array_key_exists('alias', $data) || $data['alias'] === null) {
            return;
        }

        $person->update(['alias' => $data['alias']]);
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
