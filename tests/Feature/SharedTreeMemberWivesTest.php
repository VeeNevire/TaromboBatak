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
});

test('a new member can be added with several wives', function () {
    $this->actingAs($this->owner)
        ->post(route('family-trees.people.store', $this->tree), [
            'name' => 'Anak Baru',
            'gender' => 'L',
            'father_node_id' => $this->fatherNode->id,
            'wives' => [
                ['name' => 'Istri Pertama', 'marga' => 'Boru Silaban'],
                ['name' => 'Istri Kedua', 'marga' => ''],
            ],
        ])->assertSessionHasNoErrors();

    $member = Person::where('name', 'Anak Baru')->firstOrFail();

    expect($member->wives()->orderBy('person_wife.position')->pluck('name')->all())
        ->toBe(['Istri Pertama', 'Istri Kedua']);
});

test('wives are rejected for a female member', function () {
    $this->actingAs($this->owner)
        ->post(route('family-trees.people.store', $this->tree), [
            'name' => 'Anak Perempuan',
            'gender' => 'P',
            'father_node_id' => $this->fatherNode->id,
            'wives' => [['name' => 'Istri', 'marga' => '']],
        ])->assertSessionHasErrors('wives');
});

test('a child row can carry a first spouse and extra spouses', function () {
    $this->actingAs($this->owner)
        ->post(route('family-trees.people.store', $this->tree), [
            'name' => 'Anak Baru',
            'gender' => 'L',
            'father_node_id' => $this->fatherNode->id,
            'children' => [[
                'name' => 'Cucu',
                'gender' => 'L',
                'spouse' => 'Pasangan Satu',
                'spouse_marga' => 'Silaban',
                'extra_wives' => [['name' => 'Pasangan Dua', 'marga' => '']],
            ]],
        ])->assertSessionHasNoErrors();

    $grandchild = Person::where('name', 'Cucu')->firstOrFail();

    expect($grandchild->wives()->pluck('name')->all())->toBe(['Pasangan Satu', 'Pasangan Dua']);
});
