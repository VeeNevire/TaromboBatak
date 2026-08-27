<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyEntryService
{
    public function syncTreeNodes(FamilyTree $tree): void
    {
        $this->syncLegacyNodes($tree);
    }

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
     * @return array{father: Person|null, matchedFather: Person|null, mother: Person|null, children: Collection<int, Person>, detached: array<int, Person>}
     */
    public function save(
        array $data,
        ?int $forcedMargaId = null,
        ?int $createdBy = null,
        bool $deferExistingFatherMatch = false,
    ): array {
        $oldFathers = $this->validateExistingRows($data, $forcedMargaId, $createdBy);

        $result = DB::transaction(function () use ($data, $forcedMargaId, $createdBy, $deferExistingFatherMatch) {
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

            $matchedFather = $deferExistingFatherMatch && $fatherGiven
                ? $this->findExistingFatherMatch($data, $fatherMargaId, $ineligibleFatherIds)
                : null;
            $pending = ! $fatherGiven || $matchedFather !== null;

            $father = $fatherGiven && $matchedFather === null
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

            $mothers = [];

            foreach ($this->motherEntries($data) as $index => $entry) {
                $motherMargaId = $this->resolveMargaId(
                    $entry['marga_id'] ?? null,
                    $entry['new_marga'] ?? null,
                );
                $resolvedMother = $this->resolveParent(
                    $entry['id'] ?? ($index === 0 ? ($data['mother_id'] ?? null) : null),
                    $entry,
                    $motherMargaId,
                    $forcedMargaId,
                    $createdBy,
                    'P',
                );

                if ($resolvedMother !== null && $this->normalizeName($entry['father_name'] ?? null) !== null) {
                    $motherFather = $this->resolveParent(
                        null,
                        ['name' => $entry['father_name']],
                        $motherMargaId,
                        null,
                        $createdBy,
                        'L',
                        $resolvedMother->ineligibleFatherIds(),
                    );

                    if ($motherFather !== null && $resolvedMother->father_id !== $motherFather->id) {
                        $resolvedMother->update([
                            'father_id' => $motherFather->id,
                            'pending_father' => false,
                        ]);
                    }
                }

                $mothers[] = $resolvedMother;
            }

            if ($father !== null) {
                $wifeLinks = [];

                foreach ($mothers as $resolvedMother) {
                    if ($resolvedMother !== null && ! isset($wifeLinks[$resolvedMother->id])) {
                        $wifeLinks[$resolvedMother->id] = [
                            'position' => count($wifeLinks) + 1,
                        ];
                    }
                }

                $father->wives()->sync($wifeLinks);
            }

            // Sibling focus tetap memakai istri pertama untuk kompatibilitas
            // form lama; anak fokus memilih Ibu masing-masing di bawah.
            $mother = $mothers[0] ?? null;

            $this->validateParentLinks($data, $father, $mothers);
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
                $mothers,
            );

            $detached = $this->detachRemoved(
                $data['removed_child_ids'] ?? [],
                $data['removed_own_child_ids'] ?? [],
                $focus?->id,
                $forcedMargaId,
                $createdBy,
            );

            $familyTrees = $this->syncFamilyTrees(
                $createdBy,
                $father,
                $mothers,
                $children,
                $ownChildren,
                $focus,
            );

            return [
                'father' => $father,
                'matchedFather' => $matchedFather,
                'pending' => $pending,
                'mother' => $mother,
                'mothers' => $mothers,
                'children' => $children,
                'ownChildren' => $ownChildren,
                'detached' => $detached,
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
            // Baris anak (own children) dilepas: rekomputasi chain dari fokus
            // agar keturunan yang tersisa tetap bernomor benar.
            $numbering->recomputeBranch($result['focus']);
        }

        foreach ($result['detached'] as $detachedPerson) {
            // Pohon yang dilepas berdiri sendiri; chain lamanya sudah dibersihkan
            // sehingga recompute memberi nomor induk root baru bila punya keturunan.
            $numbering->recomputeFromAncestor($detachedPerson->fresh());
        }

        $result['familyTrees']->each->touch();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $excludedIds
     */
    protected function findExistingFatherMatch(array $data, ?int $margaId, array $excludedIds): ?Person
    {
        if (! empty($data['father_id'])) {
            $father = Person::query()
                ->whereKey((int) $data['father_id'])
                ->whereNotIn('id', $excludedIds)
                ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
                ->first();

            if ($father === null && in_array((int) $data['father_id'], $excludedIds, true)) {
                throw ValidationException::withMessages([
                    'father_id' => 'Relasi orang tua ini akan membentuk siklus silsilah.',
                ]);
            }

            return $father;
        }

        $name = $this->normalizeName(data_get($data, 'father.name'));

        if ($name === null) {
            return null;
        }

        $matches = Person::query()
            ->where('name', $name)
            ->where(fn ($query) => $query->where('gender', 'L')->orWhereNull('gender'))
            ->when($margaId !== null, fn ($query) => $query->where('marga_id', $margaId))
            ->whereNotIn('id', $excludedIds)
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'father.name' => 'Terdapat lebih dari satu data Ayah dengan nama dan marga yang sama.',
            ]);
        }

        return $matches->first();
    }

    /**
     * Normalize the submitted wife list. The legacy single "mother" payload
     * is treated as the first wife so older clients keep working, and
     * array order is retained because child rows refer to wives by index.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function motherEntries(array $data): array
    {
        $entries = [];

        foreach (is_array($data['mothers'] ?? null) ? $data['mothers'] : [] as $entry) {
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        if ($entries === [] && is_array($data['mother'] ?? null)) {
            $entries = [$data['mother']];
        }

        return $entries;
    }

    /**
     * Keep one account-owned history record for every distinct patrilineal
     * tree touched by the family form.
     *
     * @param  array<int, Person|null>  $mothers
     * @param  Collection<int, Person>  $children
     * @param  Collection<int, Person>  $ownChildren
     * @return Collection<int, FamilyTree>
     */
    protected function syncFamilyTrees(
        ?int $createdBy,
        ?Person $father,
        array $mothers,
        Collection $children,
        Collection $ownChildren,
        ?Person $focus,
    ): Collection {
        if ($createdBy === null || $focus === null) {
            return new Collection;
        }

        // Each focus person owns a separate family history. Ancestors remain
        // members for context, but do not define this family's identity.
        $tree = FamilyTree::query()
            ->where('user_id', $createdBy)
            ->where('root_person_id', $focus->id)
            ->whereNull('based_on_id')
            ->first()
            ?? FamilyTree::create([
                'user_id' => $createdBy,
                'root_person_id' => $focus->id,
                'name' => 'Keluarga '.$focus->name,
            ]);

        $memberIds = $children
            ->merge($ownChildren)
            ->push($father)
            ->merge(collect($mothers)->filter())
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

        if ($tree->name === null) {
            $tree->update(['name' => 'Keluarga '.$focus->name]);
        }

        $tree->people()->syncWithoutDetaching(array_values(array_unique($memberIds)));
        $this->syncLegacyNodes($tree);

        return new Collection([$tree]);
    }

    /**
     * New entries still originate in the legacy family form. Mirror its
     * relationships into the initial version so they can later be copied and
     * edited independently without changing shared Person records.
     */
    protected function syncLegacyNodes(FamilyTree $tree): void
    {
        $people = $tree->people()->get();
        $memberIds = $people->pluck('id')->flip();

        foreach ($people as $person) {
            FamilyTreeNode::query()->updateOrCreate(
                ['family_tree_id' => $tree->id, 'person_id' => $person->id],
                [
                    'birth_order' => $person->birth_order,
                    'sibling_count' => $person->sibling_count,
                    'chain' => $person->chain,
                    'pending_father' => $person->pending_father,
                ],
            );
        }

        $nodes = $tree->nodes()->pluck('id', 'person_id');

        foreach ($people as $person) {
            FamilyTreeNode::query()
                ->whereKey($nodes[$person->id])
                ->update([
                    'father_node_id' => $person->father_id !== null && $memberIds->has($person->father_id)
                        ? $nodes[$person->father_id]
                        : null,
                    'mother_node_id' => $person->mother_id !== null && $memberIds->has($person->mother_id)
                        ? $nodes[$person->mother_id]
                        : null,
                ]);
        }
    }

    /**
     * Ensure existing row IDs still belong to the family being edited.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Person>
     */
    protected function validateExistingRows(
        array $data,
        ?int $forcedMargaId = null,
        ?int $createdBy = null,
    ): Collection
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
        $removedSiblingIds = collect(is_array($data['removed_child_ids'] ?? null) ? $data['removed_child_ids'] : [])
            ->filter()
            ->map(fn ($id) => (int) $id);
        $removedChildIds = collect(is_array($data['removed_own_child_ids'] ?? null) ? $data['removed_own_child_ids'] : [])
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($focusId === null) {
            if ($removedSiblingIds->isNotEmpty() || $removedChildIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'children' => 'Data keluarga baru belum punya anggota untuk dilepaskan.',
                ]);
            }

            $submittedPeople = Person::query()
                ->whereIn('id', $submittedSiblingIds->merge($submittedChildIds))
                ->get();

            if ($forcedMargaId !== null && $submittedPeople->contains(
                fn (Person $person) => (int) $person->marga_id !== $forcedMargaId
                    || (int) $person->created_by !== $createdBy,
            )) {
                throw ValidationException::withMessages([
                    'children' => 'Anda hanya dapat memakai ulang anggota yang Anda buat sendiri pada marga ini.',
                ]);
            }

            $submittedPeople->load('father');

            return $submittedPeople
                ->whereNotNull('father_id')
                ->pluck('father')
                ->filter()
                ->unique('id')
                ->values();
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
            $candidate = isset($row['id']) ? Person::query()->find((int) $row['id']) : null;

            if ($candidate !== null
                && ! $allowedSiblingIds->contains($candidate->id)
                && ($candidate->father_id !== null
                    || ($forcedMargaId !== null && (int) $candidate->created_by !== $createdBy))) {
                $errors["children.$index.id"] = 'Anggota ini bukan bagian dari keluarga yang sedang diedit.';
            }
        }

        foreach ($childRows as $index => $row) {
            $candidate = isset($row['id']) ? Person::query()->with('father')->find((int) $row['id']) : null;

            if ($candidate !== null
                && ! $allowedChildIds->contains($candidate->id)
                && ($candidate->father_id !== null
                    || ($forcedMargaId !== null && (int) $candidate->created_by !== $createdBy))) {
                $fatherName = $candidate->father->name ?? 'orang lain';
                $errors["ownChildren.$index.id"] = "{$candidate->name} sudah tercatat sebagai anak dari {$fatherName}. Pilih orang yang sesuai atau buat data baru.";
            }
        }

        if ($removedSiblingIds->contains(fn (int $id) => ! $allowedSiblingIds->contains($id))) {
            $errors['removed_child_ids'] = 'Ada saudara yang dilepas bukan bagian dari keluarga yang sedang diedit.';
        }

        if ($removedChildIds->contains(fn (int $id) => ! $allowedChildIds->contains($id))) {
            $errors['removed_own_child_ids'] = 'Ada anak yang dilepas bukan anak dari person yang sedang diedit.';
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
     * @param  array<int, Person|null>  $mothers
     */
    protected function validateParentLinks(array $data, ?Person $father, array $mothers): void
    {
        if (! isset($data['id'])) {
            return;
        }

        $focusId = (int) $data['id'];
        $links = ['father_id' => $father];

        foreach ($mothers as $mother) {
            $links['mother_id'] = $mother;
        }

        foreach ($links as $field => $parent) {
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
            $label = $expectedGender === 'P' ? 'Ibu' : 'Ayah';
            $errorField = $expectedGender === 'P' ? 'mother.name' : 'father.name';

            if ($excludedIds !== [] && Person::query()->whereIn('id', $excludedIds)->where('name', $name)->exists()) {
                throw ValidationException::withMessages([
                    'father.name' => "{$label} tidak boleh merupakan orang itu sendiri, saudara sekandung, atau keturunannya.",
                ]);
            }

            if ($expectedGender !== null && Person::query()->where('name', $name)->whereNotNull('gender')->where('gender', '!=', $expectedGender)->exists()) {
                throw ValidationException::withMessages([
                    $errorField => "{$label} harus dipilih dari anggota ".($expectedGender === 'P' ? 'perempuan' : 'laki-laki').'.',
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
     * @param  array<int, Person|null>  $mothers
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
        array $mothers = [],
    ): Collection {
        $children = new Collection;

        foreach ($rows as $index => $row) {
            $child = isset($row['id'])
                ? Person::query()->find((int) $row['id'])
                : null;
            $isFocus = isset($data['id'])
                ? isset($row['id']) && (int) $row['id'] === (int) $data['id']
                : $focusOrder !== null && (int) $focusOrder === $index + 1;

            if ($child !== null && $forcedMargaId !== null && $child->marga_id !== $forcedMargaId) {
                abort(403, 'Anggota yang dipilih berada di luar marga akun Anda.');
            }

            if ($child !== null && $fatherId !== null) {
                $targetFather = Person::query()->find($fatherId);

                if ($child->id === $fatherId
                    || ($targetFather !== null && $this->parentPathContains($targetFather, $child->id))) {
                    $field = $focusOrder === null ? "ownChildren.$index.id" : "children.$index.id";

                    throw ValidationException::withMessages([
                        $field => 'Anggota ini tidak dapat dijadikan anak karena akan membentuk siklus silsilah.',
                    ]);
                }
            }

            if ($child !== null
                && $child->father_id !== null
                && $child->father_id !== $fatherId
                && ! $preserveSiblingPaths) {
                $field = $focusOrder === null ? "ownChildren.$index.id" : "children.$index.id";
                $fatherName = $child->father->name ?? 'orang lain';

                throw ValidationException::withMessages([
                    $field => "{$child->name} sudah tercatat sebagai anak dari {$fatherName} dan tidak dipindahkan otomatis.",
                ]);
            }

            $childMotherId = $motherId;
            $availableMothers = array_values(array_filter($mothers));

            if (($row['mother_index'] ?? null) === null) {
                if (count($availableMothers) === 1) {
                    $childMotherId = $availableMothers[0]->id;
                } elseif (count($availableMothers) > 1) {
                    throw ValidationException::withMessages([
                        "ownChildren.$index.mother_index" => 'Pilih Ibu untuk anak ini.',
                    ]);
                }
            }

            if (array_key_exists('mother_index', $row) && $row['mother_index'] !== null) {
                $motherIndex = (int) $row['mother_index'];
                $selectedMother = $mothers[$motherIndex] ?? null;

                if ($selectedMother === null) {
                    throw ValidationException::withMessages([
                        "ownChildren.$index.mother_index" => 'Ibu yang dipilih tidak tersedia.',
                    ]);
                }

                $childMotherId = $selectedMother->id;
            }

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
                'mother_id' => $childMotherId,
                'birth_order' => $index + 1,
                'sibling_count' => $siblingCount,
                'pending_father' => $pending,
                ...$focusedFields,
            ], fn ($value) => $value !== null);

            $attributes['father_id'] = $fatherId;
            $attributes['mother_id'] = $childMotherId;
            $attributes['pending_father'] = $pending;

            if ($preserveSiblingPaths && $child?->father_id !== null && ! $isFocus) {
                unset(
                    $attributes['father_id'],
                    $attributes['mother_id'],
                    $attributes['birth_order'],
                    $attributes['sibling_count'],
                    $attributes['pending_father'],
                );
            }

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
     * Detach people that were removed from the form from their parent
     * instead of deleting them. Every attribute stays intact and the whole
     * patrilineal descendant subtree is preserved; the detached person just
     * becomes the root of its own standalone tree.
     *
     * The focused person (the record being edited) is never detached. For
     * non-staff users every detached person must have been created by the
     * submitting user.
     *
     * @param  array<int, mixed>  $siblingIds
     * @param  array<int, mixed>  $ownChildIds
     * @return array<int, Person>
     */
    protected function detachRemoved(array $siblingIds, array $ownChildIds, ?int $focusId, ?int $forcedMargaId, ?int $createdBy): array
    {
        $ids = array_values(array_unique(array_filter(
            array_merge($siblingIds, $ownChildIds),
            fn ($id) => is_numeric($id) && (int) $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $ids = array_map('intval', $ids);

        if ($focusId !== null) {
            $ids = array_values(array_diff($ids, [$focusId]));
        }

        if ($ids === []) {
            return [];
        }

        $people = Person::query()->whereIn('id', $ids)->get();

        if ($forcedMargaId !== null) {
            abort_unless(
                $people->every(fn (Person $person) => (int) $person->created_by === (int) $createdBy),
                403,
                'Anda tidak memiliki akses untuk melepas salah satu anggota.',
            );
        }

        $detached = [];

        foreach ($people as $person) {
            if ($person->father_id === null) {
                continue;
            }

            // Chain lama memakai format "ayah-anak" sehingga tidak lagi valid
            // untuk sebuah root baru; dibersihkan di sini, nomor induk baru
            // diberikan oleh recompute setelah transaksi commit.
            $person->forceFill(['father_id' => null, 'chain' => null])->save();

            $detached[] = $person;
        }

        return $detached;
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
