<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use Illuminate\Support\Collection;

class FamilyTreeInheritanceService
{
    /**
     * Resolve an alternative tree from its source plus its local overrides.
     *
     * @return Collection<int, array<string, int|bool|string|null>>
     */
    public function nodesFor(FamilyTree $tree): Collection
    {
        return $this->resolve($tree, []);
    }

    /**
     * @param  array<int, true>  $visitedTreeIds
     * @return Collection<int, array<string, int|bool|string|null>>
     */
    private function resolve(FamilyTree $tree, array $visitedTreeIds): Collection
    {
        if (isset($visitedTreeIds[$tree->id])) {
            return collect();
        }

        $visitedTreeIds[$tree->id] = true;
        $tree->loadMissing(['basedOn', 'nodes.fatherNode', 'nodes.motherNode']);
        $localNodes = $tree->nodes->keyBy('person_id');

        if ($tree->basedOn === null) {
            return $localNodes->map(fn (FamilyTreeNode $node) => $this->rawNode($node))->values();
        }

        $resolved = $this->resolve($tree->basedOn, $visitedTreeIds)->keyBy('person_id');

        foreach ($localNodes as $personId => $localNode) {
            $sourceNode = $resolved->get($personId);

            if ($sourceNode === null) {
                $resolved->put($personId, $this->rawNode($localNode));

                continue;
            }

            $resolved->put($personId, [
                ...$sourceNode,
                ...$this->overridesFor($localNode, $sourceNode),
                'node_id' => $localNode->id,
            ]);
        }

        return $resolved->values();
    }

    /** @return array<string, int|bool|string|null> */
    private function rawNode(FamilyTreeNode $node): array
    {
        return [
            'node_id' => $node->id,
            'person_id' => $node->person_id,
            'father_person_id' => $node->fatherNode?->person_id,
            'mother_person_id' => $node->motherNode?->person_id,
            'birth_order' => $node->birth_order,
            'sibling_count' => $node->sibling_count,
            'chain' => $node->chain,
            'pending_father' => $node->pending_father,
        ];
    }

    /**
     * Nodes created before explicit override tracking retain only their
     * differences from the source. New duplicated nodes use [] and inherit.
     *
     * @param  array<string, int|bool|string|null>  $sourceNode
     * @return array<string, int|bool|null>
     */
    private function overridesFor(FamilyTreeNode $localNode, array $sourceNode): array
    {
        if ($localNode->structure_overrides !== null) {
            return $localNode->structure_overrides;
        }

        $local = $this->rawNode($localNode);
        $overrides = [];

        foreach (['father_person_id', 'mother_person_id', 'birth_order', 'sibling_count', 'pending_father'] as $field) {
            if ($local[$field] !== $sourceNode[$field]) {
                $overrides[$field] = $local[$field];
            }
        }

        return $overrides;
    }
}
