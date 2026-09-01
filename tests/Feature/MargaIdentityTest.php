<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\MargaIdentityPersonService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('marga index is alphabetical and exposes connection state from its identity', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['chain' => '1', 'father_id' => null]);
    $connectedIdentity = Person::factory()->create([
        'chain' => '1-1',
        'father_id' => $root->id,
    ]);
    $zeta = Marga::factory()->create([
        'name' => 'Zeta',
        'identity_person_id' => $connectedIdentity->id,
    ]);
    $alpha = Marga::factory()->create(['name' => 'Alpha']);

    $this->actingAs($admin)
        ->get(route('marga.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('margas.0.id', $alpha->id)
            ->where('margas.0.identity_person_id', null)
            ->where('margas.1.id', $zeta->id)
            ->where('margas.1.identity_person_id', $connectedIdentity->id));
});

test('marga identity options use connected tarombo people through generation eleven without family tree nodes', function () {
    $root = Person::factory()->create([
        'name' => 'Si Raja Batak',
        'father_id' => null,
        'chain' => '1',
    ]);
    $generationEleven = Person::factory()->create([
        'name' => 'Generasi Sebelas',
        'father_id' => $root->id,
        'chain' => '1-1-1-1-1-1-1-1-1-1-1',
    ]);
    Person::factory()->create([
        'name' => 'Generasi Dua Belas',
        'father_id' => $generationEleven->id,
        'chain' => '1-1-1-1-1-1-1-1-1-1-1-1',
    ]);
    Person::factory()->create([
        'name' => 'Pohon Lain',
        'father_id' => null,
        'chain' => '2',
    ]);

    $options = app(MargaIdentityPersonService::class)->options();

    expect($options->pluck('id')->all())->toBe([
        $root->id,
        $generationEleven->id,
    ]);
});

test('an admin can save a valid marga identity and invalid tree nodes are rejected', function () {
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create([
        'name' => 'Si Raja Batak',
        'father_id' => null,
        'chain' => '1',
    ]);
    $validIdentity = Person::factory()->create([
        'name' => 'Raja Marga',
        'father_id' => $root->id,
        'chain' => '1-1',
    ]);
    $invalidIdentity = Person::factory()->create([
        'name' => 'Di luar batas',
        'father_id' => $validIdentity->id,
        'chain' => '1-1-1-1-1-1-1-1-1-1-1-1',
    ]);

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->post(route('marga.store'), [
            'name' => 'Marga Valid',
            'identity_person_id' => $validIdentity->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('marga.index'));

    expect(Marga::query()->where('name', 'Marga Valid')->value('identity_person_id'))
        ->toBe($validIdentity->id);

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->post(route('marga.store'), [
            'name' => 'Marga Tidak Valid',
            'identity_person_id' => $invalidIdentity->id,
        ])
        ->assertSessionHasErrors('identity_person_id');
});

test('an admin can update a marga identity and image through a spoofed multipart request', function () {
    Storage::fake('public');

    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create([
        'name' => 'Si Raja Batak',
        'father_id' => null,
        'chain' => '1',
    ]);
    $identity = Person::factory()->create([
        'name' => 'Raja Saribu',
        'father_id' => $root->id,
        'chain' => '1-1',
    ]);
    $marga = Marga::factory()->create([
        'name' => 'Saribu Raja',
        'image' => 'margas/old.png',
    ]);
    Storage::disk('public')->put($marga->image, 'old image');

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->post(route('marga.update', $marga), [
            '_method' => 'put',
            'name' => $marga->name,
            'description' => $marga->description,
            'color' => $marga->color,
            'image' => Storage::disk('public')->url($marga->image),
            'identity_person_id' => $identity->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('marga.index'));

    $marga->refresh();

    expect($marga->identity_person_id)->toBe($identity->id)
        ->and($marga->image)->toBe('margas/old.png');
    Storage::disk('public')->assertExists('margas/old.png');

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->post(route('marga.update', $marga), [
            '_method' => 'put',
            'name' => $marga->name,
            'description' => $marga->description,
            'color' => $marga->color,
            'image' => UploadedFile::fake()->image('saribu-raja.png'),
            'identity_person_id' => $identity->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('marga.index'));

    $marga->refresh();

    expect($marga->identity_person_id)->toBe($identity->id)
        ->and($marga->image)->toStartWith('margas/');
    Storage::disk('public')->assertExists($marga->image);
    Storage::disk('public')->assertMissing('margas/old.png');
});

test('an admin cannot update a marga to a name already in use', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create(['name' => 'Saribu Raja']);
    Marga::factory()->create(['name' => 'Simatupang']);

    $this->actingAs($admin)
        ->from(route('marga.index'))
        ->post(route('marga.update', $marga), [
            '_method' => 'put',
            'name' => 'Simatupang',
            'description' => $marga->description,
            'color' => $marga->color,
        ])
        ->assertSessionHasErrors('name');

    expect($marga->fresh()->name)->toBe('Saribu Raja');
});
