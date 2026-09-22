<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyTreeStructureService
{
    /**
     * Apply a complete set of parent and sibling-order changes to one version.
     * IDs are node IDs, deliberately preventing links across tree versions.
     *
     * @param  array<int, array{id: int, father_node_id: int|null, birth_order: int|null}>  $entries
     */
    public function update(FamilyTree $tree, array $entries): void
    {
        DB::transaction(function () use ($tree, $entries): void {
            $tree = FamilyTree::query()->lockForUpdate()->findOrFail($tree->id);
            $tree->ensureStructureIsEditable();
            $nodes = $tree->nodes()->get()->keyBy('id');
            $sourceNodes = $tree->basedOn !== null
                ? app(FamilyTreeInheritanceService::class)->nodesFor($tree->basedOn)->keyBy('person_id')
                : collect();
            $parents = $nodes->mapWithKeys(fn (FamilyTreeNode $node) => [$node->id => $node->father_node_id])->all();
            $orders = [];

            foreach ($entries as $entry) {
                $nodeId = (int) $entry['id'];
                $fatherNodeId = isset($entry['father_node_id']) ? (int) $entry['father_node_id'] : null;

                if (! isset($nodes[$nodeId])) {
                    throw ValidationException::withMessages(['entries' => 'Node tidak termasuk dalam versi silsilah ini.']);
                }

                if ($fatherNodeId !== null && ! isset($nodes[$fatherNodeId])) {
                    throw ValidationException::withMessages(['entries' => 'Ayah harus berasal dari versi silsilah yang sama.']);
                }

                if ($fatherNodeId === $nodeId) {
                    throw ValidationException::withMessages(['entries' => 'Seseorang tidak dapat menjadi ayah dirinya sendiri.']);
                }

                $parents[$nodeId] = $fatherNodeId;
                $orders[$nodeId] = isset($entry['birth_order']) ? (int) $entry['birth_order'] : null;
            }

            $this->ensureAcyclic($parents);

            foreach ($entries as $entry) {
                $node = $nodes[(int) $entry['id']];
                $attributes = [
                    'father_node_id' => $parents[$node->id],
                    'birth_order' => $orders[$node->id],
                    'pending_father' => false,
                ];

                if ($tree->based_on_id !== null) {
                    $fatherPersonId = $parents[$node->id] !== null
                        ? $nodes[$parents[$node->id]]->person_id
                        : null;
                    $overrides = $node->structure_overrides ?? [];
                    $source = $sourceNodes->get($node->person_id);

                    foreach ([
                        'father_person_id' => $fatherPersonId,
                        'birth_order' => $orders[$node->id],
                        'pending_father' => false,
                    ] as $field => $value) {
                        if ($source === null || $source[$field] !== $value) {
                            $overrides[$field] = $value;
                        } else {
                            unset($overrides[$field]);
                        }
                    }

                    $attributes['structure_overrides'] = $overrides;
                }

                $node->update($attributes);
            }

            app(FamilyTreeChainNumberingService::class)->recompute($tree);
            $tree->touch();
        });
    }

    /**
     * Translate the existing family form's person-based rows into nodes of one
     * version, appending new members locally. Existing person relationships
     * and global chains are deliberately not touched.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFromFamilyForm(FamilyTree $tree, Person $focus, array $data, ?int $createdBy = null): void
    {
        DB::transaction(function () use ($tree, $focus, $data, $createdBy): void {
            $tree = FamilyTree::query()->lockForUpdate()->findOrFail($tree->id);
            $tree->ensureStructureIsEditable();
            $this->applyFamilyForm($tree, $focus, $data, $createdBy ?? $tree->user_id);
        });
    }

    /** @param array<string, mixed> $data */
    private function applyFamilyForm(FamilyTree $tree, Person $focus, array $data, int $createdBy): void
    {
        if (($data['name'] ?? $focus->name) !== $focus->name) {
            throw ValidationException::withMessages(['name' => 'Biodata tidak diubah pada versi alternatif.']);
        }

        $nodes = $tree->nodes()->get()->keyBy('person_id');
        $focusNode = $nodes->get($focus->id);

        if ($focusNode === null) {
            throw ValidationException::withMessages([
                'version_tree' => 'Orang ini tidak termasuk dalam versi silsilah yang dipilih.',
            ]);
        }

        $nodeForPerson = function (mixed $personId) use ($nodes): ?FamilyTreeNode {
            if (! is_numeric($personId) || (int) $personId < 1) {
                return null;
            }

            return $nodes->get((int) $personId);
        };

        $entries = [];
        $fatherNode = $nodeForPerson(data_get($data, 'father.id'));
        $fatherId = data_get($data, 'father.id');

        if (filled($fatherId) && $fatherNode === null) {
            // Allow a father from the same marga even when he is not part of
            // this version yet: attach him (and his same-marga ancestors) as
            // nodes so the version stays unified within one marga.
            $father = Person::query()->find((int) $fatherId);
            $allowedMargaId = $focus->marga_id ?? $tree->rootPerson()->value('marga_id');

            // The form pre-fills the global father even when this version does
            // not link him (e.g. a cross-marga father or a root node). Keep the
            // version placement untouched for that unchanged father instead of
            // rejecting the save.
            $isUnchangedGlobalFather = $father !== null
                && ! $focus->pending_father
                && (int) $focus->father_id === (int) $fatherId;

            if (! $isUnchangedGlobalFather
                && ($father === null
                    || ($allowedMargaId !== null && (int) $father->marga_id !== (int) $allowedMargaId))) {
                throw ValidationException::withMessages([
                    'father.id' => 'Ayah harus berasal dari marga yang sama.',
                ]);
            }

            if (! $isUnchangedGlobalFather) {
                $fatherNode = $this->attachPersonNode($tree, $father, $nodes, $allowedMargaId);
            }
        }

        $entries[$focusNode->id] = [
            'id' => $focusNode->id,
            'father_node_id' => $fatherNode?->id,
            'birth_order' => filled($data['birth_order'] ?? null)
                ? (int) $data['birth_order']
                : null,
        ];

        if ($fatherNode !== null) {
            app(FamilyEntryService::class)->syncWives(
                $fatherNode->person()->firstOrFail(),
                $data,
                $createdBy,
            );
        }

        foreach (['children', 'ownChildren'] as $group) {
            foreach (($data[$group] ?? []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $parentNode = $group === 'ownChildren' ? $focusNode : $fatherNode;

                if (! filled($row['id'] ?? null)) {
                    if (! filled($row['name'] ?? null)) {
                        continue;
                    }

                    if ($parentNode === null) {
                        throw ValidationException::withMessages([
                            $group.'.'.$index.'.name' => 'Pilih ayah pada silsilah ini sebelum menambahkan anak.',
                        ]);
                    }

                    $person = app(SharedFamilyTreeAppendService::class)->append($tree, [
                        'name' => trim($row['name']),
                        'alias' => $row['alias'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'spouse' => $row['spouse'] ?? null,
                        'spouse_marga' => $row['spouse_marga'] ?? null,
                        'father_node_id' => $parentNode->id,
                        'birth_order' => $index + 1,
                    ], $createdBy);
                    $node = $tree->nodes()->where('person_id', $person->id)->firstOrFail();
                    $nodes->put($person->id, $node);
                } else {
                    $node = $nodeForPerson($row['id']);
                }
                if ($node === null) {
                    throw ValidationException::withMessages([
                        $group.'.'.$index.'.id' => 'Anggota harus berasal dari versi silsilah yang sama.',
                    ]);
                }

                $entries[$node->id] = [
                    'id' => $node->id,
                    'father_node_id' => $parentNode?->id,
                    'birth_order' => $index + 1,
                ];
            }
        }

        // A removed row must also be detached from this version. Keeping the
        // node untouched here makes it reappear after the form redirects.
        $removedPersonIds = collect([
            ...($data['removed_child_ids'] ?? []),
            ...($data['removed_own_child_ids'] ?? []),
        ])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($removedPersonIds as $personId) {
            $node = $nodeForPerson($personId);

            if ($node !== null && $node->id !== $focusNode->id) {
                $entries[$node->id] = [
                    'id' => $node->id,
                    'father_node_id' => null,
                    'birth_order' => null,
                ];
            }
        }

        $this->update($tree, array_values($entries));
    }

    /**
     * Attach an existing person as a node of the version, walking up the
     * same-marga father chain so the lineage stays intact.
     *
     * @param  Collection<int, FamilyTreeNode>  $nodes
     * @param  array<int, bool>  $seen
     */
    private function attachPersonNode(FamilyTree $tree, Person $person, Collection $nodes, ?int $allowedMargaId, array $seen = []): FamilyTreeNode
    {
        $existing = $nodes->get($person->id);

        if ($existing !== null) {
            return $existing;
        }

        if (isset($seen[$person->id])) {
            throw ValidationException::withMessages([
                'father.id' => 'Relasi ayah akan membentuk siklus silsilah pada versi ini.',
            ]);
        }

        $seen[$person->id] = true;
        $parentNode = null;

        if ($person->father_id !== null) {
            $parent = Person::query()->find($person->father_id);

            if ($parent !== null
                && ($allowedMargaId === null || (int) $parent->marga_id === $allowedMargaId)
                && ! isset($seen[$parent->id])) {
                $parentNode = $this->attachPersonNode($tree, $parent, $nodes, $allowedMargaId, $seen);
            }
        }

        $node = $tree->nodes()->create([
            'person_id' => $person->id,
            'father_node_id' => $parentNode?->id,
            'pending_father' => (bool) $person->pending_father,
        ]);

        $nodes->put($person->id, $node);

        return $node;
    }

    /** @param array<int, int|null> $parents */
    protected function ensureAcyclic(array $parents): void
    {
        foreach (array_keys($parents) as $start) {
            $seen = [];
            $current = $start;

            while ($current !== null) {
                if (isset($seen[$current])) {
                    throw ValidationException::withMessages([
                        'entries' => 'Relasi ayah akan membentuk siklus silsilah pada versi ini.',
                    ]);
                }

                $seen[$current] = true;
                $current = $parents[$current] ?? null;
            }
        }
    }
}
