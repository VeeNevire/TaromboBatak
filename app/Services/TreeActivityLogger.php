<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\Person;
use App\Models\TreeActivityLog;
use App\Models\User;

class TreeActivityLogger
{
    /** @param array<string, mixed> $details */
    public function record(
        Person $person,
        User $actor,
        string $action,
        string $summary,
        array $details = [],
        ?FamilyTree $familyTree = null,
    ): void {
        $context = app(TreeProtectionService::class)->contextForLog($person, $familyTree);

        if ($context === null) {
            return;
        }

        TreeActivityLog::query()->create([
            'person_id' => $person->id,
            'family_tree_id' => $context['family_tree_id'],
            'actor_id' => $actor->id,
            'marga_id' => $context['marga_id'] ?? $person->marga_id,
            'action' => $action,
            'protection_scope' => $context['scope'],
            'summary' => $summary,
            'details' => $details,
        ]);
    }
}
