<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeAppendRequest;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function contributorAppendMargaTree(Marga $marga): array
{
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Raja Marga', 'gender' => 'L', 'marga_id' => $marga->id]);
    $marga->update(['identity_person_id' => $root->id]);
    $tree = FamilyTree::create([
        'user_id' => $admin->id, 'root_person_id' => $root->id, 'name' => 'Keluarga Raja Marga',
    ]);
    $tree->people()->attach($root->id);
    $node = FamilyTreeNode::create([
        'family_tree_id' => $tree->id, 'person_id' => $root->id, 'chain' => '1',
    ]);

    return compact('tree', 'root', 'node');
}

dataset('contributor roles', ['contributor_main', 'contributor_member']);

test('a contributor adds a member to their marga tree directly', function (string $role) {
    $marga = Marga::factory()->create();
    $contributor = User::factory()->create(['role' => $role, 'marga_id' => null]);
    $contributor->managedMargas()->attach($marga);
    ['tree' => $tree, 'root' => $root, 'node' => $node] = contributorAppendMargaTree($marga);

    $this->actingAs($contributor)
        ->get(route('family-trees.people.create', $tree))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/shared-tree-person-form')
            ->where('familyTree.requires_approval', false)
            ->has('fatherOptions', 1));

    $this->actingAs($contributor)
        ->post(route('family-trees.people.store', $tree), [
            'name' => 'Anak Kontributor', 'gender' => 'L', 'father_node_id' => $node->id,
        ])
        ->assertRedirect();

    $child = Person::query()->where('name', 'Anak Kontributor')->firstOrFail();
    expect($child->father_id)->toBe($root->id)
        ->and($tree->nodes()->where('person_id', $child->id)->exists())->toBeTrue()
        ->and(FamilyTreeAppendRequest::query()->exists())->toBeFalse();
})->with('contributor roles');

test('a contributor opens the marga tree through the branch entry', function (string $role) {
    $marga = Marga::factory()->create();
    $contributor = User::factory()->create(['role' => $role, 'marga_id' => null]);
    $contributor->managedMargas()->attach($marga);
    ['tree' => $tree, 'root' => $root] = contributorAppendMargaTree($marga);

    $this->actingAs($contributor)
        ->post(route('marga-branch-entries.store', $root))
        ->assertRedirect(route('family-trees.people.create', [
            'familyTree' => $tree,
            'father_person_id' => $root->id,
        ]));
})->with('contributor roles');

test('a contributor cannot add members to another marga tree', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $contributor = User::factory()->asMainContributor()->create(['marga_id' => $otherMarga->id]);
    ['tree' => $tree, 'root' => $root] = contributorAppendMargaTree($marga);

    $this->actingAs($contributor)->withHeader('Accept', 'application/json')
        ->get(route('family-trees.people.create', $tree))
        ->assertForbidden();
    $this->actingAs($contributor)->withHeader('Accept', 'application/json')
        ->post(route('marga-branch-entries.store', $root))
        ->assertForbidden();
});
