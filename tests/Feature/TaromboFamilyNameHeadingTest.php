<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an account tree sends the family name entered on its root branch', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id, 'created_by' => $user->id]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $root->id, 'name' => 'Keluarga Lama']);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id, 'family_name' => 'Keluarga Jay Silaban']);

    $this->actingAs($user)
        ->get(route('tarombo.fullscreen', ['view' => 'tree', 'family_tree' => $tree->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/fullscreen')
            ->where('familyName', 'Keluarga Jay Silaban'));

    $this->get(route('tarombo.index', ['family_tree' => $tree->id]))
        ->assertInertia(fn (Assert $page) => $page->where('familyName', 'Keluarga Jay Silaban'));
});

test('an account tree without a branch family name falls back to the tree name', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id, 'created_by' => $user->id]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $root->id, 'name' => 'Keluarga Borsak Junjungan']);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);

    $this->actingAs($user)
        ->get(route('tarombo.fullscreen', ['view' => 'tree', 'family_tree' => $tree->id]))
        ->assertInertia(fn (Assert $page) => $page->where('familyName', 'Keluarga Borsak Junjungan'));
});

test('a marga lower tree is headed by the family tree rooted at the marga identity', function () {
    $marga = Marga::factory()->create();
    $identity = Person::factory()->create(['marga_id' => $marga->id, 'gender' => 'L']);
    $marga->update(['identity_person_id' => $identity->id]);
    $staff = User::factory()->asAdmin()->create();
    $identityTree = FamilyTree::create(['user_id' => $staff->id, 'root_person_id' => $identity->id, 'name' => 'Keluarga Ambarita Raja']);
    FamilyTreeNode::create(['family_tree_id' => $identityTree->id, 'person_id' => $identity->id]);
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $this->actingAs($contributor)
        ->get(route('tarombo.fullscreen', ['view' => 'tree', 'marga_direction' => 'lower', 'marga_id' => $marga->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('margaTree.margaName', $marga->name)
            ->where('familyName', 'Keluarga Ambarita Raja'));

    $identityTree->nodes()->where('person_id', $identity->id)->update(['family_name' => 'Keluarga Besar Ambarita']);

    $this->get(route('tarombo.fullscreen', ['view' => 'tree', 'marga_direction' => 'lower', 'marga_id' => $marga->id]))
        ->assertInertia(fn (Assert $page) => $page->where('familyName', 'Keluarga Besar Ambarita'));

    $this->get(route('tarombo.fullscreen', ['view' => 'tree', 'marga_direction' => 'upper', 'marga_id' => $marga->id]))
        ->assertInertia(fn (Assert $page) => $page->where('familyName', null));
});

test('a marga without a family tree rooted at its identity keeps the default heading', function () {
    $marga = Marga::factory()->create();
    $identity = Person::factory()->create(['marga_id' => $marga->id, 'gender' => 'L']);
    $marga->update(['identity_person_id' => $identity->id]);
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $this->actingAs($contributor)
        ->get(route('tarombo.fullscreen', ['view' => 'tree', 'marga_direction' => 'lower', 'marga_id' => $marga->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('familyName', null));
});
