<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;

/**
 * A marga tree whose descendant carries another marga and was entered by a
 * sub-admin, like a "Purba" grandson under the "Purba Pakpak" identity.
 *
 * @return array{marga: Marga, identity: Person, son: Person, grandson: Person}
 */
function contributorMargaTree(): array
{
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $subAdmin = User::factory()->asSubAdmin()->create();
    $identity = Person::factory()->create(['gender' => 'L', 'marga_id' => $marga->id, 'created_by' => $subAdmin->id]);
    $marga->update(['identity_person_id' => $identity->id]);
    $son = Person::factory()->create([
        'gender' => 'L', 'marga_id' => $otherMarga->id, 'father_id' => $identity->id, 'created_by' => $subAdmin->id,
    ]);
    $grandson = Person::factory()->create([
        'gender' => 'L', 'marga_id' => null, 'father_id' => $son->id, 'created_by' => $subAdmin->id,
    ]);

    return compact('marga', 'identity', 'son', 'grandson');
}

dataset('marga tree contributor roles', ['contributor_main', 'contributor_member']);

test('a contributor gets copy, branch and edit actions across their marga tree', function (string $role) {
    ['marga' => $marga, 'son' => $son, 'grandson' => $grandson] = contributorMargaTree();
    $contributor = User::factory()->create(['role' => $role, 'marga_id' => null]);
    $contributor->managedMargas()->attach($marga);

    $this->actingAs($contributor);
    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey([$son->id, $grandson->id]),
    ))->keyBy('id');

    foreach ([$son, $grandson] as $person) {
        expect($rows[(string) $person->id])
            ->canCopyCode->toBeTrue()
            ->canAppendBranch->toBeTrue()
            ->canEdit->toBeTrue();
        expect($contributor->can('update', $person))->toBeTrue()
            ->and($contributor->can('appendBranch', $person))->toBeTrue();
    }

    $this->get(route('people.edit', $grandson))->assertSuccessful();
    $this->post(route('marga-branch-entries.store', $grandson))->assertRedirect();
})->with('marga tree contributor roles');

test('a contributor of another marga gets no actions in that tree', function () {
    ['son' => $son] = contributorMargaTree();
    $contributor = User::factory()->asMainContributor()->withMarga(Marga::factory()->create()->id)->create();

    $this->actingAs($contributor);
    $row = app(TaromboTreeService::class)->rows(Person::query()->whereKey($son->id))[0];

    expect($row['canCopyCode'])->toBeFalse()
        ->and($row['canAppendBranch'])->toBeFalse()
        ->and($row['canEdit'])->toBeFalse()
        ->and($contributor->can('update', $son))->toBeFalse();
});

test('a regular member still cannot edit people entered by staff', function () {
    ['marga' => $marga, 'son' => $son] = contributorMargaTree();
    $member = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($member);
    $row = app(TaromboTreeService::class)->rows(Person::query()->whereKey($son->id))[0];

    expect($row['canCopyCode'])->toBeFalse()
        ->and($row['canEdit'])->toBeFalse();
});
