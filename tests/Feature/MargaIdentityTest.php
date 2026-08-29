<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function margaIdentityNode(FamilyTree $tree, Person $person, string $chain): FamilyTreeNode
{
    return FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $person->id,
        'chain' => $chain,
    ]);
}

test('marga identity options only use the primary Si Raja Batak tree through generation eleven', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Si Raja Batak']);
    $generationEleven = Person::factory()->create(['name' => 'Generasi Sebelas']);
    $generationTwelve = Person::factory()->create(['name' => 'Generasi Dua Belas']);
    $alternativeOnly = Person::factory()->create(['name' => 'Khusus Alternatif']);
    $primary = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'is_primary' => true,
    ]);
    $alternative = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'based_on_id' => $primary->id,
        'is_primary' => false,
    ]);

    margaIdentityNode($primary, $root, '1');
    margaIdentityNode($primary, $generationEleven, '1-1-1-1-1-1-1-1-1-1-1');
    margaIdentityNode($primary, $generationTwelve, '1-1-1-1-1-1-1-1-1-1-1-1');
    margaIdentityNode($alternative, $alternativeOnly, '1-2');

    $this->actingAs($admin)
        ->get(route('marga.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('identityPersonOptions', 2)
            ->where('identityPersonOptions.0.id', $root->id)
            ->where('identityPersonOptions.1.id', $generationEleven->id));
});

test('an admin can save a valid marga identity and invalid tree nodes are rejected', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Si Raja Batak']);
    $validIdentity = Person::factory()->create(['name' => 'Raja Marga']);
    $invalidIdentity = Person::factory()->create(['name' => 'Di luar batas']);
    $primary = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'is_primary' => true,
    ]);
    margaIdentityNode($primary, $root, '1');
    margaIdentityNode($primary, $validIdentity, '1-1');
    margaIdentityNode($primary, $invalidIdentity, '1-1-1-1-1-1-1-1-1-1-1-1');

    $this->actingAs($admin)
        ->post(route('marga.store'), [
            'name' => 'Marga Valid',
            'identity_person_id' => $validIdentity->id,
        ])
        ->assertRedirect(route('marga.index'));

    expect(Marga::query()->where('name', 'Marga Valid')->value('identity_person_id'))
        ->toBe($validIdentity->id);

    $this->actingAs($admin)
        ->post(route('marga.store'), [
            'name' => 'Marga Tidak Valid',
            'identity_person_id' => $invalidIdentity->id,
        ])
        ->assertSessionHasErrors('identity_person_id');
});
