<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\FamilyTreeShare;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sharingTree(User $owner, Marga $marga): array
{
    $root = Person::factory()->create([
        'name' => 'Raja Sharing', 'gender' => 'L', 'marga_id' => $marga->id,
        'created_by' => $owner->id, 'bio' => 'Data lama tidak boleh berubah',
    ]);
    $tree = FamilyTree::create([
        'user_id' => $owner->id, 'root_person_id' => $root->id, 'name' => 'Keluarga Raja Sharing',
    ]);
    $tree->people()->attach($root->id);
    $node = FamilyTreeNode::create([
        'family_tree_id' => $tree->id, 'person_id' => $root->id, 'chain' => '1',
    ]);

    return compact('tree', 'root', 'node');
}

test('an owner can invite another account from the same marga', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree] = sharingTree($owner, $marga);

    $this->actingAs($owner)
        ->post(route('family-trees.shares.store', $tree), ['recipient_id' => $recipient->id])
        ->assertRedirect();

    $share = FamilyTreeShare::query()->first();
    expect($share)->not->toBeNull()
        ->and($share->sender_id)->toBe($owner->id)
        ->and($share->recipient_id)->toBe($recipient->id)
        ->and($share->status)->toBe(FamilyTreeShare::STATUS_PENDING);
});

test('a regular owner cannot share a tree outside their marga', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($otherMarga->id)->create();
    ['tree' => $tree] = sharingTree($owner, $marga);

    $this->actingAs($owner)
        ->post(route('family-trees.shares.store', $tree), ['recipient_id' => $recipient->id])
        ->assertSessionHasErrors('recipient_id');

    expect(FamilyTreeShare::query()->exists())->toBeFalse();
});

test('a pending recipient must accept before opening the shared tree', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree] = sharingTree($owner, $marga);
    $share = FamilyTreeShare::create([
        'family_tree_id' => $tree->id, 'sender_id' => $owner->id, 'recipient_id' => $recipient->id,
    ]);

    $this->actingAs($recipient)->withHeader('Accept', 'application/json')
        ->get(route('family-trees.people.create', $tree))->assertForbidden();

    $this->actingAs($recipient)
        ->patch(route('family-tree-shares.update', $share), ['status' => 'accepted'])
        ->assertRedirect();

    expect($share->fresh()->status)->toBe(FamilyTreeShare::STATUS_ACCEPTED)
        ->and($share->fresh()->responded_at)->not->toBeNull();

    $this->actingAs($recipient)->get(route('family-trees.people.create', $tree))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/shared-tree-person-form')->where('familyTree.id', $tree->id));

    $this->actingAs($recipient)->get(route('people.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('familyTrees.0.id', $tree->id)
            ->where('familyTrees.0.access', 'shared')
            ->where('familyTrees.0.can_manage', false)
            ->where('familyTrees.0.can_append', true));
});

test('an accepted recipient can only append a new member to the shared tree', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree, 'root' => $root, 'node' => $node] = sharingTree($owner, $marga);
    FamilyTreeShare::create([
        'family_tree_id' => $tree->id, 'sender_id' => $owner->id,
        'recipient_id' => $recipient->id, 'status' => FamilyTreeShare::STATUS_ACCEPTED,
        'responded_at' => now(),
    ]);

    $this->actingAs($recipient)->post(route('family-trees.people.store', $tree), [
        'name' => 'Anak Tambahan', 'gender' => 'L', 'father_node_id' => $node->id,
        'birth_order' => 2, 'birth_year' => '2001', 'bio' => 'Ditambahkan kolaborator',
    ])->assertRedirect(route('family-trees.show', $tree));

    $child = Person::query()->where('name', 'Anak Tambahan')->firstOrFail();
    expect($child->created_by)->toBe($recipient->id)
        ->and($child->father_id)->toBe($root->id)
        ->and($child->marga_id)->toBe($marga->id)
        ->and($tree->nodes()->where('person_id', $child->id)->value('father_node_id'))->toBe($node->id)
        ->and($tree->people()->whereKey($child->id)->exists())->toBeTrue()
        ->and($root->fresh()->bio)->toBe('Data lama tidak boleh berubah');
});

test('a shared recipient cannot manage duplicate or reshare the tree', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    $thirdUser = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree] = sharingTree($owner, $marga);
    FamilyTreeShare::create([
        'family_tree_id' => $tree->id, 'sender_id' => $owner->id,
        'recipient_id' => $recipient->id, 'status' => FamilyTreeShare::STATUS_ACCEPTED,
    ]);

    $this->actingAs($recipient)->withHeader('Accept', 'application/json')
        ->get(route('family-trees.edit', $tree))->assertForbidden();
    $this->actingAs($recipient)->withHeader('Accept', 'application/json')
        ->post(route('family-trees.duplicate', $tree))->assertForbidden();
    $this->actingAs($recipient)->withHeader('Accept', 'application/json')
        ->post(route('family-trees.shares.store', $tree), ['recipient_id' => $thirdUser->id])->assertForbidden();

    expect(FamilyTree::query()->count())->toBe(1)
        ->and(FamilyTreeShare::query()->count())->toBe(1);
});

test('a recipient cannot attach a node from another tree', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree] = sharingTree($owner, $marga);
    ['node' => $foreignNode] = sharingTree($owner, $marga);
    FamilyTreeShare::create([
        'family_tree_id' => $tree->id, 'sender_id' => $owner->id,
        'recipient_id' => $recipient->id, 'status' => FamilyTreeShare::STATUS_ACCEPTED,
    ]);

    $this->actingAs($recipient)->post(route('family-trees.people.store', $tree), [
        'name' => 'Tidak Boleh Masuk', 'father_node_id' => $foreignNode->id,
    ])->assertSessionHasErrors('father_node_id');

    expect(Person::query()->where('name', 'Tidak Boleh Masuk')->exists())->toBeFalse();
});
