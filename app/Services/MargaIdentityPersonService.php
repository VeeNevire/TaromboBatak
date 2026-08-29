<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use Illuminate\Support\Collection;

class MargaIdentityPersonService
{
    public const MAX_GENERATION = 11;

    /** @return Collection<int, array{id: int, name: string, chain: string, generation: int}> */
    public function options(): Collection
    {
        $primaryTreeId = FamilyTree::query()
            ->where('is_primary', true)
            ->whereHas('rootPerson', fn ($query) => $query->where('name', 'Si Raja Batak'))
            ->latest('id')
            ->value('id');

        if ($primaryTreeId === null) {
            return collect();
        }

        return FamilyTreeNode::query()
            ->where('family_tree_id', $primaryTreeId)
            ->whereNotNull('chain')
            ->with('person:id,name')
            ->get(['id', 'family_tree_id', 'person_id', 'chain'])
            ->map(fn (FamilyTreeNode $node) => [
                'id' => $node->person_id,
                'name' => $node->person->name,
                'chain' => $node->chain,
                'generation' => substr_count($node->chain, '-') + 1,
            ])
            ->filter(fn (array $option) => $option['generation'] <= self::MAX_GENERATION)
            ->sort(fn (array $left, array $right) => strnatcmp($left['chain'], $right['chain']))
            ->values();
    }

    public function contains(int $personId): bool
    {
        return $this->options()->contains('id', $personId);
    }
}
