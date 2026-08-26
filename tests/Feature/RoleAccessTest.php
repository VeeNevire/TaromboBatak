<?php

use App\Models\FamilyTree;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login when visiting the people index', function () {
    $this->get(route('people.index'))->assertRedirect(route('login'));
});

test('regular users only see their own marga inside their family trees', function () {
    $sitorus = Marga::factory()->create(['name' => 'Sitorus']);
    $hutasoit = Marga::factory()->create(['name' => 'Hutasoit']);

    $ownPerson = Person::factory()->create(['name' => 'Ompu Sitorus', 'marga_id' => $sitorus->id]);
    $contextPerson = Person::factory()->create(['name' => 'Ompu Hutasoit', 'marga_id' => $hutasoit->id]);

    $user = User::factory()->withMarga($sitorus->id)->create();
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $ownPerson->id,
        'name' => 'Keluarga Sitorus',
    ]);
    $tree->people()->attach([$ownPerson->id, $contextPerson->id]);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/index')
            ->where('canManage', false)
            ->has('people.data', 1)
            ->has('people.data.0', fn (Assert $person) => $person
                ->where('name', 'Ompu Sitorus')
                ->etc()));

    $this->actingAs($user)
        ->from(route('people.index'))
        ->get(route('people.show', $contextPerson))
        ->assertRedirect(route('people.index'))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
});

test('a regular user sees their first marga member as the lineage boundary root', function () {
    $userMarga = Marga::factory()->create(['name' => 'Simare']);
    $otherMarga = Marga::factory()->create(['name' => 'Situmorang']);
    $user = User::factory()->withMarga($userMarga->id)->create();
    $outsideFather = Person::factory()->create([
        'name' => 'Raja Situmorang',
        'marga_id' => $otherMarga->id,
    ]);
    $boundary = Person::factory()->create([
        'name' => 'Parsaoran Simare',
        'marga_id' => $userMarga->id,
        'father_id' => $outsideFather->id,
    ]);

    $this->actingAs($user)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('margas', 1)
            ->where('margas.0.name', 'Simare')
            ->has('lineage', 1)
            ->where('lineage.0.id', $boundary->id)
            ->where('lineage.0.marga', 'Simare'));
});

test('the lineage list excludes a wife while retaining her father', function () {
    $marga = Marga::factory()->create(['name' => 'Simare']);
    $user = User::factory()->withMarga($marga->id)->create();
    $wifeFather = Person::factory()->create([
        'name' => 'Ayah Istri',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    Person::factory()->create([
        'name' => 'Istri',
        'gender' => 'P',
        'marga_id' => $marga->id,
        'father_id' => $wifeFather->id,
    ]);

    $this->actingAs($user)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('lineage', 1)
            ->where('lineage.0.id', $wifeFather->id)
            ->has('lineage.0.children', 0));
});

test('regular users without a marga cannot create or edit family data', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create();

    $this->actingAs($user)->get(route('people.create'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
    $this->actingAs($user)->get(route('people.edit', $person))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
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

test('people search matches aliases and marga names', function () {
    $admin = User::factory()->asAdmin()->create();
    $simare = Marga::factory()->create(['name' => 'Simare']);
    $silaban = Marga::factory()->create(['name' => 'Silaban']);
    Person::factory()->create([
        'name' => 'Rehan',
        'alias' => 'Raja Parhata',
        'marga_id' => $simare->id,
    ]);
    Person::factory()->create([
        'name' => 'Budi',
        'alias' => 'Ompu Tua',
        'marga_id' => $silaban->id,
    ]);

    $this->actingAs($admin)
        ->get(route('people.index', ['search' => 'Parhata']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Rehan'));

    $this->actingAs($admin)
        ->get(route('people.index', ['search' => 'Silaban']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.name', 'Budi'));
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

test('unverified users with a marga can view and create their family data', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create([
        'email_verified_at' => null,
    ]);
    $person = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $person->id,
        'name' => 'Keluarga Rehan',
    ]);
    $tree->people()->attach($person->id);

    $this->actingAs($user)
        ->get(route('people.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)
            ->where('hasMarga', true)
            ->has('people.data', 1));

    $this->actingAs($user)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/form')
            ->where('canPublish', false));
});

test('regular users can submit a family entry without publishing it', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('people.store'), [
            'name' => 'Keluarga Rehan',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'children' => [['name' => 'Keluarga Rehan']],
        ])
        ->assertRedirect(route('people.index'));

    $this->assertDatabaseHas('people', [
        'name' => 'Keluarga Rehan',
        'marga_id' => $marga->id,
        'created_by' => $user->id,
        'is_public' => false,
    ]);
});

test('regular users can view and edit any person inside their marga', function () {
    $marga = Marga::factory()->create();
    $creator = User::factory()->withMarga($marga->id)->create();
    $other = User::factory()->withMarga($marga->id)->create();
    $person = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'created_by' => $creator->id,
    ]);

    $this->actingAs($other)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('readOnly', true));

    $this->actingAs($other)
        ->get(route('people.edit', $person))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/form'));
});

test('regular users cannot open people outside their marga', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $person = Person::factory()->create(['marga_id' => $otherMarga->id]);

    $this->actingAs($user)
        ->from(route('people.index'))
        ->get(route('people.show', $person))
        ->assertRedirect(route('people.index'))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->actingAs($user)
        ->from(route('people.index'))
        ->put(route('people.update', $person), ['name' => 'Diubah'])
        ->assertRedirect(route('people.index'))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
});

test('regular users can open a family member detail page as read-only', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create([
        'email_verified_at' => null,
    ]);
    $outsideAncestor = Person::factory()->create(['marga_id' => $otherMarga->id]);
    $boundary = Person::factory()->create([
        'marga_id' => $marga->id,
        'father_id' => $outsideAncestor->id,
    ]);
    $person = Person::factory()->create([
        'marga_id' => $marga->id,
        'father_id' => $boundary->id,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $person->id,
        'name' => 'Keluarga User',
    ]);
    $tree->people()->attach([$outsideAncestor->id, $boundary->id, $person->id]);

    $this->actingAs($user)
        ->get(route('people.show', $person))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('readOnly', true)
            ->has('person.lineage', 2)
            ->where('person.lineage.0.marga', $marga->name)
            ->where('person.lineage.1.marga', $marga->name));
});

test('authenticated users can open the tarombo full screen page', function () {
    $user = User::factory()->create();

    foreach (['diagram', 'tree'] as $view) {
        $this->actingAs($user)
            ->get(route('tarombo.fullscreen', ['view' => $view]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tarombo/fullscreen')
                ->where('view', $view));
    }
});

test('the tarombo full screen page rejects unknown views', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo.fullscreen', ['view' => 'invalid']))
        ->assertNotFound();
});

test('guests are redirected to login when visiting the tarombo full screen page', function () {
    $this->get(route('tarombo.fullscreen', ['view' => 'diagram']))
        ->assertRedirect(route('login'));
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
