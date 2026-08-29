<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
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
            $nodes = $tree->nodes()->get()->keyBy('id');
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
                $node->update([
                    'father_node_id' => $parents[$node->id],
                    'birth_order' => $orders[$node->id],
                    'pending_father' => false,
                ]);
            }

            app(FamilyTreeChainNumberingService::class)->recompute($tree);
            $tree->touch();
        });
    }

    /**
     * Translate the existing family form's person-based rows into nodes of one
     * version. Person relationships and global chains are deliberately not
     * touched.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFromFamilyForm(FamilyTree $tree, Person $focus, array $data): void
    {
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
        $entries[$focusNode->id] = [
            'id' => $focusNode->id,
            'father_node_id' => $fatherNode?->id,
            'birth_order' => filled($data['birth_order'] ?? null)
                ? (int) $data['birth_order']
                : null,
        ];

        foreach (['children', 'ownChildren'] as $group) {
            foreach (($data[$group] ?? []) as $index => $row) {
                if (! is_array($row) || ! filled($row['id'] ?? null)) {
                    continue;
                }

                $node = $nodeForPerson($row['id']);
                if ($node === null) {
                    throw ValidationException::withMessages([
                        $group.'.'.$index.'.id' => 'Anggota harus berasal dari versi silsilah yang sama.',
                    ]);
                }

                $parentNode = $group === 'ownChildren' ? $focusNode : $fatherNode;
                $entries[$node->id] = [
                    'id' => $node->id,
                    'father_node_id' => $parentNode?->id,
                    'birth_order' => $index + 1,
                ];
            }
        }

        $this->update($tree, array_values($entries));
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
