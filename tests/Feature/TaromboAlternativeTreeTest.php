<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tarombo includes accessible male descendants from alternative tree versions', function () {
    $marga = Marga::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create([
        'name' => 'Raja Lontung',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    $son = Person::factory()->create([
        'name' => 'Toga Pandiangan',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
    ]);
    $daughter = Person::factory()->create([
        'name' => 'Boru Lontung',
        'gender' => 'P',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
    ]);
    $disconnectedSon = Person::factory()->create([
        'name' => 'Anak Boru Lontung',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $daughter->id,
    ]);
    $source = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'name' => 'Versi Utama',
    ]);
    $alternative = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'based_on_id' => $source->id,
        'name' => 'Versi Alternatif',
    ]);
    $rootNode = FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $son->id,
        'father_node_id' => $rootNode->id,
        'birth_order' => 2,
        'chain' => '1-2',
    ]);
    $daughterNode = FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $daughter->id,
        'father_node_id' => $rootNode->id,
        'birth_order' => 3,
        'chain' => '1-3',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $disconnectedSon->id,
        'father_node_id' => $daughterNode->id,
        'birth_order' => 1,
        'chain' => '1-3-1',
    ]);

    $this->actingAs($admin)
        ->get(route('tarombo.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/index')
            ->has('alternativeTrees', 1)
            ->where('alternativeTrees.0.id', $alternative->id)
            ->where('alternativeTrees.0.name', 'Versi Alternatif')
            ->where('alternativeTrees.0.rootPersonId', (string) $root->id)
            ->has('alternativeTrees.0.people', 2)
            ->where('alternativeTrees.0.people.1.id', (string) $son->id)
            ->where('alternativeTrees.0.people.1.parentId', (string) $root->id));
});

test('tarombo hides an unapproved alternative tree owned by another account', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create([
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    $source = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
    ]);
    $alternative = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
        'based_on_id' => $source->id,
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $root->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('tarombo.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/index')
            ->has('alternativeTrees', 0));
});
