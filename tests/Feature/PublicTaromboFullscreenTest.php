<?php

use App\Models\Marga;
use App\Models\Person;
use Inertia\Testing\AssertableInertia as Assert;

test('the public fullscreen tarombo page is accessible to guests', function () {
    $marga = Marga::factory()->create();
    $root = Person::factory()->public()->create([
        'name' => 'Root Publik',
        'marga_id' => $marga->id,
    ]);
    Person::factory()->public()->create([
        'name' => 'Anak Publik',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
        'birth_order' => 1,
    ]);

    $this->get(route('tarombo.full'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/public-fullscreen')
            ->has('people', 2)
            ->where('initialPersonId', '')
            ->where('truncated', false));
});

test('the public fullscreen tarombo page accepts an initial person query', function () {
    $root = Person::factory()->public()->create(['name' => 'Root']);
    $child = Person::factory()->public()->create([
        'name' => 'Anak',
        'father_id' => $root->id,
        'birth_order' => 1,
    ]);

    $this->get(route('tarombo.full', ['person' => (string) $child->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/public-fullscreen')
            ->where('initialPersonId', (string) $child->id));
});
