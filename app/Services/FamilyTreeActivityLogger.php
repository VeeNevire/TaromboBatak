<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\TaromboSnapshot;
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

    /**
     * Record a gallery download, which is not tied to a specific family tree.
     */
    public function logSnapshotDownload(TaromboSnapshot $snapshot, User $actor): FamilyTreeActivity
    {
        $title = $snapshot->title ?: $snapshot->centerPerson?->name ?: 'Pohon Tarombo';

        return FamilyTreeActivity::create([
            'family_tree_id' => null,
            'owner_id' => $snapshot->user_id,
            'actor_id' => $actor->id,
            'tree_name' => $title,
            'member_name' => $snapshot->centerPerson?->name,
            'action' => 'downloaded',
            'description' => "Mengunduh gambar Tarombo \"{$title}\" milik ".($snapshot->user?->name ?? 'akun').'.',
        ]);
    }
}
