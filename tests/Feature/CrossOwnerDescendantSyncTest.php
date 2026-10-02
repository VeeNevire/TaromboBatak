<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeDescendantSyncService;
use App\Services\TaromboTreeService;

beforeEach(function () {
    $this->marga = Marga::factory()->create();
    $this->adminOwner = User::factory()->asAdmin()->create();
    $this->otherOwner = User::factory()->withMarga($this->marga->id)->create();
    $this->root = Person::factory()->create(['marga_id' => $this->marga->id, 'gender' => 'L']);
    $this->adminTree = FamilyTree::create(['user_id' => $this->adminOwner->id, 'root_person_id' => $this->root->id]);
    FamilyTreeNode::create(['family_tree_id' => $this->adminTree->id, 'person_id' => $this->root->id]);
    $this->otherTree = FamilyTree::create(['user_id' => $this->otherOwner->id, 'root_person_id' => $this->root->id]);
    FamilyTreeNode::create(['family_tree_id' => $this->otherTree->id, 'person_id' => $this->root->id]);
});

test('a person added through another owner tree also reaches the admin tree', function () {
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id, 'name' => 'Ama Gayver']);

    app(FamilyTreeDescendantSyncService::class)->syncTreesForNewDescendant($this->otherTree, $newcomer);

    expect($this->adminTree->nodes()->where('person_id', $newcomer->id)->exists())->toBeTrue();
    $names = collect(app(TaromboTreeService::class)->rowsForFamilyTree($this->adminTree))->pluck('name');
    expect($names)->toContain('Ama Gayver');
});

test('a locked alternative inherits the person through its synced base tree', function () {
    $alternative = FamilyTree::create([
        'user_id' => $this->adminOwner->id,
        'root_person_id' => $this->root->id,
        'based_on_id' => $this->adminTree->id,
    ]);
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id, 'name' => 'Ama Gayver']);

    app(FamilyTreeDescendantSyncService::class)->syncTreesForPerson($newcomer);

    $names = collect(app(TaromboTreeService::class)->rowsForFamilyTree($alternative))->pluck('name');
    expect($names)->toContain('Ama Gayver');
});

test('trees without a common ancestor are not touched', function () {
    $stranger = Person::factory()->create(['gender' => 'L']);
    $strangerTree = FamilyTree::create(['user_id' => $this->adminOwner->id, 'root_person_id' => $stranger->id]);
    FamilyTreeNode::create(['family_tree_id' => $strangerTree->id, 'person_id' => $stranger->id]);
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id]);

    app(FamilyTreeDescendantSyncService::class)->syncTreesForPerson($newcomer);

    expect($strangerTree->nodes()->where('person_id', $newcomer->id)->exists())->toBeFalse();
});

test('the sync command reports missing people on a dry run and adds them with --apply', function () {
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id, 'name' => 'Ama Gayver']);

    $this->artisan('tarombo:sync-descendants', ['--tree' => $this->adminTree->id])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();
    expect($this->adminTree->nodes()->where('person_id', $newcomer->id)->exists())->toBeFalse();

    $this->artisan('tarombo:sync-descendants', ['--tree' => $this->adminTree->id, '--apply' => true])
        ->expectsOutputToContain('Synced')
        ->assertSuccessful();
    expect($this->adminTree->nodes()->where('person_id', $newcomer->id)->exists())->toBeTrue();
});
