<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
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

test('a user can view an admin family tree from their marga without edit actions', function () {
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
        ->post(route('family-trees.duplicate', $tree))
        ->assertRedirect();

    $copy = FamilyTree::query()->where('based_on_id', $tree->id)->firstOrFail();

    expect($copy->user_id)->toBe($user->id)
        ->and($copy->name)->toBe('Versi 1 - Versi alternatif')
        ->and($copy->nodes()->value('person_id'))->toBe($root->id);
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
