<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;

test('only a contributor managing the persons marga can copy the share code', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();

    $regular = User::factory()->create(['role' => 'user']);
    $mainContributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $memberContributor = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $contributorOtherMarga = User::factory()->asMainContributor()->withMarga($otherMarga->id)->create();

    $person = Person::factory()->create(['marga_id' => $marga->id]);

    $rowsFor = function (User $viewer) use ($person) {
        $this->actingAs($viewer);

        return collect(app(TaromboTreeService::class)->rows(
            Person::query()->whereKey($person->id),
        ))->keyBy('id');
    };

    expect($rowsFor($regular)[(string) $person->id]['canCopyCode'])->toBeFalse()
        ->and($rowsFor($mainContributor)[(string) $person->id]['canCopyCode'])->toBeTrue()
        ->and($rowsFor($memberContributor)[(string) $person->id]['canCopyCode'])->toBeTrue()
        ->and($rowsFor($contributorOtherMarga)[(string) $person->id]['canCopyCode'])->toBeFalse();
});

test('a contributor managing the marga through managedMargas can copy the share code', function () {
    $marga = Marga::factory()->create();
    $contributor = User::factory()->asMainContributor()->create();
    $contributor->managedMargas()->attach($marga->id);

    $person = Person::factory()->create(['marga_id' => $marga->id]);

    $this->actingAs($contributor);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey($person->id),
    ))->keyBy('id');

    expect($rows[(string) $person->id]['canCopyCode'])->toBeTrue();
});

test('staff can always copy the share code', function () {
    $marga = Marga::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $person = Person::factory()->create(['marga_id' => $marga->id]);

    $this->actingAs($admin);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey($person->id),
    ))->keyBy('id');

    expect($rows[(string) $person->id]['canCopyCode'])->toBeTrue();
});
