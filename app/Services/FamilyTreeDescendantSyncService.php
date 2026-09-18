<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class FamilyTreeDescendantSyncService
{
    /**
     * Add missing descendants to one tree and every alternative derived from it.
     * Existing nodes are deliberately left untouched because their placement may
     * be an override specific to that version.
     */
    public function syncTreeAndDescendantVersions(FamilyTree $tree): void
    {
        $pendingTreeIds = [$tree->id];
        $processedTreeIds = [];

        while ($pendingTreeIds !== []) {
            $treeId = array_shift($pendingTreeIds);

            if ($treeId === null || isset($processedTreeIds[$treeId])) {
                continue;
            }

            $processedTreeIds[$treeId] = true;
            $currentTree = FamilyTree::query()->find($treeId);

            if ($currentTree === null) {
                continue;
            }

            $this->sync($currentTree);

            $childTreeIds = FamilyTree::query()
                ->where('user_id', $tree->user_id)
                ->where('based_on_id', $currentTree->id)
                ->pluck('id')
                ->all();

            array_push($pendingTreeIds, ...$childTreeIds);
        }
    }

    /**
     * Materialize inherited nodes and global descendants missing from one tree.
     *
     * @return int Number of nodes added.
     */
    public function sync(FamilyTree $familyTree): int
    {
        return DB::transaction(function () use ($familyTree): int {
            $familyTree = FamilyTree::query()->lockForUpdate()->findOrFail($familyTree->id);

            // Approved alternatives are immutable. They already inherit source
            // branches in read-only views, so do not materialize new local nodes.
            if ($familyTree->structureIsLocked()) {
                return 0;
            }

            $nodesByPersonId = $familyTree->nodes()
                ->lockForUpdate()
                ->get()
                ->keyBy('person_id');
            $effectiveNodes = app(FamilyTreeInheritanceService::class)
                ->nodesFor($familyTree)
                ->keyBy('person_id');
            $addedNodes = collect();

            foreach ($effectiveNodes as $personId => $effectiveNode) {
                if ($nodesByPersonId->has($personId)) {
                    continue;
                }

                $node = FamilyTreeNode::create([
                    'family_tree_id' => $familyTree->id,
                    'person_id' => $personId,
                    'birth_order' => $effectiveNode['birth_order'],
                    'sibling_count' => $effectiveNode['sibling_count'],
                    'chain' => $effectiveNode['chain'],
                    'pending_father' => $effectiveNode['pending_father'],
                    'structure_overrides' => [],
                ]);
                $nodesByPersonId->put($personId, $node);
                $addedNodes->put($personId, $node);
            }

            foreach ($addedNodes as $personId => $node) {
                $effectiveNode = $effectiveNodes->get($personId);

                $node->update([
                    'father_node_id' => $nodesByPersonId
                        ->get($effectiveNode['father_person_id'])?->id,
                    'mother_node_id' => $nodesByPersonId
                        ->get($effectiveNode['mother_person_id'])?->id,
                ]);
            }

            if ($addedNodes->isNotEmpty()) {
                $familyTree->people()->syncWithoutDetaching($addedNodes->keys()->all());
            }

            $frontierPersonIds = $nodesByPersonId->keys()->map(fn ($id) => (int) $id)->all();

            while ($frontierPersonIds !== []) {
                $children = Person::query()
                    ->whereIn('father_id', $frontierPersonIds)
                    ->whereNotIn('id', $nodesByPersonId->keys())
                    ->orderBy('birth_order')
                    ->orderBy('id')
                    ->get();
                $frontierPersonIds = [];

                foreach ($children as $child) {
                    $fatherNode = $nodesByPersonId->get($child->father_id);

                    if ($fatherNode === null) {
                        continue;
                    }

                    $node = FamilyTreeNode::create([
                        'family_tree_id' => $familyTree->id,
                        'person_id' => $child->id,
                        'father_node_id' => $fatherNode->id,
                        'mother_node_id' => $nodesByPersonId->get($child->mother_id)?->id,
                        'birth_order' => $child->birth_order,
                        'sibling_count' => $child->sibling_count,
                        'pending_father' => $child->pending_father,
                        'structure_overrides' => $familyTree->based_on_id === null ? null : [],
                    ]);
                    $nodesByPersonId->put($child->id, $node);
                    $addedNodes->put($child->id, $node);
                    $frontierPersonIds[] = $child->id;
                }
            }

            if ($addedNodes->isNotEmpty()) {
                $familyTree->people()->syncWithoutDetaching($addedNodes->keys()->all());
                app(FamilyTreeChainNumberingService::class)->recompute($familyTree);
                $familyTree->touch();
            }

            return $addedNodes->count();
        });
    }
}
