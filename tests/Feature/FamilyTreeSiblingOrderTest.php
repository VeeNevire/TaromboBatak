<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->withMarga(Marga::factory()->create()->id)->create();
    $this->father = Person::factory()->create(['marga_id' => $this->owner->marga_id, 'gender' => 'L']);
    $this->tree = FamilyTree::create(['user_id' => $this->owner->id, 'root_person_id' => $this->father->id]);
    $this->fatherNode = FamilyTreeNode::create(['family_tree_id' => $this->tree->id, 'person_id' => $this->father->id]);
    $this->nodes = collect([1, 2, 3])->map(fn (int $order) => FamilyTreeNode::create([
        'family_tree_id' => $this->tree->id,
        'person_id' => Person::factory()->create([
            'father_id' => $this->father->id,
            'marga_id' => $this->owner->marga_id,
            'gender' => 'L',
        ])->id,
        'father_node_id' => $this->fatherNode->id,
        'birth_order' => $order,
    ]));
});

test('tree owner can reorder brothers inside the tree version', function () {
    $reversed = $this->nodes->pluck('id')->reverse()->values();

    $this->actingAs($this->owner)
        ->post(route('family-trees.sibling-order.update', $this->tree), [
            'father_node_id' => $this->fatherNode->id,
            'node_ids' => $reversed->all(),
        ])->assertSessionHasNoErrors();

    $reversed->each(fn (int $id, int $index) => expect(FamilyTreeNode::find($id)->birth_order)->toBe($index + 1));
});

test('other users cannot reorder a tree they do not own', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('family-trees.sibling-order.update', $this->tree), [
            'father_node_id' => $this->fatherNode->id,
            'node_ids' => $this->nodes->pluck('id')->reverse()->values()->all(),
        ])->assertForbidden();
});

test('nodes that are not children of the father are rejected', function () {
    $this->actingAs($this->owner)
        ->post(route('family-trees.sibling-order.update', $this->tree), [
            'father_node_id' => $this->fatherNode->id,
            'node_ids' => [$this->nodes[0]->id, $this->fatherNode->id],
        ])->assertSessionHasErrors('node_ids');
});

test('reordering in an original tree updates the person order and other original trees', function () {
    $other = FamilyTree::create(['user_id' => $this->owner->id, 'root_person_id' => $this->father->id]);
    $otherFatherNode = FamilyTreeNode::create(['family_tree_id' => $other->id, 'person_id' => $this->father->id]);
    $otherNodes = $this->nodes->map(fn ($node) => FamilyTreeNode::create([
        'family_tree_id' => $other->id,
        'person_id' => $node->person_id,
        'father_node_id' => $otherFatherNode->id,
        'birth_order' => $node->birth_order,
    ]));
    $reversed = $this->nodes->reverse()->values();

    $this->actingAs($this->owner)
        ->post(route('family-trees.sibling-order.update', $this->tree), [
            'father_node_id' => $this->fatherNode->id,
            'node_ids' => $reversed->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

    $reversed->each(function ($node, int $index) use ($otherNodes) {
        expect(Person::find($node->person_id)->birth_order)->toBe($index + 1)
            ->and($otherNodes->firstWhere('person_id', $node->person_id)->fresh()->birth_order)->toBe($index + 1);
    });
});

test('reordering brothers in the marga updates original trees but not alternative versions', function () {
    $alternative = FamilyTree::create([
        'user_id' => $this->owner->id,
        'root_person_id' => $this->father->id,
        'based_on_id' => $this->tree->id,
    ]);
    $altFatherNode = FamilyTreeNode::create(['family_tree_id' => $alternative->id, 'person_id' => $this->father->id]);
    $altNode = FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $this->nodes[0]->person_id,
        'father_node_id' => $altFatherNode->id,
        'birth_order' => 1,
    ]);
    $marga = Marga::find($this->owner->marga_id);
    $staff = User::factory()->asAdmin()->create();
    $reversed = $this->nodes->pluck('person_id')->reverse()->values();

    $this->actingAs($staff)
        ->post(route('margas.sibling-order.update', $marga), [
            'father_id' => $this->father->id,
            'person_ids' => $reversed->all(),
        ])->assertSessionHasNoErrors();

    $reversed->each(fn (int $personId, int $index) => expect(
        $this->tree->nodes()->where('person_id', $personId)->first()->birth_order,
    )->toBe($index + 1));
    expect($altNode->fresh()->birth_order)->toBe(1);
});
