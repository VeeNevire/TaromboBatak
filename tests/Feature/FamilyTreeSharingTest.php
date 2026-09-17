<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\FamilyTreeAppendRequest;
use App\Models\FamilyTreeNode;
use App\Models\FamilyTreeShare;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Notifications\FamilyTreeAppendSubmitted;
use Illuminate\Support\Facades\Notification;
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

test('an accepted recipient submits a new member for the owner to approve', function () {
    Notification::fake();
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

    expect(Person::query()->where('name', 'Anak Tambahan')->exists())->toBeFalse();

    $appendRequest = FamilyTreeAppendRequest::query()->firstOrFail();
    expect($appendRequest->requester_id)->toBe($recipient->id)
        ->and($appendRequest->family_tree_id)->toBe($tree->id)
        ->and($appendRequest->status)->toBe(FamilyTreeAppendRequest::STATUS_PENDING)
        ->and($appendRequest->payload['name'])->toBe('Anak Tambahan');
    Notification::assertSentTo($owner, FamilyTreeAppendSubmitted::class);

    $this->actingAs($owner)
        ->post(route('family-tree-append-requests.approve', $appendRequest))
        ->assertRedirect();

    $child = Person::query()->where('name', 'Anak Tambahan')->firstOrFail();
    expect($child->created_by)->toBe($recipient->id)
        ->and($child->father_id)->toBe($root->id)
        ->and($child->marga_id)->toBe($marga->id)
        ->and($tree->nodes()->where('person_id', $child->id)->value('father_node_id'))->toBe($node->id)
        ->and($tree->people()->whereKey($child->id)->exists())->toBeTrue()
        ->and($root->fresh()->bio)->toBe('Data lama tidak boleh berubah')
        ->and($appendRequest->fresh()->status)->toBe(FamilyTreeAppendRequest::STATUS_APPROVED)
        ->and($appendRequest->fresh()->reviewed_by)->toBe($owner->id);

    expect(FamilyTreeActivity::query()
        ->where('family_tree_id', $tree->id)
        ->where('action', 'added')
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('family-tree-activities.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('family-tree-activities/index')
            ->has('activities', 1));
});

test('the shared member form offers every male father in the active tree', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree, 'node' => $rootNode] = sharingTree($owner, $marga);
    $leaf = Person::factory()->create([
        'name' => 'Calon Ayah Ujung',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $rootNode->person_id,
    ]);
    $leafNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $leaf->id,
        'father_node_id' => $rootNode->id,
        'chain' => '1-1',
    ]);
    // A visually empty branch may still have children recorded elsewhere.
    $fatherElsewhere = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id]);
    $fatherElsewhereNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $fatherElsewhere->id,
        'father_node_id' => $rootNode->id,
    ]);
    Person::factory()->create(['father_id' => $fatherElsewhere->id]);
    $woman = Person::factory()->create(['gender' => 'P', 'marga_id' => $marga->id]);
    $womanNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $woman->id,
    ]);
    FamilyTreeShare::create([
        'family_tree_id' => $tree->id,
        'sender_id' => $owner->id,
        'recipient_id' => $recipient->id,
        'status' => FamilyTreeShare::STATUS_ACCEPTED,
    ]);

    $this->actingAs($recipient)
        ->get(route('family-trees.people.create', $tree))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fatherOptions', [
                ['id' => $fatherElsewhereNode->id, 'name' => $fatherElsewhere->name, 'chain' => null],
                ['id' => $rootNode->id, 'name' => 'Raja Sharing', 'chain' => '1'],
                ['id' => $leafNode->id, 'name' => 'Calon Ayah Ujung', 'chain' => '1-1'],
            ]));

    $this->actingAs($recipient)
        ->get(route('family-trees.people.create', [
            'familyTree' => $tree,
            'father_person_id' => $rootNode->person_id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initialFatherNodeId', $rootNode->id)
            ->has('fatherOptions', 3));

    $this->get(route('family-trees.people.create', [
        'familyTree' => $tree,
        'father_person_id' => $fatherElsewhere->id,
    ]))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('initialFatherNodeId', $fatherElsewhereNode->id)
        ->has('fatherOptions', 3));

    $this->actingAs($owner)->post(route('family-trees.people.store', $tree), [
        'name' => 'Anak Cabang Baru',
        'gender' => 'L',
        'father_node_id' => $fatherElsewhereNode->id,
    ])->assertRedirect(route('family-trees.show', $tree));

    $newChild = Person::query()->where('name', 'Anak Cabang Baru')->firstOrFail();
    expect($newChild->father_id)->toBe($fatherElsewhere->id)
        ->and($tree->nodes()->where('person_id', $newChild->id)->value('father_node_id'))
        ->toBe($fatherElsewhereNode->id);

    $this->actingAs($owner)->post(route('family-trees.people.store', $tree), [
        'name' => 'Tidak Boleh Berayah Perempuan',
        'father_node_id' => $womanNode->id,
    ])->assertSessionHasErrors('father_node_id');
});

test('adding a child from a known father preselects his node and joins the existing tree', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree, 'root' => $father, 'node' => $fatherNode] = sharingTree($owner, $marga);
    $firstChild = Person::factory()->create([
        'name' => 'Anak Pertama',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
    ]);
    $tree->people()->attach($firstChild->id);
    $firstChildNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $firstChild->id,
        'father_node_id' => $fatherNode->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);

    $this->actingAs($owner)->get(route('family-trees.people.create', [
        'familyTree' => $tree,
        'father_person_id' => $firstChild->id,
    ]))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('initialFatherNodeId', $firstChildNode->id)
        ->where('fatherOptions', fn ($options) => collect($options)->contains('id', $firstChildNode->id)));

    $this->actingAs($owner)->post(route('family-trees.people.store', $tree), [
        'name' => 'Anak Kedua',
        'gender' => 'L',
        'father_node_id' => $firstChildNode->id,
    ])->assertRedirect(route('family-trees.show', $tree));

    $secondChild = Person::query()->where('name', 'Anak Kedua')->firstOrFail();
    expect($secondChild->father_id)->toBe($firstChild->id)
        ->and($secondChild->birth_order)->toBe(1)
        ->and($tree->nodes()->where('person_id', $secondChild->id)->value('father_node_id'))->toBe($firstChildNode->id)
        ->and(FamilyTree::query()->count())->toBe(1);
});

test('the shared member form limits mothers to the selected father wives', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree, 'root' => $father, 'node' => $fatherNode] = sharingTree($owner, $marga);
    $wife = Person::factory()->create(['name' => 'Istri Raja Sharing', 'gender' => 'P']);
    $unrelatedWoman = Person::factory()->create(['name' => 'Bukan Istri', 'gender' => 'P']);

    $father->wives()->attach($wife->id, ['position' => 1]);

    $wifeNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $wife->id,
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $unrelatedWoman->id,
    ]);
    $tree->people()->attach([$wife->id, $unrelatedWoman->id]);
    FamilyTreeShare::create([
        'family_tree_id' => $tree->id,
        'sender_id' => $owner->id,
        'recipient_id' => $recipient->id,
        'status' => FamilyTreeShare::STATUS_ACCEPTED,
    ]);

    $this->actingAs($recipient)
        ->get(route('family-trees.people.create', $tree))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("motherOptionsByFather.{$fatherNode->id}", [[
                'id' => $wifeNode->id,
                'name' => 'Istri Raja Sharing',
                'chain' => null,
            ]]));
});

test('a shared member cannot select a mother unrelated to the selected father', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();
    ['tree' => $tree, 'node' => $fatherNode] = sharingTree($owner, $marga);
    $unrelatedWoman = Person::factory()->create(['gender' => 'P']);
    $unrelatedNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $unrelatedWoman->id,
    ]);
    $tree->people()->attach($unrelatedWoman->id);
    FamilyTreeShare::create([
        'family_tree_id' => $tree->id,
        'sender_id' => $owner->id,
        'recipient_id' => $recipient->id,
        'status' => FamilyTreeShare::STATUS_ACCEPTED,
    ]);

    $this->actingAs($recipient)
        ->post(route('family-trees.people.store', $tree), [
            'name' => 'Anak Baru',
            'gender' => 'L',
            'father_node_id' => $fatherNode->id,
            'mother_node_id' => $unrelatedNode->id,
        ])
        ->assertSessionHasErrors('mother_node_id');
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
        ->post(route('people.family-version.duplicate', $tree->root_person_id))->assertUnprocessable();
    $this->actingAs($recipient)->withHeader('Accept', 'application/json')
        ->post(route('family-trees.shares.store', $tree), ['recipient_id' => $thirdUser->id])->assertForbidden();
    $this->actingAs($recipient)->withHeader('Accept', 'application/json')
        ->get(route('people.edit', $tree->root_person_id))->assertForbidden();
    $this->actingAs($recipient)
        ->get(route('people.show', $tree->root_person_id))->assertSuccessful();

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
