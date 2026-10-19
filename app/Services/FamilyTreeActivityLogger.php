<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\Person;
use App\Models\TaromboSnapshot;
use App\Models\User;

class FamilyTreeActivityLogger
{
    public function log(
        FamilyTree $tree,
        ?User $actor,
        string $action,
        string $description,
        Person|string|null $member = null,
    ): FamilyTreeActivity {
        return FamilyTreeActivity::create([
            ...self::memberAttributes($member),
            'family_tree_id' => $tree->id,
            'owner_id' => $tree->user_id,
            'actor_id' => $actor?->id,
            'tree_name' => $tree->name ?? $tree->rootPerson()->value('name') ?? 'Silsilah',
            'action' => $action,
            'description' => $description,
        ]);
    }

    /**
     * Name of the member and, when known, the name of their father.
     *
     * @return array{member_name: string|null, father_name: string|null}
     */
    public static function memberAttributes(Person|string|null $member): array
    {
        if (! $member instanceof Person) {
            return ['member_name' => $member, 'father_name' => null];
        }

        return [
            'member_name' => $member->name,
            'father_name' => $member->pending_father ? null : $member->father?->name,
        ];
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
            ...self::memberAttributes($snapshot->centerPerson),
            'action' => 'downloaded',
            'description' => "Mengunduh gambar Tarombo \"{$title}\" milik ".($snapshot->user?->name ?? 'akun').'.',
        ]);
    }
}
