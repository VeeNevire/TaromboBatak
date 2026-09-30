<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('publication changes are saved when editing a family tree version', function (bool $publish, bool $cascade, bool $privateFather, bool $regularUser, bool $valid) {
    $user = $regularUser ? User::factory()->create() : User::factory()->asAdmin()->create();
    $father = Person::factory()->create(['is_public' => ! $privateFather]);
    $grandfather = Person::factory()->create(['is_public' => false]);
    $father->update(['father_id' => $grandfather->id]);
    $person = Person::factory()->create(['father_id' => $father->id, 'is_public' => ! $publish]);
    $child = Person::factory()->create(['father_id' => $person->id, 'is_public' => ! $publish]);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $person->id,
        'name' => 'Publication test',
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $person->id]);

    $response = $this->actingAs($user)
        ->put(route('people.update', ['person' => $person, 'version_tree' => $tree->id]), [
            'name' => $person->name,
            'birth_order' => 1,
            'sibling_count' => 1,
            'is_public' => $publish,
            'cascade_public_descendants' => $cascade,
            'children' => [],
            'ownChildren' => [],
        ]);

    if ($valid) {
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('people.edit', ['person' => $person, 'version_tree' => $tree->id]));
    } else {
        $response->assertSessionHasErrors('is_public');
    }

    expect($person->fresh()->is_public)->toBe($valid ? $publish : ! $publish)
        ->and($grandfather->fresh()->is_public)->toBe($valid && $publish)
        ->and($father->fresh()->is_public)->toBe($valid && $publish ? true : ! $privateFather)
        ->and($person->fresh()->father_id)->toBe($father->id)
        ->and($child->fresh()->is_public)->toBe($valid && $cascade ? false : ! $publish);
})->with([
    'publish' => [true, false, false, false, true],
    'unpublish branch with confirmation' => [false, true, false, false, true],
    'unpublish branch requires confirmation' => [false, false, false, false, false],
    'publish includes private ancestors' => [true, false, true, false, true],
    'regular users cannot publish' => [true, false, false, true, false],
]);

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

test('the dashboard Tarombo preview uses the same public branch data as the full page', function () {
    $marga = Marga::factory()->create();
    $ancestor = Person::factory()->public()->create(['marga_id' => $marga->id]);
    $child = Person::factory()->public()->create([
        'marga_id' => $marga->id,
        'father_id' => $ancestor->id,
        'birth_order' => 1,
    ]);
    Person::factory()->public()->create([
        'marga_id' => $marga->id,
        'father_id' => $child->id,
        'birth_order' => 1,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('taromboPeople', 3)
            ->where('taromboPeople.1.parentId', (string) $ancestor->id)
            ->where('taromboPeople.2.parentId', (string) $child->id));
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

test('an admin can make a public family branch private after confirming the cascade', function () {
    $marga = Marga::factory()->create();
    $ancestor = Person::factory()->public()->create([
        'name' => 'Leluhur Publik',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    $child = Person::factory()->public()->create([
        'name' => 'Anak Publik',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $ancestor->id,
        'birth_order' => 1,
    ]);
    $grandchild = Person::factory()->public()->create([
        'name' => 'Cucu Publik',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $child->id,
        'birth_order' => 1,
    ]);

    $this->actingAs(User::factory()->asAdmin()->create())
        ->put(route('people.update', $ancestor), [
            'name' => $ancestor->name,
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'is_public' => false,
            'cascade_public_descendants' => true,
            'children' => [[
                'id' => $ancestor->id,
                'name' => $ancestor->name,
                'gender' => 'L',
                'marga_id' => $marga->id,
            ]],
            'ownChildren' => [[
                'id' => $child->id,
                'name' => $child->name,
                'gender' => 'L',
                'marga_id' => $marga->id,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($ancestor->fresh()->is_public)->toBeFalse()
        ->and($child->fresh()->is_public)->toBeFalse()
        ->and($grandchild->fresh()->is_public)->toBeFalse();
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
