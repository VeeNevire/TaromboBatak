<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests see only public marga without management controls', function () {
    $public = Marga::factory()->public()->create(['name' => 'Silaban']);
    Marga::factory()->create(['name' => 'Marga Privat']);

    $this->get(route('marga.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marga/index')
            ->where('canManage', false)
            ->where('identityPersonOptions', [])
            ->has('margas', 1)
            ->where('margas.0.id', $public->id)
            ->where('margas.0.is_public', true));
});

test('regular users see only public marga', function () {
    Marga::factory()->public()->create();
    Marga::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('marga.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)
            ->has('margas', 1));
});

test('staff see every marga with management enabled', function () {
    Marga::factory()->public()->create();
    Marga::factory()->create();

    $this->actingAs(User::factory()->asAdmin()->create())
        ->get(route('marga.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->has('margas', 2));
});

test('staff can mark a marga public and unpublish it', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->post(route('marga.store'), [
            'name' => 'Situmorang',
            'is_public' => '1',
        ])
        ->assertRedirect(route('marga.index'));

    $marga = Marga::firstOrFail();
    expect($marga->is_public)->toBeTrue();

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->put(route('marga.update', $marga), [
            'name' => 'Situmorang',
            'is_public' => '0',
        ])
        ->assertRedirect(route('marga.index'));

    expect($marga->refresh()->is_public)->toBeFalse();
});

test('guests can open a public marga silsilah tree in both directions', function () {
    $root = Person::factory()->create(['name' => 'Si Raja Batak', 'marga_id' => null, 'gender' => 'L']);
    $identity = Person::factory()->create([
        'name' => 'Identitas Silaban',
        'marga_id' => null,
        'father_id' => $root->id,
        'gender' => 'L',
    ]);
    $marga = Marga::factory()->public()->create([
        'name' => 'Silaban',
        'identity_person_id' => $identity->id,
    ]);
    $identity->update(['marga_id' => $marga->id]);

    foreach (['upper', 'lower'] as $direction) {
        $this->get(route('marga.public-tree', [$marga, $direction]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tarombo/fullscreen')
                ->where('margaTree.margaName', 'Silaban')
                ->where('margaTree.direction', $direction));
    }
});

test('a non-public marga silsilah tree returns not found', function () {
    $marga = Marga::factory()->create();

    $this->get(route('marga.public-tree', [$marga, 'lower']))->assertNotFound();
});
