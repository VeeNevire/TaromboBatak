<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\MargaIdentityPersonService;

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
