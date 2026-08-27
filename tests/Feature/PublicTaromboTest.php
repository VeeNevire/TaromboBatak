<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the public tarombo excludes private people and sensitive person fields', function () {
    $marga = Marga::factory()->create();
    $root = Person::factory()->public()->create([
        'name' => 'Root Publik',
        'marga_id' => $marga->id,
        'birth_year' => '1900',
        'image' => 'https://example.com/root.jpg',
        'bio' => 'Data sensitif',
        'related_stories' => [
            ['title' => 'Rahasia', 'url' => 'https://example.com/rahasia'],
        ],
        'spouse' => 'Pasangan',
    ]);
    Person::factory()->public()->create([
        'name' => 'Anak Publik',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
        'birth_order' => 1,
    ]);
    Person::factory()->create([
        'name' => 'Orang Private',
        'marga_id' => $marga->id,
    ]);

    $this->get(route('tarombo.view'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tarombo/public')
            ->has('people', 2)
            ->where('people.0.name', 'Root Publik')
            ->where('people.1.name', 'Anak Publik')
            ->missing('people.0.birthYear')
            ->missing('people.0.image')
            ->missing('people.0.bio')
            ->missing('people.0.relatedStories')
            ->missing('people.0.spouse')
            ->where('stats.totalPeople', 2));
});

test('a regular user cannot publish family data directly', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Data User',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'is_public' => true,
            'children' => [['name' => 'Data User']],
        ])
        ->assertSessionHasErrors('is_public');

    $this->assertDatabaseMissing('people', ['name' => 'Data User']);
});

test('the public tree respects the configured node limit', function () {
    config()->set('tarombo.public_max_nodes', 2);
    config()->set('tarombo.public_max_depth', 6);

    $root = Person::factory()->public()->create(['name' => 'Root']);
    Person::factory()->public()->create([
        'name' => 'Anak Satu',
        'father_id' => $root->id,
        'birth_order' => 1,
    ]);
    Person::factory()->public()->create([
        'name' => 'Anak Dua',
        'father_id' => $root->id,
        'birth_order' => 2,
    ]);

    $this->get(route('tarombo.view'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('people', 2)
            ->where('people.0.parentId', null)
            ->where('people.1.parentId', (string) $root->id)
            ->where('truncated', true));
});
