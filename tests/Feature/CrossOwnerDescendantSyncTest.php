<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeDescendantSyncService;
use App\Services\TaromboTreeService;
use Illuminate\Support\Facades\DB;

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

test('a locked alternative with its own father node is skipped', function () {
    $alternative = FamilyTree::create([
        'user_id' => $this->adminOwner->id,
        'root_person_id' => $this->root->id,
        'based_on_id' => $this->adminTree->id,
    ]);
    FamilyTreeNode::create(['family_tree_id' => $alternative->id, 'person_id' => $this->root->id, 'structure_overrides' => []]);
    ContributionRequest::factory()->create([
        'family_tree_id' => $alternative->id,
        'requester_id' => $this->adminOwner->id,
        'matched_father_id' => $this->root->id,
        'subject_person_id' => $this->root->id,
        'status' => ContributionRequest::STATUS_PENDING,
    ]);
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id]);

    app(FamilyTreeDescendantSyncService::class)->syncTreesForPerson($newcomer);

    expect($alternative->nodes()->where('person_id', $newcomer->id)->exists())->toBeFalse()
        ->and($this->adminTree->nodes()->where('person_id', $newcomer->id)->exists())->toBeTrue();
});

test('the new node takes its chain from the father node', function () {
    $this->adminTree->nodes()->where('person_id', $this->root->id)->update(['chain' => '1-4']);
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id, 'birth_order' => 3]);

    app(FamilyTreeDescendantSyncService::class)->syncTreesForPerson($newcomer);

    expect($this->adminTree->nodes()->where('person_id', $newcomer->id)->value('chain'))->toBe('1-4-3');
});

test('attaching to many trees uses a fixed number of queries', function () {
    foreach (range(1, 20) as $i) {
        $tree = FamilyTree::create(['user_id' => User::factory()->create()->id, 'root_person_id' => $this->root->id]);
        FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $this->root->id]);
    }
    $newcomer = Person::factory()->create(['father_id' => $this->root->id, 'marga_id' => $this->marga->id]);

    DB::enableQueryLog();
    $added = app(FamilyTreeDescendantSyncService::class)->attachToTreesHoldingFather($newcomer);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($added)->toBe(22)->and($queries)->toBeLessThan(10);
});
