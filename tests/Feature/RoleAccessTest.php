<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login when visiting the people index', function () {
    $this->get(route('people.index'))->assertRedirect(route('login'));
});

test('non-admin users can only see people from their own marga', function () {
    $sitorus = Marga::factory()->create(['name' => 'Sitorus']);
    $hutasoit = Marga::factory()->create(['name' => 'Hutasoit']);

    Person::factory()->create(['name' => 'Ompu Sitorus', 'marga_id' => $sitorus->id]);
    Person::factory()->create(['name' => 'Ompu Hutasoit', 'marga_id' => $hutasoit->id]);

    $user = User::factory()->withMarga($sitorus->id)->create();

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/index')
            ->where('canManage', false)
            ->has('people.data', 1)
            ->has('people.data.0', fn (Assert $person) => $person
                ->where('name', 'Ompu Sitorus')
                ->etc()));
});

test('regular users without a marga cannot create or edit family data', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create();

    $this->actingAs($user)->get(route('people.create'))->assertForbidden();
    $this->actingAs($user)->get(route('people.edit', $person))->assertForbidden();
    $this->actingAs($user)->get(route('marga.index'))->assertForbidden();
    $this->actingAs($user)->delete(route('people.destroy', $person))->assertForbidden();
});

test('regular users with a marga can store private family data in their own scope', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Orang Baru',
            'marga_id' => $otherMarga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'children' => [['name' => 'Orang Baru']],
        ])
        ->assertRedirect(route('people.index'));

    $this->assertDatabaseHas('people', [
        'name' => 'Orang Baru',
        'marga_id' => $marga->id,
        'created_by' => $user->id,
        'is_public' => false,
    ]);
});

test('admin users can access admin-only routes', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get(route('marga.index'))
        ->assertOk();
});

test('the tarombo page is publicly accessible', function () {
    $this->get(route('tarombo.view'))
        ->assertOk();
});

test('the marga page is publicly accessible', function () {
    $this->get(route('marga.view'))
        ->assertOk();
});

test('authenticated users can view the admin tarombo page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo.index'))
        ->assertOk();
});

test('admin users see all people and can manage them', function () {
    $sitorus = Marga::factory()->create(['name' => 'Sitorus']);
    $hutasoit = Marga::factory()->create(['name' => 'Hutasoit']);

    Person::factory()->create(['name' => 'Ompu Sitorus', 'marga_id' => $sitorus->id]);
    Person::factory()->create(['name' => 'Ompu Hutasoit', 'marga_id' => $hutasoit->id]);

    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/index')
            ->where('canManage', true)
            ->has('people.data', 2));
});

test('non-admin users can view the dashboard even when their ancestor is outside their marga', function () {
    $sitorus = Marga::factory()->create(['name' => 'Sitorus']);
    $batak = Marga::factory()->create(['name' => 'Batak']);

    $root = Person::factory()->create(['name' => 'Si Raja Batak', 'marga_id' => $batak->id]);
    Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'marga_id' => $sitorus->id,
        'father_id' => $root->id,
    ]);

    $user = User::factory()->withMarga($sitorus->id)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.totalGenerations', 2)
            ->etc());
});

test('dashboard generation depth includes multiple ancestors outside the user marga', function () {
    $sitorus = Marga::factory()->create(['name' => 'Sitorus']);
    $batak = Marga::factory()->create(['name' => 'Batak']);
    $root = Person::factory()->create(['marga_id' => $batak->id]);
    $middle = Person::factory()->create(['marga_id' => $batak->id, 'father_id' => $root->id]);
    Person::factory()->create(['marga_id' => $sitorus->id, 'father_id' => $middle->id]);
    $user = User::factory()->withMarga($sitorus->id)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.totalGenerations', 3)
            ->etc());
});
