<?php

use App\Models\ActivityLog;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;

test('sub-admins can access staff data routes', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();

    $this->actingAs($subAdmin)
        ->get(route('people.index'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->get(route('marga.index'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->get(route('stories.index'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->get(route('events.index'))
        ->assertOk();
});

test('sub-admins can manage people', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($subAdmin)
        ->get(route('people.create'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->post(route('people.store'), [
            'name' => 'Orang Baru',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'children' => [['name' => 'Orang Baru']],
        ])
        ->assertRedirect(route('people.index'));
});

test('sub-admins cannot access sub-admin management routes', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();

    $this->actingAs($subAdmin)->get(route('sub-admins.index'))->assertForbidden();
    $this->actingAs($subAdmin)->get(route('sub-admins.create'))->assertForbidden();
});

test('regular users are forbidden from sub-admin management routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sub-admins.index'))->assertForbidden();
});

test('admins can create a sub-admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($admin)
        ->post(route('sub-admins.store'), [
            'name' => 'Sub Admin Baru',
            'email' => 'subadmin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'marga_id' => $marga->id,
        ])
        ->assertRedirect(route('sub-admins.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'subadmin@example.com',
        'role' => 'subadmin',
        'marga_id' => $marga->id,
    ]);

    $subAdmin = User::query()->where('email', 'subadmin@example.com')->firstOrFail();
    expect(ActivityLog::query()->where('account_id', $subAdmin->id)->value('action'))->toBe('created');
});

test('successful data changes by a sub-admin are recorded on their activity log', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();
    $root = Person::factory()->create();
    $tree = FamilyTree::create([
        'user_id' => $subAdmin->id,
        'root_person_id' => $root->id,
        'name' => 'Nama Lama',
    ]);

    $this->actingAs($subAdmin)
        ->patch(route('family-trees.name.update', $tree), ['name' => 'Nama Baru'])
        ->assertRedirect();

    expect(ActivityLog::query()
        ->where('account_id', $subAdmin->id)
        ->where('actor_id', $subAdmin->id)
        ->latest()
        ->firstOrFail())
        ->action->toBe('family-trees.name.update')
        ->description->toBe('Melakukan perubahan data melalui family-trees.name.update.');
});

test('a sub-admin activity log identifies the edited person and their father', function () {
    $admin = User::factory()->asAdmin()->create();
    $subAdmin = User::factory()->asSubAdmin()->create();
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['name' => 'Ayah Log', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Anak Log',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $subAdmin->id,
        'root_person_id' => $father->id,
        'name' => 'Keluarga Log',
        'is_primary' => true,
    ]);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $child->id,
        'father_node_id' => $fatherNode->id,
    ]);

    $this->actingAs($subAdmin)
        ->put(route('people.update', $child), [
            'name' => $child->name,
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father_id' => $father->id,
            'children' => [['id' => $child->id, 'name' => $child->name]],
        ])
        ->assertRedirect(route('people.show', $child));

    $log = ActivityLog::query()
        ->where('account_id', $subAdmin->id)
        ->latest()
        ->firstOrFail();

    expect($log->description)->toBe('Mengubah data Anak Log, anak dari Ayah Log.')
        ->and($log->metadata)->toMatchArray([
            'person' => ['id' => $child->id, 'name' => 'Anak Log'],
            'father' => ['id' => $father->id, 'name' => 'Ayah Log'],
            'family_tree' => ['id' => $tree->id, 'name' => 'Keluarga Log'],
        ]);

    $this->actingAs($admin)
        ->get(route('accounts.activity-log', $subAdmin))
        ->assertOk()
        ->assertJsonPath('logs.0.context.person_name', 'Anak Log')
        ->assertJsonPath('logs.0.context.father_name', 'Ayah Log')
        ->assertJsonPath('logs.0.context.family_tree_name', 'Keluarga Log')
        ->assertJsonPath('logs.0.context.is_legacy', false);
});

test('a sub-admin activity log snapshots the family tree selected by version_tree', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();
    $root = Person::factory()->create(['name' => 'Akar Versi', 'gender' => 'L']);
    $child = Person::factory()->create([
        'name' => 'Anak Versi',
        'gender' => 'L',
        'father_id' => $root->id,
        'birth_order' => 1,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $subAdmin->id,
        'root_person_id' => $root->id,
        'name' => 'Versi Yang Dipilih',
    ]);
    $rootNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $child->id,
        'father_node_id' => $rootNode->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($subAdmin)
        ->put(route('people.update', ['person' => $root, 'version_tree' => $tree->id]), [
            'name' => $root->name,
            'gender' => $root->gender,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => [],
            'mothers' => [],
            'children' => [],
            'ownChildren' => [['id' => $child->id, 'name' => $child->name, 'gender' => $child->gender]],
        ])
        ->assertRedirect(route('people.show', ['person' => $root, 'version_tree' => $tree->id]));

    expect(ActivityLog::query()->where('account_id', $subAdmin->id)->latest()->firstOrFail()->metadata)
        ->toMatchArray([
            'person' => ['id' => $root->id, 'name' => 'Akar Versi'],
            'family_tree' => ['id' => $tree->id, 'name' => 'Versi Yang Dipilih'],
        ]);
});

test('admins can update a sub-admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $subAdmin = User::factory()->asSubAdmin()->create(['name' => 'Lama']);

    $this->actingAs($admin)
        ->put(route('sub-admins.update', $subAdmin), [
            'name' => 'Baru',
            'email' => $subAdmin->email,
            'marga_id' => null,
        ])
        ->assertRedirect(route('sub-admins.index'));

    $this->assertDatabaseHas('users', [
        'id' => $subAdmin->id,
        'name' => 'Baru',
    ]);
});

test('admins can delete a sub-admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $subAdmin = User::factory()->asSubAdmin()->create();

    $this->actingAs($admin)
        ->delete(route('sub-admins.destroy', $subAdmin))
        ->assertRedirect(route('sub-admins.index'));

    $this->assertDatabaseMissing('users', ['id' => $subAdmin->id]);
});

test('creating a sub-admin requires a unique email', function () {
    $admin = User::factory()->asAdmin()->create();
    User::factory()->create(['email' => 'same@example.com']);

    $this->actingAs($admin)
        ->post(route('sub-admins.store'), [
            'name' => 'Sub Admin',
            'email' => 'same@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('email');
});
