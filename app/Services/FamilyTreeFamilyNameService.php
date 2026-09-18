<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;

class FamilyTreeFamilyNameService
{
    /**
     * Store a name at the selected branch root. Its descendants inherit the
     * value until another branch root supplies a more specific name.
     */
    public function setForPerson(FamilyTree $tree, int $personId, ?string $name): void
    {
        $node = $tree->nodes()->where('person_id', $personId)->firstOrFail();

        $node->update([
            'family_name' => filled($name) ? trim($name) : null,
        ]);
    }

    /**
     * Resolve the closest named branch, falling back to the legacy tree name
     * so existing trees remain readable before their branches are named.
     */
    public function forPerson(FamilyTree $tree, int $personId): ?string
    {
        $nodes = $tree->nodes()->get()->keyBy('id');
        $node = $nodes->firstWhere('person_id', $personId);
        $visited = [];

        while ($node !== null && ! isset($visited[$node->id])) {
            $visited[$node->id] = true;

            if (filled($node->family_name)) {
                return $node->family_name;
            }

            $node = $node->father_node_id === null
                ? null
                : $nodes->get($node->father_node_id);
        }

        return $tree->name;
    }
}
