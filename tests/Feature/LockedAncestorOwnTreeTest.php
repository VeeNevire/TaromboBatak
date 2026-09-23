<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;

/**
 * Kakek → Ayah → Darma in the owner's tree, with an approved contribution
 * matching Darma as a father — which locks Kakek and Ayah as ancestors.
 *
 * @return array{marga: Marga, owner: User, tree: FamilyTree, kakek: Person, kakekNode: FamilyTreeNode}
 */
function lockedLineage(): array
{
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();

    $kakek = Person::factory()->create(['name' => 'Kakek Darma', 'gender' => 'L', 'marga_id' => $marga->id, 'created_by' => $owner->id, 'father_id' => null]);
    $ayah = Person::factory()->create(['name' => 'Ayah Darma', 'gender' => 'L', 'marga_id' => $marga->id, 'created_by' => $owner->id, 'father_id' => $kakek->id]);
    $darma = Person::factory()->create(['name' => 'Darma', 'gender' => 'L', 'marga_id' => $marga->id, 'created_by' => $owner->id, 'father_id' => $ayah->id]);
    $anak = Person::factory()->create(['name' => 'Anak Darma', 'gender' => 'L', 'marga_id' => $marga->id, 'created_by' => $owner->id, 'father_id' => $darma->id]);

    $tree = FamilyTree::create(['user_id' => $owner->id, 'root_person_id' => $kakek->id, 'name' => 'Keluarga Darma']);
    $kakekNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $kakek->id, 'chain' => '1']);
    $ayahNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $ayah->id, 'father_node_id' => $kakekNode->id, 'birth_order' => 1]);
    $darmaNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $darma->id, 'father_node_id' => $ayahNode->id, 'birth_order' => 1]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $anak->id, 'father_node_id' => $darmaNode->id, 'birth_order' => 1]);

    ContributionRequest::factory()->approved()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $darma->id,
        'subject_person_id' => $anak->id,
        'affected_person_ids' => [$anak->id],
    ]);

    return compact('marga', 'owner', 'tree', 'kakek', 'kakekNode');
}

test('a locked ancestor stays locked on the global form and in other accounts trees', function () {
    ['marga' => $marga, 'owner' => $owner, 'tree' => $tree, 'kakek' => $kakek] = lockedLineage();
    $otherMember = User::factory()->withMarga($marga->id)->create();

    expect($owner->can('update', $kakek))->toBeFalse()
        ->and($otherMember->can('update', $kakek))->toBeFalse();

    $this->actingAs($otherMember)
        ->put(route('people.update', ['person' => $kakek, 'version_tree' => $tree->id]), [
            'name' => $kakek->name,
            'father' => ['name' => 'Ayah Kakek Darma'],
        ])
        ->assertForbidden();

    expect($kakek->fresh()->father_id)->toBeNull()
        ->and(Person::query()->where('name', 'Ayah Kakek Darma')->exists())->toBeFalse();
});

test('a father typed by name on the tree form is created and connected above a locked ancestor', function () {
    ['marga' => $marga, 'owner' => $owner, 'tree' => $tree, 'kakek' => $kakek, 'kakekNode' => $kakekNode] = lockedLineage();
    $pendingBefore = ContributionRequest::query()->where('status', ContributionRequest::STATUS_PENDING)->count();

    $this->actingAs($owner)
        ->put(route('people.update', ['person' => $kakek, 'version_tree' => $tree->id]), [
            'name' => $kakek->name,
            'father' => ['name' => 'Ayah Kakek Darma'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('people.show', ['person' => $kakek, 'version_tree' => $tree->id]));

    $father = Person::query()->where('name', 'Ayah Kakek Darma')->firstOrFail();
    $fatherNode = $tree->nodes()->where('person_id', $father->id)->first();

    expect($father->gender)->toBe('L')
        ->and($father->marga_id)->toBe($marga->id)
        ->and($father->created_by)->toBe($owner->id)
        ->and($kakek->fresh()->father_id)->toBe($father->id)
        ->and($fatherNode)->not->toBeNull()
        ->and($kakekNode->fresh()->father_node_id)->toBe($fatherNode->id)
        ->and(ContributionRequest::query()->where('status', ContributionRequest::STATUS_PENDING)->count())->toBe($pendingBefore);
});

test('an ambiguous typed father name is rejected instead of being dropped', function () {
    ['marga' => $marga, 'owner' => $owner, 'tree' => $tree, 'kakek' => $kakek] = lockedLineage();
    Person::factory()->count(2)->create(['name' => 'Ayah Kembar', 'gender' => 'L', 'marga_id' => $marga->id]);

    $this->actingAs($owner)
        ->put(route('people.update', ['person' => $kakek, 'version_tree' => $tree->id]), [
            'name' => $kakek->name,
            'father' => ['name' => 'Ayah Kembar'],
        ])
        ->assertSessionHasErrors('father.name');

    expect($kakek->fresh()->father_id)->toBeNull();
});
