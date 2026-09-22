<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;

test('the fix command connects every local only link to its global father_id', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $ancestor = Person::factory()->create(['name' => 'Guru Sotadingon', 'gender' => 'L', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Ompu Raja Matakkang Manjuara',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => null,
        'created_by' => $admin->id,
    ]);

    $tree = FamilyTree::create(['user_id' => $admin->id, 'root_person_id' => $ancestor->id]);
    $ancestorNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $ancestor->id]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $child->id, 'father_node_id' => $ancestorNode->id]);

    $this->artisan('tarombo:fix-local-only-links', ['--force' => true])
        ->assertSuccessful();

    expect($child->fresh()->father_id)->toBe($ancestor->id);

    $this->artisan('tarombo:audit-local-only-links')
        ->expectsOutputToContain('No local-only father links found.')
        ->assertSuccessful();
});

test('the audit command reports nothing when there are no mismatches', function () {
    $this->artisan('tarombo:audit-local-only-links')
        ->expectsOutputToContain('No local-only father links found.')
        ->assertSuccessful();
});
