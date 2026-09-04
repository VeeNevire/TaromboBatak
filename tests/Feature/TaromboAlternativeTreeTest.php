<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\MargaAccessRequest;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('tarombo defaults to the signed in accounts primary family tree', function () {
    $owner = User::factory()->asAdmin()->create();
    $primaryRoot = Person::factory()->create(['name' => 'Akar Utama']);
    $otherRoot = Person::factory()->create(['name' => 'Akar Lain']);
    $primary = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $primaryRoot->id,
        'name' => 'Silsilah Utama',
        'is_primary' => true,
    ]);
    FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $otherRoot->id,
        'name' => 'Silsilah Lain',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $primary->id,
        'person_id' => $primaryRoot->id,
        'chain' => '1',
    ]);

    $this->actingAs($owner)
        ->get(route('tarombo.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedFamilyTreeId', $primary->id)
            ->has('selectedTreePeople', 1)
            ->where('selectedTreePeople.0.id', (string) $primaryRoot->id));

    $this->actingAs($owner)
        ->get(route('tarombo.fullscreen', ['view' => 'tree']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedFamilyTreeId', $primary->id)
            ->has('selectedTreePeople', 1));
});

test('tarombo defaults to the most recently updated account family tree when no primary exists', function () {
    $owner = User::factory()->asAdmin()->create();
    $olderRoot = Person::factory()->create();
    $newerRoot = Person::factory()->create();
    $older = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $olderRoot->id,
        'name' => 'Silsilah Lama',
        'updated_at' => now()->subDay(),
    ]);
    $newer = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $newerRoot->id,
        'name' => 'Silsilah Baru',
        'updated_at' => now(),
    ]);
    FamilyTreeNode::create(['family_tree_id' => $older->id, 'person_id' => $olderRoot->id, 'chain' => '1']);
    FamilyTreeNode::create(['family_tree_id' => $newer->id, 'person_id' => $newerRoot->id, 'chain' => '1']);

    $this->actingAs($owner)
        ->get(route('tarombo.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedFamilyTreeId', $newer->id)
            ->where('selectedTreePeople.0.id', (string) $newerRoot->id));
});

test('tarombo keeps the default tree when the account has no family tree', function () {
    $owner = User::factory()->asAdmin()->create();

    $this->actingAs($owner)
        ->get(route('tarombo.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedFamilyTreeId', null)
            ->where('selectedTreePeople', null));
});

test('tarombo includes female branches from alternative tree versions for the vertical toggle', function () {
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
            ->has('alternativeTrees.0.people', 4)
            ->where('alternativeTrees.0.people.1.id', (string) $son->id)
            ->where('alternativeTrees.0.people.1.parentId', (string) $root->id)
            ->where('alternativeTrees.0.people.2.id', (string) $daughter->id)
            ->where('alternativeTrees.0.people.2.gender', 'P')
            ->where('alternativeTrees.0.people.3.id', (string) $disconnectedSon->id)
            ->where('alternativeTrees.0.people.3.parentId', (string) $daughter->id));
});

test('a marga account receives descendants from its female branch without receiving unrelated margas', function () {
    $marga = Marga::factory()->create();
    $spouseMarga = Marga::factory()->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id]);
    $daughter = Person::factory()->create([
        'gender' => 'P',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
    ]);
    $grandson = Person::factory()->create([
        'gender' => 'L',
        'marga_id' => $spouseMarga->id,
        'father_id' => $daughter->id,
    ]);
    $greatGranddaughter = Person::factory()->create([
        'gender' => 'P',
        'marga_id' => $spouseMarga->id,
        'father_id' => $grandson->id,
    ]);
    $unrelated = Person::factory()->create([
        'gender' => 'L',
        'marga_id' => $spouseMarga->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('tarombo.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people) => collect($people)->pluck('id')->all() === [
                (string) $root->id,
                (string) $daughter->id,
                (string) $grandson->id,
                (string) $greatGranddaughter->id,
            ]));

    expect($unrelated->id)->not->toBeIn([$root->id, $daughter->id, $grandson->id, $greatGranddaughter->id]);
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

test('approved marga viewers receive approved alternative descendants for the marga being opened', function () {
    $accountMarga = Marga::factory()->create(['name' => 'Sagala']);
    $viewedMarga = Marga::factory()->create(['name' => 'Silaban']);
    $owner = User::factory()->withMarga($viewedMarga->id)->create();
    $viewer = User::factory()->withMarga($accountMarga->id)->create();
    $root = Person::factory()->create([
        'name' => 'Borsak Junjungan',
        'gender' => 'L',
        'marga_id' => $viewedMarga->id,
    ]);
    $child = Person::factory()->create([
        'name' => 'Keturunan Alternatif',
        'gender' => 'L',
        'marga_id' => $viewedMarga->id,
    ]);
    $viewedMarga->update(['identity_person_id' => $root->id]);

    MargaAccessRequest::create([
        'requester_id' => $viewer->id,
        'marga_id' => $viewedMarga->id,
        'status' => MargaAccessRequest::STATUS_APPROVED,
    ]);

    $source = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
        'name' => 'Versi Utama Silaban',
    ]);
    $alternative = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
        'based_on_id' => $source->id,
        'name' => 'Versi Lanjutan Silaban',
    ]);
    $rootNode = FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $alternative->id,
        'person_id' => $child->id,
        'father_node_id' => $rootNode->id,
        'chain' => '1-1',
    ]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $root->id,
        'subject_person_id' => $child->id,
        'family_tree_id' => $alternative->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $viewedMarga->id,
            'marga_direction' => 'lower',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('alternativeTrees', 1)
            ->where('alternativeTrees.0.id', $alternative->id)
            ->where('alternativeTrees.0.people.1.id', (string) $child->id));
});

test('authenticated tarombo rows include related story links for the vertical tree dialog', function () {
    $admin = User::factory()->asAdmin()->create();
    $person = Person::factory()->create([
        'name' => 'Sangkar Toba',
        'related_stories' => [
            ['title' => 'Sejarah Sangkar Toba', 'url' => 'https://example.com/sejarah'],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('tarombo.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people) => collect($people)->contains(
                fn (array $row) => $row['id'] === (string) $person->id
                    && $row['relatedStories'] === [
                        ['title' => 'Sejarah Sangkar Toba', 'url' => 'https://example.com/sejarah'],
                    ],
            )));
});

test('vertical tarombo can select account and approved marga family trees', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['name' => 'Akar Pilihan', 'marga_id' => $marga->id]);
    $child = Person::factory()->create(['name' => 'Anak Pilihan', 'marga_id' => $marga->id]);
    $tree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
        'name' => 'Silsilah Pilihan',
    ]);
    $rootNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $child->id,
        'father_node_id' => $rootNode->id,
        'chain' => '1-1',
    ]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $root->id,
        'subject_person_id' => $root->id,
        'family_tree_id' => $tree->id,
    ]);

    $this->actingAs($owner)
        ->get(route('tarombo.index', ['family_tree' => $tree->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedFamilyTreeId', $tree->id)
            ->where('familyTreeOptions.0.id', $tree->id)
            ->where('familyTreeOptions.0.group', 'marga')
            ->has('selectedTreePeople', 2)
            ->where('selectedTreePeople.1.parentId', (string) $root->id));
});

test('vertical tarombo only lists top level and alternative family trees', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Si Raja Batak']);
    $branchRoot = Person::factory()->create(['name' => 'Raja Isumbaon']);
    $topLevelTree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'name' => 'Pohon Utama',
    ]);
    $rootNode = FamilyTreeNode::create([
        'family_tree_id' => $topLevelTree->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $topLevelTree->id,
        'person_id' => $branchRoot->id,
        'father_node_id' => $rootNode->id,
        'chain' => '1-1',
    ]);
    $branchTree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $branchRoot->id,
        'name' => 'Pohon Ranting',
    ]);
    $alternativeTree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $branchRoot->id,
        'based_on_id' => $branchTree->id,
        'name' => 'Pohon Ranting - Versi Alternatif',
    ]);

    $this->actingAs($admin)
        ->get(route('tarombo.fullscreen', ['view' => 'tree']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('familyTreeOptions', fn ($options) => collect($options)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === collect([$topLevelTree->id, $alternativeTree->id])
                ->sort()
                ->values()
                ->all()));
});

test('vertical tarombo rejects a family tree outside the signed in accounts list', function () {
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
        ->get(route('tarombo.index', ['family_tree' => $tree->id]))
        ->assertForbidden();
});
