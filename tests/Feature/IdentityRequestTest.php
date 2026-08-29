<?php

use App\Models\IdentityRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;

test('an admin can choose an identity from any marga and it is persisted', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $person = Person::factory()->create(['marga_id' => $marga->id]);

    $this->actingAs($admin)
        ->post(route('identity-requests.store'), ['person_id' => $person->id])
        ->assertRedirect();

    expect($admin->fresh()->current_person_id)->toBe($person->id)
        ->and(IdentityRequest::query()->count())->toBe(0);
});

test('an approved identity request persists the requester identity', function () {
    $marga = Marga::factory()->create();
    $requester = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $person = Person::factory()->create(['marga_id' => $marga->id]);
    $identityRequest = IdentityRequest::query()->create([
        'requester_id' => $requester->id,
        'person_id' => $person->id,
        'status' => IdentityRequest::STATUS_PENDING,
    ]);

    $this->actingAs($contributor)
        ->post(route('identity-requests.approve', $identityRequest))
        ->assertRedirect();

    expect($requester->fresh()->current_person_id)->toBe($person->id);
});
