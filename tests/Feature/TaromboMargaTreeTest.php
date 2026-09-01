<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('staff can open an upper or lower marga tree for its identity person', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $root = Person::factory()->create(['name' => 'Si Raja Batak', 'marga_id' => null]);
    $identity = Person::factory()->create([
        'name' => 'Identitas Marga',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
        'gender' => 'L',
    ]);
    $marga->update(['identity_person_id' => $identity->id]);

    foreach (['upper', 'lower'] as $direction) {
        $this->actingAs($admin)
            ->get(route('tarombo.fullscreen', [
                'view' => 'tree',
                'marga_id' => $marga->id,
                'marga_direction' => $direction,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tarombo/fullscreen')
                ->where('margaTree.margaName', $marga->name)
                ->where('margaTree.identityPersonId', (string) $identity->id)
                ->where('margaTree.direction', $direction)
                ->where('people.0.id', (string) $root->id)
                ->where('people.1.id', (string) $identity->id));
    }
});

test('marga tree requires a valid identity and staff access', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $marga->id,
            'marga_direction' => 'lower',
        ]))
        ->assertForbidden();

    $this->actingAs(User::factory()->asAdmin()->create())
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $marga->id,
            'marga_direction' => 'lower',
        ]))
        ->assertNotFound();
});
