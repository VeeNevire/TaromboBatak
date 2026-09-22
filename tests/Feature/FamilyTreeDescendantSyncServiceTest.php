<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeDescendantSyncService;

test('syncing cascades into an alternative version owned by a different user', function () {
    $marga = Marga::factory()->create();
    $sourceOwner = User::factory()->withMarga($marga->id)->create();
    $alternativeOwner = User::factory()->withMarga($marga->id)->create();

    $ancestor = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id]);
    $child = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id, 'father_id' => $ancestor->id]);

    $source = FamilyTree::create(['user_id' => $sourceOwner->id, 'root_person_id' => $ancestor->id]);
    FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $ancestor->id]);

    $alternative = FamilyTree::create([
        'user_id' => $alternativeOwner->id,
        'root_person_id' => $ancestor->id,
        'based_on_id' => $source->id,
    ]);
    FamilyTreeNode::create(['family_tree_id' => $alternative->id, 'person_id' => $ancestor->id]);

    app(FamilyTreeDescendantSyncService::class)->syncTreeAndDescendantVersions($source);

    expect($source->nodes()->where('person_id', $child->id)->exists())->toBeTrue()
        ->and($alternative->nodes()->where('person_id', $child->id)->exists())->toBeTrue();
});
