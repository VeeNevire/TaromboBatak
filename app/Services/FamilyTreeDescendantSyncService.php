<?php

namespace App\Services;

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FamilyTreeDescendantSyncService
{
    /**
     * Propagate a newly appended person to this owner's trees that contain one
     * of the person's paternal ancestors, plus the tree they edited. Trees of
     * other owners only receive the new person (and their children) under the
     * father's node, which stays cheap however many trees share the lineage.
     */
    public function syncTreesForNewDescendant(FamilyTree $sourceTree, Person $person): void
    {
        $trees = $this->treesContainingAncestorsOf($person, $sourceTree->user_id);
        $trees->push($sourceTree);
        $trees->unique('id')->each(fn (FamilyTree $tree) => $this->syncTreeAndDescendantVersions($tree));

        $this->syncTreesForPerson($person);
    }

    /**
     * Add a person, then their direct children, to every tree that already
     * holds their father.
     */
    public function syncTreesForPerson(Person $person): void
    {
        $this->attachToTreesHoldingFather($person);

        Person::query()
            ->where('father_id', $person->id)
            ->orderBy('birth_order')
            ->orderBy('id')
            ->get()
            ->each(fn (Person $child) => $this->attachToTreesHoldingFather($child));
    }

    /**
     * Insert one node for the person under the father's node in every unlocked
     * tree that holds the father but not the person yet. Unlike sync(), this
     * neither loads whole trees nor renumbers them: the chain is derived from
     * the father's chain.
     */
    public function attachToTreesHoldingFather(Person $person): int
    {
        if ($person->father_id === null) {
            return 0;
        }

        $fatherNodes = FamilyTreeNode::query()
            ->where('person_id', $person->father_id)
            ->where('is_removed', false)
            ->whereNotIn('family_tree_id', FamilyTreeNode::query()
                ->select('family_tree_id')
                ->where('person_id', $person->id))
            ->with('familyTree:id,based_on_id')
            ->get(['id', 'family_tree_id', 'chain']);

        if ($fatherNodes->isEmpty()) {
            return 0;
        }

        $lockedTreeIds = ContributionRequest::query()
            ->whereIn('family_tree_id', $fatherNodes->pluck('family_tree_id'))
            ->whereIn('status', [ContributionRequest::STATUS_PENDING, ContributionRequest::STATUS_APPROVED])
            ->pluck('family_tree_id')
            ->flip();
        $now = now();
        $nodes = [];
        $pivot = [];

        foreach ($fatherNodes->unique('family_tree_id') as $fatherNode) {
            $tree = $fatherNode->familyTree;

            if ($tree === null || ($tree->based_on_id !== null && $lockedTreeIds->has($tree->id))) {
                continue;
            }

            $nodes[] = [
                'family_tree_id' => $tree->id,
                'person_id' => $person->id,
                'father_node_id' => $fatherNode->id,
                'mother_node_id' => null,
                'birth_order' => $person->birth_order,
                'sibling_count' => $person->sibling_count,
                'chain' => $fatherNode->chain !== null && $person->birth_order !== null
                    ? $fatherNode->chain.'-'.$person->birth_order
                    : null,
                'pending_father' => false,
                'structure_overrides' => $tree->based_on_id === null ? null : '[]',
                'is_removed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $pivot[] = ['family_tree_id' => $tree->id, 'person_id' => $person->id];
        }

        if ($nodes === []) {
            return 0;
        }

        DB::transaction(function () use ($nodes, $pivot): void {
            FamilyTreeNode::query()->insert($nodes);
            DB::table('family_tree_person')->insertOrIgnore($pivot);
        });

        return count($nodes);
    }

    /**
     * @return Collection<int, FamilyTree>
     */
    private function treesContainingAncestorsOf(Person $person, int $userId): Collection
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

        return FamilyTree::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($ancestorIds): void {
                $query->whereIn('root_person_id', $ancestorIds)
                    ->orWhereHas('people', fn ($people) => $people->whereIn('people.id', $ancestorIds))
                    ->orWhereHas('nodes', fn ($nodes) => $nodes->whereIn('person_id', $ancestorIds));
            })
            ->get();
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
