<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\User;

class FamilyTreeActivityLogger
{
    public function log(
        FamilyTree $tree,
        ?User $actor,
        string $action,
        string $description,
        ?string $memberName = null,
    ): FamilyTreeActivity {
        return FamilyTreeActivity::create([
            'family_tree_id' => $tree->id,
            'owner_id' => $tree->user_id,
            'actor_id' => $actor?->id,
            'tree_name' => $tree->name ?? $tree->rootPerson()->value('name') ?? 'Silsilah',
            'member_name' => $memberName,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
