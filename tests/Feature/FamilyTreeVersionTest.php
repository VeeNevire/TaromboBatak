<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\FamilyTreeShare;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeVersionService;
use Inertia\Testing\AssertableInertia as Assert;

test('an alternative version can change a parent without changing its source version', function () {
    $user = User::factory()->create();
    $rajaLontung = Person::factory()->create(['name' => 'Raja Lontung']);
    $togaPandiangan = Person::factory()->create(['name' => 'Toga Pandiangan']);
    $rajaAmparhutala = Person::factory()->create(['name' => 'Raja Amparhutala']);
    $rajaHumirtap = Person::factory()->create(['name' => 'Raja Humirtap']);

    $versionOne = FamilyTree::create([
        'user_id' => $user->id,
        'name' => 'Versi 1',
        'root_person_id' => $rajaLontung->id,
    ]);

    $lontungNode = FamilyTreeNode::create([
        'family_tree_id' => $versionOne->id,
        'person_id' => $rajaLontung->id,
        'chain' => '1',
    ]);
    $pandianganNode = FamilyTreeNode::create([
        'family_tree_id' => $versionOne->id,
        'person_id' => $togaPandiangan->id,
        'father_node_id' => $lontungNode->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);
    $amparhutalaNode = FamilyTreeNode::create([
        'family_tree_id' => $versionOne->id,
        'person_id' => $rajaAmparhutala->id,
        'father_node_id' => $pandianganNode->id,
        'birth_order' => 1,
        'chain' => '1-1-1',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $versionOne->id,
        'person_id' => $rajaHumirtap->id,
        'father_node_id' => $amparhutalaNode->id,
        'birth_order' => 1,
        'chain' => '1-1-1-1',
    ]);

    $versionTwo = app(FamilyTreeVersionService::class)->duplicate($versionOne, $user, 'Versi 2');
    $amparhutalaInVersionTwo = $versionTwo->nodes()->where('person_id', $rajaAmparhutala->id)->firstOrFail();
    $lontungInVersionTwo = $versionTwo->nodes()->where('person_id', $rajaLontung->id)->firstOrFail();

    $amparhutalaInVersionTwo->update([
        'father_node_id' => $lontungInVersionTwo->id,
        'chain' => '1-2',
    ]);

    expect($versionTwo->based_on_id)->toBe($versionOne->id)
        ->and($versionTwo->nodes)->toHaveCount(4)
        ->and($amparhutalaInVersionTwo->fresh()->father_node_id)->toBe($lontungInVersionTwo->id)
        ->and($versionOne->nodes()->where('person_id', $rajaAmparhutala->id)->value('father_node_id'))->toBe($pandianganNode->id)
        ->and($versionOne->nodes()->where('person_id', $rajaHumirtap->id)->value('person_id'))->toBe($rajaHumirtap->id);
});

test('an alternative version inherits new primary branches until that branch is overridden', function () {
    $user = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Akar']);
    $child = Person::factory()->create(['name' => 'Anak Utama']);
    $newGrandchild = Person::factory()->create(['name' => 'Cucu Baru']);
    $source = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $root->id,
        'name' => 'Versi Utama',
        'is_primary' => true,
    ]);
    $rootNode = FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $root->id]);
    $childNode = FamilyTreeNode::create([
        'family_tree_id' => $source->id,
        'person_id' => $child->id,
        'father_node_id' => $rootNode->id,
    ]);
    $alternative = app(FamilyTreeVersionService::class)->duplicate($source, $user, 'Versi Alternatif');

    FamilyTreeNode::create([
        'family_tree_id' => $source->id,
        'person_id' => $newGrandchild->id,
        'father_node_id' => $childNode->id,
    ]);

    $this->actingAs($user)
        ->get(route('family-trees.show', $alternative))
        ->assertInertia(fn (Assert $page) => $page
            ->has('people', 3)
            ->where('people.2.id', (string) $newGrandchild->id)
            ->where('people.2.parentId', (string) $child->id));

    $alternativeRoot = $alternative->nodes()->where('person_id', $root->id)->firstOrFail();
    FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $newGrandchild->id,
        'father_node_id' => $alternativeRoot->id,
        'structure_overrides' => ['father_person_id' => $root->id],
    ]);

    $this->actingAs($user)
        ->get(route('family-trees.show', $alternative))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.2.parentId', (string) $root->id));
});

test('the existing family form updates an alternative version without changing global chains', function () {
    $user = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Si Raja Batak', 'gender' => 'L', 'chain' => '1']);
    $firstChild = Person::factory()->create([
        'name' => 'Anak Pertama',
        'gender' => 'L',
        'father_id' => $root->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);
    $secondChild = Person::factory()->create([
        'name' => 'Anak Kedua',
        'gender' => 'L',
        'father_id' => $root->id,
        'birth_order' => 2,
        'chain' => '1-2',
    ]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $root->id, 'name' => 'Versi Utama']);
    $rootNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id, 'chain' => '1']);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $firstChild->id, 'father_node_id' => $rootNode->id, 'birth_order' => 1, 'chain' => '1-1']);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $secondChild->id, 'father_node_id' => $rootNode->id, 'birth_order' => 2, 'chain' => '1-2']);

    $alternative = app(FamilyTreeVersionService::class)->duplicate($tree, $user, 'Versi Alternatif');

    $this->actingAs($user)
        ->put(route('people.update', ['person' => $root, 'version_tree' => $alternative->id]), [
            'name' => $root->name,
            'gender' => 'L',
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => [],
            'mothers' => [],
            'children' => [],
            'ownChildren' => [
                ['id' => $secondChild->id, 'name' => $secondChild->name, 'gender' => 'L'],
                ['id' => $firstChild->id, 'name' => $firstChild->name, 'gender' => 'L'],
            ],
        ])
        ->assertRedirect(route('people.show', ['person' => $root, 'version_tree' => $alternative->id]));

    expect($tree->nodes()->where('person_id', $firstChild->id)->value('birth_order'))->toBe(1)
        ->and($tree->nodes()->where('person_id', $secondChild->id)->value('birth_order'))->toBe(2)
        ->and($alternative->nodes()->where('person_id', $secondChild->id)->value('birth_order'))->toBe(1)
        ->and($alternative->nodes()->where('person_id', $firstChild->id)->value('birth_order'))->toBe(2)
        ->and($alternative->nodes()->where('person_id', $secondChild->id)->firstOrFail()->structure_overrides)
        ->toMatchArray(['birth_order' => 1])
        ->and($firstChild->fresh()->chain)->toBe('1-1')
        ->and($secondChild->fresh()->chain)->toBe('1-2');
});

test('editing a member from a tree modal retains and updates that tree parent', function () {
    $admin = User::factory()->asAdmin()->create();
    $globalFather = Person::factory()->create(['name' => 'Ayah Global']);
    $treeFather = Person::factory()->create(['name' => 'Ayah Pada Silsilah']);
    $replacementFather = Person::factory()->create(['name' => 'Ayah Pengganti']);
    $child = Person::factory()->create([
        'name' => 'Anak Pada Silsilah',
        'gender' => 'L',
        'father_id' => $globalFather->id,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $treeFather->id,
        'name' => 'Silsilah Modal',
    ]);
    $treeFatherNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $treeFather->id,
    ]);
    $replacementFatherNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $replacementFather->id,
    ]);
    $childNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $child->id,
        'father_node_id' => $treeFatherNode->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('people.edit', ['person' => $child, 'version_tree' => $tree->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/form')
            ->where('person.father.id', $treeFather->id)
            ->where('person.father.name', $treeFather->name));

    $this->actingAs($admin)
        ->put(route('people.update', ['person' => $child, 'version_tree' => $tree->id]), [
            'name' => $child->name,
            'gender' => $child->gender,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => [
                'id' => $replacementFather->id,
                'name' => $replacementFather->name,
            ],
            'mothers' => [],
            'children' => [],
            'ownChildren' => [],
        ])
        ->assertRedirect(route('people.show', ['person' => $child, 'version_tree' => $tree->id]));

    expect($childNode->fresh()->father_node_id)->toBe($replacementFatherNode->id)
        ->and($child->fresh()->father_id)->toBe($globalFather->id);
});

test('an owner can open their family version when its root is a locked ancestor', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id, 'created_by' => $user->id]);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $root->id,
        'name' => 'Keluarga Milik Saya',
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $user->id,
        'matched_father_id' => $root->id,
        'subject_person_id' => $root->id,
        'family_tree_id' => $tree->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('people.edit', $root))
        ->assertForbidden();

    $this->get(route('people.edit', ['person' => $root, 'version_tree' => $tree->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/form')
            ->where('selectedVersionId', $tree->id)
            ->where('selectedVersionName', $tree->name));
});

test('editing a family version requires ownership even when the person is editable', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $person = Person::factory()->create(['marga_id' => $marga->id, 'created_by' => $user->id]);
    $tree = FamilyTree::create([
        'user_id' => User::factory()->create()->id,
        'root_person_id' => $person->id,
        'name' => 'Keluarga Pengguna Lain',
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $person->id]);

    $this->actingAs($user)->get(route('people.edit', $person))->assertOk();
    $this->get(route('people.edit', ['person' => $person, 'version_tree' => $tree->id]))
        ->assertForbidden();
});

test('an unavailable version context never falls back to the main family form', function () {
    $user = User::factory()->asAdmin()->create();
    $person = Person::factory()->create();
    $otherRoot = Person::factory()->create();
    $otherTree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $otherRoot->id,
        'name' => 'Versi Orang Lain',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $otherTree->id,
        'person_id' => $otherRoot->id,
    ]);

    $this->actingAs($user)
        ->get(route('people.edit', ['person' => $person, 'version_tree' => $otherTree->id]))
        ->assertNotFound();
});

test('a family tree page reads parentage from its own version nodes', function () {
    $admin = User::factory()->asAdmin()->create();
    $rajaLontung = Person::factory()->create(['name' => 'Raja Lontung']);
    $togaPandiangan = Person::factory()->create(['name' => 'Toga Pandiangan']);
    $rajaAmparhutala = Person::factory()->create(['name' => 'Raja Amparhutala']);

    $version = FamilyTree::create([
        'user_id' => $admin->id,
        'name' => 'Versi Alternatif',
        'root_person_id' => $rajaLontung->id,
    ]);
    $lontungNode = FamilyTreeNode::create(['family_tree_id' => $version->id, 'person_id' => $rajaLontung->id]);
    FamilyTreeNode::create([
        'family_tree_id' => $version->id,
        'person_id' => $togaPandiangan->id,
        'father_node_id' => $lontungNode->id,
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $version->id,
        'person_id' => $rajaAmparhutala->id,
        'father_node_id' => $lontungNode->id,
        'birth_order' => 2,
        'chain' => '1-2',
    ]);

    $this->actingAs($admin)
        ->get(route('family-trees.show', $version))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/silsilah')
            ->where('familyTree.rootPersonId', $rajaLontung->id)
            ->where('people.2.parentId', (string) $rajaLontung->id)
            ->where('people.2.birthOrder', 2)
            ->where('people.2.chain', '1-2'));
});

test('a user can only view an admin family tree after accepting a share', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $outsideRoot = Person::factory()->create(['marga_id' => $otherMarga->id]);
    $visibleRoot = Person::factory()->create([
        'marga_id' => $marga->id,
        'father_id' => $outsideRoot->id,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $outsideRoot->id,
        'name' => 'Silsilah Admin',
    ]);
    $outsideNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $outsideRoot->id,
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $visibleRoot->id,
        'father_node_id' => $outsideNode->id,
    ]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $admin->id,
        'matched_father_id' => $visibleRoot->id,
        'subject_person_id' => $visibleRoot->id,
        'family_tree_id' => $tree->id,
    ]);

    $this->actingAs($user)
        ->get(route('family-trees.show', $tree))
        ->assertForbidden();

    FamilyTreeShare::create([
        'family_tree_id' => $tree->id,
        'sender_id' => $admin->id,
        'recipient_id' => $user->id,
        'status' => FamilyTreeShare::STATUS_ACCEPTED,
        'responded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('family-trees.show', $tree))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/silsilah')
            ->where('canEditFamilyTree', false)
            ->has('people', 1)
            ->where('people.0.id', (string) $visibleRoot->id)
            ->where('people.0.parentId', null)
            ->where('person.id', (string) $visibleRoot->id));

    $this->actingAs($user)
        ->get(route('family-trees.edit', $tree))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('family-trees.duplicate', $tree))
        ->assertForbidden();
});

test('a user cannot view an unapproved family tree owned by another account', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);

    $this->actingAs($viewer)
        ->get(route('family-trees.show', $tree))
        ->assertForbidden();
});

test('a user cannot view an admin family tree from another marga', function () {
    $admin = User::factory()->asAdmin()->create();
    $userMarga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($userMarga->id)->create();
    $root = Person::factory()->create(['marga_id' => $otherMarga->id]);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
    ]);

    $this->actingAs($user)
        ->get(route('family-trees.show', $tree))
        ->assertForbidden();
});

test('a person silsilah link opens the only matching family tree version', function () {
    $admin = User::factory()->asAdmin()->create();
    $person = Person::factory()->create(['name' => 'Raja Humirtap']);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $person->id,
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $person->id]);

    $this->actingAs($admin)
        ->get(route('people.silsilah', $person))
        ->assertRedirect(route('family-trees.show', $tree));
});

test('a person silsilah link asks the user to choose when multiple versions contain them', function () {
    $admin = User::factory()->asAdmin()->create();
    $person = Person::factory()->create(['name' => 'Raja Humirtap']);

    foreach (['Versi 1', 'Versi 2'] as $name) {
        $tree = FamilyTree::create([
            'user_id' => $admin->id,
            'name' => $name,
            'root_person_id' => $person->id,
        ]);
        FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $person->id]);
    }

    $this->actingAs($admin)
        ->get(route('people.silsilah', $person))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/tree-selector')
            ->where('person.name', 'Raja Humirtap')
            ->has('familyTrees', 2));
});

test('a tree owner can duplicate a version from its history action', function () {
    $user = User::factory()->create();
    $root = Person::factory()->create(['name' => 'Raja Lontung']);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'name' => 'Versi 1',
        'root_person_id' => $root->id,
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);

    $this->actingAs($user)
        ->post(route('family-trees.duplicate', $tree), [
            'alternative_name' => 'Cabang Raja Lontung di Medan',
        ])
        ->assertRedirect();

    $copy = FamilyTree::query()->where('based_on_id', $tree->id)->firstOrFail();

    expect($copy->user_id)->toBe($user->id)
        ->and($copy->name)->toBe('Cabang Raja Lontung di Medan')
        ->and($copy->nodes()->value('person_id'))->toBe($root->id);
});

test('a tree owner can rename a family tree without changing its nodes', function () {
    $user = User::factory()->create();
    $root = Person::factory()->create(['name' => 'Raja Lontung']);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $root->id,
        'name' => 'Nama Lama',
    ]);
    $node = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);

    $this->actingAs($user)
        ->patch(route('family-trees.name.update', $tree), [
            'name' => 'Nama Baru',
        ])
        ->assertRedirect();

    expect($tree->fresh()->name)->toBe('Nama Baru')
        ->and($node->fresh()->chain)->toBe('1')
        ->and($node->fresh()->family_tree_id)->toBe($tree->id);
});

test('updating an alternative tree relationship does not alter its source tree', function () {
    $user = User::factory()->create();
    $root = Person::factory()->create(['name' => 'Raja Lontung']);
    $intermediate = Person::factory()->create(['name' => 'Toga Pandiangan']);
    $child = Person::factory()->create(['name' => 'Raja Amparhutala']);
    $source = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $root->id]);
    $rootNode = FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $root->id]);
    $intermediateNode = FamilyTreeNode::create([
        'family_tree_id' => $source->id,
        'person_id' => $intermediate->id,
        'father_node_id' => $rootNode->id,
        'birth_order' => 1,
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $source->id,
        'person_id' => $child->id,
        'father_node_id' => $intermediateNode->id,
        'birth_order' => 1,
    ]);
    $alternative = app(FamilyTreeVersionService::class)->duplicate($source, $user, 'Versi 2');
    $alternativeRoot = $alternative->nodes()->where('person_id', $root->id)->firstOrFail();
    $alternativeIntermediate = $alternative->nodes()->where('person_id', $intermediate->id)->firstOrFail();
    $alternativeChild = $alternative->nodes()->where('person_id', $child->id)->firstOrFail();

    $this->actingAs($user)
        ->put(route('family-trees.update', $alternative), [
            'entries' => [
                ['id' => $alternativeRoot->id, 'father_node_id' => null, 'birth_order' => null],
                ['id' => $alternativeIntermediate->id, 'father_node_id' => $alternativeRoot->id, 'birth_order' => 1],
                ['id' => $alternativeChild->id, 'father_node_id' => $alternativeRoot->id, 'birth_order' => 2],
            ],
        ])
        ->assertRedirect(route('family-trees.show', $alternative));

    expect($alternativeChild->fresh()->father_node_id)->toBe($alternativeRoot->id)
        ->and($alternativeChild->fresh()->chain)->toBe('1-2')
        ->and($source->nodes()->where('person_id', $child->id)->value('father_node_id'))->toBe($intermediateNode->id);
});

test('opening a selected version shows its jejak keluarga entries without creating a copy', function () {
    $user = User::factory()->create();
    $root = Person::factory()->create(['name' => 'Raja Lontung']);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $root->id,
        'name' => 'Versi Pilihan',
    ]);
    $node = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);

    $this->actingAs($user)
        ->get(route('family-trees.edit', $tree))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/tree-editor')
            ->where('familyTree.id', $tree->id)
            ->where('familyTree.name', 'Versi Pilihan')
            ->has('entries', 1)
            ->where('entries.0.id', $node->id)
            ->where('entries.0.personId', $root->id));

    expect(FamilyTree::query()->count())->toBe(1)
        ->and(FamilyTree::query()->whereNotNull('based_on_id')->count())->toBe(0);
});

test('a family form can append a new child only to the selected alternative', function (string $group) {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['marga_id' => $marga->id, 'created_by' => $user->id]);
    $first = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $father->id, 'created_by' => $user->id]);
    $second = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $father->id, 'created_by' => $user->id]);
    $source = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $father->id, 'name' => 'Utama']);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $father->id, 'chain' => '1']);
    foreach ([$first, $second] as $index => $child) {
        FamilyTreeNode::create(['family_tree_id' => $source->id, 'person_id' => $child->id, 'father_node_id' => $fatherNode->id, 'birth_order' => $index + 1]);
    }
    $alternative = app(FamilyTreeVersionService::class)->duplicate($source, $user, 'Alternatif');
    $focus = $group === 'ownChildren' ? $father : $first;
    $payload = [
        'name' => $focus->name,
        'father' => $group === 'ownChildren' ? [] : ['id' => $father->id, 'name' => $father->name],
        'birth_order' => 1,
        $group => [
            ['id' => $first->id, 'name' => $first->name],
            ['id' => $second->id, 'name' => $second->name],
            ['name' => 'Anak Ketiga Saya Damanik', 'gender' => 'L'],
        ],
    ];
    $this->actingAs($user)
        ->put(route('people.update', ['person' => $focus, 'version_tree' => $alternative->id]), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('people.show', ['person' => $focus, 'version_tree' => $alternative->id]));

    $child = Person::query()->where('name', 'Anak Ketiga Saya Damanik')->sole();
    $node = $alternative->nodes()->where('person_id', $child->id)->sole();
    expect($node->father_node_id)->toBe($alternative->nodes()->where('person_id', $father->id)->value('id'))
        ->and($node->birth_order)->toBe(3)
        ->and($node->chain)->toBe('1-3')
        ->and($child->created_by)->toBe($user->id)
        ->and($child->marga_id)->toBe($marga->id)
        ->and($alternative->people()->whereKey($child->id)->exists())->toBeTrue()
        ->and($source->nodes()->count())->toBe(3)
        ->and($source->people()->whereKey($child->id)->exists())->toBeFalse();

    $this->get(route('people.edit', ['person' => $focus, 'version_tree' => $alternative->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.'.$group.'.2.name', $child->name));
})->with(['children', 'ownChildren']);

test('a failed version update rolls back newly appended children', function () {
    $user = User::factory()->asAdmin()->create();
    $root = Person::factory()->create();
    $outsider = Person::factory()->create();
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $root->id]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);
    $this->actingAs($user)->put(route('people.update', ['person' => $root, 'version_tree' => $tree->id]), [
        'name' => $root->name,
        'ownChildren' => [['name' => 'Must Roll Back'], ['id' => $outsider->id, 'name' => $outsider->name]],
    ])->assertSessionHasErrors('ownChildren.1.id');

    expect(Person::query()->where('name', 'Must Roll Back')->exists())->toBeFalse()
        ->and($tree->nodes()->count())->toBe(1)
        ->and($tree->people()->count())->toBe(0);
});

test('a version form saves wives and their fathers for the selected father', function () {
    $user = User::factory()->asAdmin()->create();
    $father = Person::factory()->create(['name' => 'Ayah Darma', 'gender' => 'L']);
    $focus = Person::factory()->create([
        'name' => 'Darma',
        'gender' => 'L',
        'father_id' => $father->id,
    ]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $father->id]);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $focus->id,
        'father_node_id' => $fatherNode->id,
    ]);

    $this->actingAs($user)->put(route('people.update', [
        'person' => $focus,
        'version_tree' => $tree->id,
    ]), [
        'name' => $focus->name,
        'gender' => 'L',
        'father' => ['id' => $father->id, 'name' => $father->name],
        'mothers' => [[
            'name' => 'Istri Darma',
            'marga_id' => Marga::factory()->create()->id,
            'father_name' => 'Ayah Istri Darma',
        ]],
    ])->assertRedirect();

    $wife = Person::query()->where('name', 'Istri Darma')->firstOrFail();
    expect($father->wives()->whereKey($wife->id)->exists())->toBeTrue()
        ->and($wife->father?->name)->toBe('Ayah Istri Darma');
});
