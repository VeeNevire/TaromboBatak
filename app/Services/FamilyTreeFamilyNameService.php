<?php

namespace App\Services;

use App\Models\FamilyTree;

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
        return $this->manualForPerson($tree, $personId)
            ?? $this->automaticForPerson($tree, $personId);
    }

    /**
     * The name typed for this person's own branch, without inheritance.
     */
    public function manualForPerson(FamilyTree $tree, int $personId): ?string
    {
        $name = $tree->nodes()->where('person_id', $personId)->value('family_name');

        return filled($name) ? $name : null;
    }

    /**
     * The name the system supplies when no manual name is set: the closest
     * named ancestor branch, else the tree's own name.
     */
    public function automaticForPerson(FamilyTree $tree, int $personId): ?string
    {
        $nodes = $tree->nodes()->toBase()
            ->get(['id', 'person_id', 'father_node_id', 'family_name'])->keyBy('id');
        $self = $nodes->firstWhere('person_id', $personId);
        $node = $self?->father_node_id === null ? null : $nodes->get($self->father_node_id);
        $visited = $self === null ? [] : [$self->id => true];

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
