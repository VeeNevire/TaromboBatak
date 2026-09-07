<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeVersionService;

beforeEach(function () {
    $this->owner = User::factory()->asAdmin()->withMarga(Marga::factory()->create()->id)->create();
    $this->root = Person::factory()->create(['marga_id' => $this->owner->marga_id, 'gender' => 'L']);
    $this->middle = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->owner->marga_id]);
    $this->child = Person::factory()->create(['father_id' => $this->middle->id, 'marga_id' => $this->owner->marga_id]);
    $this->source = FamilyTree::create(['user_id' => $this->owner->id, 'root_person_id' => $this->root->id]);
    $rootNode = FamilyTreeNode::create(['family_tree_id' => $this->source->id, 'person_id' => $this->root->id]);
    $middleNode = FamilyTreeNode::create(['family_tree_id' => $this->source->id, 'person_id' => $this->middle->id, 'father_node_id' => $rootNode->id]);
    FamilyTreeNode::create(['family_tree_id' => $this->source->id, 'person_id' => $this->child->id, 'father_node_id' => $middleNode->id]);
    $this->alternative = app(FamilyTreeVersionService::class)->duplicate($this->source, $this->owner, 'Alternatif');
    $this->rootNode = $this->alternative->nodes()->where('person_id', $this->root->id)->firstOrFail();
    $this->childNode = $this->alternative->nodes()->where('person_id', $this->child->id)->firstOrFail();
});

test('approval preserves the submitted alternative relationships', function () {
    $this->actingAs($this->owner)->put(route('family-trees.update', $this->alternative), [
        'entries' => [['id' => $this->childNode->id, 'father_node_id' => $this->rootNode->id, 'birth_order' => 2]],
    ])->assertSessionHasNoErrors();
    $chain = $this->childNode->fresh()->chain;
    $this->post(route('contributions.marga-tree.store', $this->alternative))->assertSessionHasNoErrors();
    $submission = $this->alternative->contributionRequests()->firstOrFail();
    $this->post(route('contributions.approve', $submission))->assertRedirect();

    expect($submission->fresh()->status)->toBe(ContributionRequest::STATUS_APPROVED)
        ->and($this->childNode->fresh()->father_node_id)->toBe($this->rootNode->id)
        ->and($this->childNode->fresh()->chain)->toBe($chain)
        ->and($this->child->fresh()->father_id)->toBe($this->middle->id);
});

test('opening an alternative from the family form preserves its version context', function () {
    $this->actingAs($this->owner)
        ->get(route('people.edit', ['person' => $this->root, 'version_tree' => $this->alternative->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/form')
            ->where('selectedVersionId', $this->alternative->id));
});

test('an old family form cannot lose the version from its URL or create global people', function () {
    $personCount = Person::count();
    $treeCount = FamilyTree::count();
    $this->actingAs($this->owner)
        ->put(route('people.update', ['person' => $this->root, 'version_tree' => $this->alternative->id]), [
            'name' => $this->root->name,
            'version_tree' => null,
            'ownChildren' => [['name' => 'Must not become a global child']],
        ])->assertSessionHasErrors('ownChildren.0.id');

    expect(Person::count())->toBe($personCount)
        ->and(FamilyTree::count())->toBe($treeCount)
        ->and($this->alternative->nodes()->count())->toBe(3);
});

test('duplicating the selected alternative retains its modified structure', function () {
    $this->childNode->update(['father_node_id' => $this->rootNode->id]);
    $this->actingAs($this->owner)->post(route('family-trees.duplicate', $this->alternative))->assertRedirect();
    $copy = FamilyTree::where('based_on_id', $this->alternative->id)->firstOrFail();
    $copyRoot = $copy->nodes()->where('person_id', $this->root->id)->firstOrFail();
    expect($copy->nodes()->where('person_id', $this->child->id)->value('father_node_id'))->toBe($copyRoot->id);
});

test('submitted alternatives require a new copy before editing or appending', function (string $status) {
    ContributionRequest::factory()->create([
        'requester_id' => $this->owner->id,
        'matched_father_id' => $this->root->id,
        'subject_person_id' => $this->root->id,
        'family_tree_id' => $this->alternative->id,
        'status' => $status,
    ]);
    $originalFather = $this->childNode->father_node_id;
    $this->actingAs($this->owner)->put(route('family-trees.update', $this->alternative), [
        'entries' => [['id' => $this->childNode->id, 'father_node_id' => $this->rootNode->id, 'birth_order' => 2]],
    ])->assertSessionHasErrors('entries');
    $this->post(route('family-trees.people.store', $this->alternative), [
        'name' => 'Anggota Baru', 'father_node_id' => $this->rootNode->id,
    ])->assertSessionHasErrors('entries');
    expect($this->childNode->fresh()->father_node_id)->toBe($originalFather)
        ->and($this->alternative->nodes()->count())->toBe(3);
    $this->post(route('family-trees.duplicate', $this->alternative))->assertRedirect();
    $copy = FamilyTree::where('based_on_id', $this->alternative->id)->firstOrFail();
    $copyChild = $copy->nodes()->where('person_id', $this->child->id)->firstOrFail();
    $copyRoot = $copy->nodes()->where('person_id', $this->root->id)->firstOrFail();
    $this->put(route('family-trees.update', $copy), [
        'entries' => [['id' => $copyChild->id, 'father_node_id' => $copyRoot->id, 'birth_order' => 2]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('family-trees.show', $copy));
})->with(['pending', 'approved']);
