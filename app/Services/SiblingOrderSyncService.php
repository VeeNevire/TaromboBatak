<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class SiblingOrderSyncService
{
    /**
     * Copy the global brother order of one father into every original
     * (non-alternative) tree that holds him. Alternative versions keep their
     * own overrides and are never touched.
     *
     * @param  array<int, int>  $orderedPersonIds
     */
    public function personsToTrees(int $fatherPersonId, array $orderedPersonIds, ?int $exceptTreeId = null): void
    {
        $positions = array_flip(array_values($orderedPersonIds));

        FamilyTree::query()
            ->whereNull('based_on_id')
            ->when($exceptTreeId !== null, fn ($query) => $query->whereKeyNot($exceptTreeId))
            ->whereHas('nodes', fn ($nodes) => $nodes->where('person_id', $fatherPersonId))
            ->get()
            ->each(function (FamilyTree $tree) use ($fatherPersonId, $positions): void {
                $fatherNodeIds = $tree->nodes()->where('person_id', $fatherPersonId)->pluck('id');
                $changed = false;

                $tree->nodes()
                    ->whereIn('father_node_id', $fatherNodeIds)
                    ->whereIn('person_id', array_keys($positions))
                    ->get()
                    ->each(function (FamilyTreeNode $node) use ($positions, &$changed): void {
                        $order = $positions[$node->person_id] + 1;

                        if ($node->birth_order !== $order) {
                            $node->update(['birth_order' => $order]);
                            $changed = true;
                        }
                    });

                if ($changed) {
                    app(FamilyTreeChainNumberingService::class)->recompute($tree);
                }
            });
    }

    /**
     * Mirror an order saved in an original tree onto the global person
     * records, then onto the other original trees that hold the same father.
     *
     * @param  array<int, int>  $orderedNodeIds
     */
    public function treeToPersons(FamilyTree $tree, int $fatherNodeId, array $orderedNodeIds): void
    {
        if ($tree->based_on_id !== null) {
            return;
        }

        $fatherNode = $tree->nodes()->find($fatherNodeId);

        if ($fatherNode === null) {
            return;
        }

        $nodes = $tree->nodes()->whereIn('id', $orderedNodeIds)->get()->keyBy('id');
        $orderedPersonIds = collect($orderedNodeIds)
            ->map(fn ($nodeId) => $nodes->get((int) $nodeId)?->person_id)
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($orderedPersonIds): void {
            foreach ($orderedPersonIds as $index => $personId) {
                Person::query()->whereKey($personId)->update(['birth_order' => $index + 1]);
            }
        });

        $father = Person::query()->find($fatherNode->person_id);

        if ($father !== null) {
            app(ChainNumberingService::class)->recomputeFromAncestor($father);
        }

        $this->personsToTrees($fatherNode->person_id, $orderedPersonIds, $tree->id);
    }
}
