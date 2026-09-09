<?php

use App\Models\FamilyTree;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can open the account family tree directory', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['name' => 'Ompu Silaban']);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'name' => 'Keluarga Jaya Silaban',
    ]);

    $this->actingAs($admin)
        ->get(route('family-trees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('family-trees/index')
            ->has('shareableAccounts')
            ->has('pendingTreeShares')
            ->has('familyTrees', 1)
            ->where('familyTrees.0.id', $tree->id)
            ->where('familyTrees.0.name', 'Keluarga Jaya Silaban'));
});

test('the directory is limited to admins', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('family-trees.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->asSubAdmin()->create())
        ->get(route('family-trees.index'))
        ->assertForbidden();

    auth()->forgetGuards();

    $this->get(route('family-trees.index'))->assertRedirect(route('login'));
});
