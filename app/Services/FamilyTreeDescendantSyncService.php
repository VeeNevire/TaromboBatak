<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class FamilyTreeDescendantSyncService
{
    /**
     * Propagate a newly appended person to this owner's trees that contain
     * one of the person's paternal ancestors, plus the tree they edited.
     */
    public function syncTreesForNewDescendant(FamilyTree $sourceTree, Person $person): void
    {
        $ancestorIds = [];
        $seen = [];
        $current = $person;

        while ($current !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $ancestorIds[] = $current->id;
            $current = $current->father_id === null
                ? null
                : Person::query()->find($current->father_id);
        }

        $trees = FamilyTree::query()
            ->where('user_id', $sourceTree->user_id)
            ->where(function ($query) use ($ancestorIds): void {
                $query->whereIn('root_person_id', $ancestorIds)
                    ->orWhereHas('people', fn ($people) => $people->whereIn('people.id', $ancestorIds))
                    ->orWhereHas('nodes', fn ($nodes) => $nodes->whereIn('person_id', $ancestorIds));
            })
            ->get();

        $trees->push($sourceTree);
        $trees->unique('id')->each(fn (FamilyTree $tree) => $this->syncTreeAndDescendantVersions($tree));
    }

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

            // based_on_id alone establishes the derivation: a version can be
            // owned by a different user than its source (see
            // FamilyTreeVersionService::duplicate()), so ownership is not a
            // valid filter here.
            $childTreeIds = FamilyTree::query()
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
