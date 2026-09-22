<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FatherConnectionService;
use Illuminate\Validation\ValidationException;

test('attaching an existing disconnected person as a child in ones own primary tree connects them globally and syncs other trees', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $ancestor = Person::factory()->create(['name' => 'Guru Sotadingon', 'gender' => 'L', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Ompu Raja Matakkang Manjuara',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => null,
        'pending_father' => false,
    ]);

    $personalTree = FamilyTree::create(['user_id' => $admin->id, 'root_person_id' => $ancestor->id, 'name' => 'Silsilah Pribadi']);
    $ancestorNode = FamilyTreeNode::create(['family_tree_id' => $personalTree->id, 'person_id' => $ancestor->id]);
    FamilyTreeNode::create(['family_tree_id' => $personalTree->id, 'person_id' => $child->id, 'father_node_id' => $ancestorNode->id]);

    $sharedTree = FamilyTree::create(['user_id' => $admin->id, 'root_person_id' => $ancestor->id, 'name' => 'Si Raja Batak - Versi alternatif']);
    FamilyTreeNode::create(['family_tree_id' => $sharedTree->id, 'person_id' => $ancestor->id]);

    $this->actingAs($admin)
        ->put(route('people.update', ['person' => $ancestor, 'version_tree' => $personalTree->id]), [
            'name' => $ancestor->name,
            'ownChildren' => [['id' => $child->id, 'name' => $child->name, 'gender' => 'L']],
        ])
        ->assertSessionHasNoErrors();

    expect($child->fresh()->father_id)->toBe($ancestor->id)
        ->and($child->fresh()->pending_father)->toBeFalse()
        ->and($sharedTree->nodes()->where('person_id', $child->id)->exists())->toBeTrue();
});

test('structure edits on an alternative version tree remain local only', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $ancestor = Person::factory()->create(['name' => 'Ayah Sumber', 'gender' => 'L', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Anak Terlepas',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => null,
    ]);

    $source = FamilyTree::create(['user_id' => $admin->id, 'root_person_id' => $ancestor->id]);
    $sourceAncestorNode = FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $ancestor->id]);
    FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $child->id]);

    $alternative = FamilyTree::create(['user_id' => $admin->id, 'root_person_id' => $ancestor->id, 'based_on_id' => $source->id]);
    $altAncestorNode = FamilyTreeNode::create(['family_tree_id' => $alternative->id, 'person_id' => $ancestor->id]);
    FamilyTreeNode::create(['family_tree_id' => $alternative->id, 'person_id' => $child->id]);

    $this->actingAs($admin)
        ->put(route('people.update', ['person' => $ancestor, 'version_tree' => $alternative->id]), [
            'name' => $ancestor->name,
            'ownChildren' => [['id' => $child->id, 'name' => $child->name, 'gender' => 'L']],
        ])
        ->assertSessionHasNoErrors();

    expect($child->fresh()->father_id)->toBeNull();
});

test('FatherConnectionService connects immediately within the requesters own tree', function () {
    $user = User::factory()->create();
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id]);
    $subject = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => null]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $father->id]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id]);

    $contribution = app(FatherConnectionService::class)->connect($subject, $father, $user->id, $tree);

    expect($contribution)->toBeNull()
        ->and($subject->fresh()->father_id)->toBe($father->id)
        ->and($subject->fresh()->pending_father)->toBeFalse();
});

test('FatherConnectionService defers to a contribution request outside the requesters own trees', function () {
    $marga = Marga::factory()->create();
    $requester = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id]);
    $ownerTree = FamilyTree::create(['user_id' => $owner->id, 'root_person_id' => $father->id]);
    FamilyTreeNode::create(['family_tree_id' => $ownerTree->id, 'person_id' => $father->id]);
    $subject = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => null]);

    $contribution = app(FatherConnectionService::class)->connect($subject, $father, $requester->id);

    expect($contribution)->not->toBeNull()
        ->and($contribution->status)->toBe(ContributionRequest::STATUS_PENDING)
        ->and($contribution->matched_father_id)->toBe($father->id)
        ->and($subject->fresh()->father_id)->toBeNull()
        ->and($subject->fresh()->pending_father)->toBeTrue()
        ->and($contributor->notifications()->count())->toBe(1);

    // A second attempt must not spawn a duplicate pending request.
    $again = app(FatherConnectionService::class)->connect($subject->fresh(), $father, $requester->id);
    expect($again->id)->toBe($contribution->id)
        ->and(ContributionRequest::count())->toBe(1);
});

test('FatherConnectionService rejects a connection that would form a cycle', function () {
    $user = User::factory()->create();
    $marga = Marga::factory()->create();
    $grandparent = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id]);
    $parent = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id, 'father_id' => $grandparent->id]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $grandparent->id]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $grandparent->id]);

    app(FatherConnectionService::class)->connect($grandparent, $parent, $user->id, $tree);
})->throws(ValidationException::class);
