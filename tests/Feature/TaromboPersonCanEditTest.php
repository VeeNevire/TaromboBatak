<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;

test('only persons not created by a regular user account and linked to a father are read-only', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $regular = User::factory()->create(['role' => 'user']);
    $contributor = User::factory()->asMainContributor()->create();
    $father = Person::factory()->create(['created_by' => $regular->id]);

    $byRegularUser = Person::factory()->create(['created_by' => $regular->id, 'father_id' => $father->id]);
    $byContributor = Person::factory()->create(['created_by' => $contributor->id, 'father_id' => $father->id]);
    $noCreator = Person::factory()->create(['created_by' => null, 'father_id' => $father->id]);
    $rootByContributor = Person::factory()->create(['created_by' => $contributor->id, 'father_id' => null]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereIn('id', [
            $byRegularUser->id,
            $byContributor->id,
            $noCreator->id,
            $rootByContributor->id,
        ]),
    ))->keyBy('id');

    expect($rows[(string) $byRegularUser->id]['canEdit'])->toBeTrue()
        ->and($rows[(string) $byContributor->id]['canEdit'])->toBeFalse()
        ->and($rows[(string) $noCreator->id]['canEdit'])->toBeFalse()
        ->and($rows[(string) $rootByContributor->id]['canEdit'])->toBeTrue();
});

test('staff can edit any person', function () {
    $admin = User::factory()->asAdmin()->create();
    $contributor = User::factory()->asMainContributor()->create();
    $father = Person::factory()->create(['created_by' => $contributor->id]);
    $attached = Person::factory()->create(['created_by' => $contributor->id, 'father_id' => $father->id]);

    $this->actingAs($admin);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey($attached->id),
    ))->keyBy('id');

    expect($rows[(string) $attached->id]['canEdit'])->toBeTrue();
});

test('a father recorded only in the tree makes the person read-only and is shown in the modal', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $contributor = User::factory()->asMainContributor()->create();
    $marga = Marga::factory()->create(['name' => 'Silaban']);

    $father = Person::factory()->create([
        'name' => 'Ayah Node',
        'marga_id' => $marga->id,
        'created_by' => $contributor->id,
    ]);
    $focus = Person::factory()->create([
        'name' => 'Fokus',
        'marga_id' => $marga->id,
        'father_id' => null,
        'created_by' => $contributor->id,
    ]);

    $tree = FamilyTree::create([
        'user_id' => $contributor->id,
        'root_person_id' => $father->id,
        'name' => 'Keluarga Uji',
    ]);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id, 'chain' => '1']);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $focus->id,
        'father_node_id' => $fatherNode->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rowsForFamilyTree($tree))->keyBy('id');

    expect($rows[(string) $focus->id]['canEdit'])->toBeFalse()
        ->and($rows[(string) $focus->id]['fatherName'])->toBe('Ayah Node')
        ->and($rows[(string) $focus->id]['fatherMarga'])->toBe('Silaban');
});
