<?php

namespace App\Services;

use App\Models\FamilyTree;

class FamilyTreeDeletionService
{
    public function isConnectedToOtherAccount(FamilyTree $tree): bool
    {
        $personIds = $tree->people()->pluck('people.id')
            ->merge($tree->nodes()->pluck('person_id'))
            ->when($tree->root_person_id !== null, fn ($ids) => $ids->push($tree->root_person_id))
            ->unique()
            ->values();

        if ($personIds->isEmpty()) {
            return false;
        }

        return FamilyTree::query()
            ->whereKeyNot($tree->id)
            ->where('user_id', '!=', $tree->user_id)
            ->where(fn ($query) => $query
                ->whereIn('root_person_id', $personIds)
                ->orWhereHas('people', fn ($people) => $people->whereKey($personIds))
                ->orWhereHas('nodes', fn ($nodes) => $nodes->whereIn('person_id', $personIds)))
            ->exists();
    }
}
