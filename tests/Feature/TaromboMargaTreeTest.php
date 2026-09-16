<?php

use App\Models\Marga;
use App\Models\MargaAccessRequest;
use App\Models\Person;
use App\Models\User;
use App\Support\PersonShareCode;
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
                ->where(
                    'selectedTreePeople.0.shareCode',
                    app(PersonShareCode::class)->for($direction === 'upper' ? $root : $identity),
                )
                ->where('people.0.id', (string) ($direction === 'upper' ? $root->id : $identity->id)));
    }
});

test('marga tree without an identity falls back to every marga member for staff', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $marga->id,
            'marga_direction' => 'lower',
        ]))
        ->assertForbidden();

    $member = Person::factory()->create([
        'name' => 'Anggota Marga',
        'marga_id' => $marga->id,
        'gender' => 'L',
    ]);

    foreach (['upper', 'lower'] as $direction) {
        $this->actingAs(User::factory()->asAdmin()->create())
            ->get(route('tarombo.fullscreen', [
                'view' => 'tree',
                'marga_id' => $marga->id,
                'marga_direction' => $direction,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tarombo/fullscreen')
                ->where('margaTree.margaName', $marga->name)
                ->where('margaTree.identityPersonId', null)
                ->where('people.0.id', (string) $member->id));
    }
});

test('approved users receive the complete ancestor path for an upper marga tree', function () {
    $marga = Marga::factory()->create(['name' => 'Silaban']);
    $otherMarga = Marga::factory()->create(['name' => 'Saribu Raja']);
    $viewer = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create([
        'name' => 'Si Raja Batak',
        'marga_id' => null,
        'gender' => 'L',
    ]);
    $ancestor = Person::factory()->create([
        'name' => 'Tuan Sorimangaraja',
        'marga_id' => $otherMarga->id,
        'father_id' => $root->id,
        'gender' => 'L',
    ]);
    $ancestorSibling = Person::factory()->create([
        'name' => 'Saudara Tuan Sorimangaraja',
        'marga_id' => $otherMarga->id,
        'father_id' => $root->id,
        'gender' => 'L',
        'birth_order' => 2,
    ]);
    $identity = Person::factory()->create([
        'name' => 'Borsak Junjungan',
        'marga_id' => $marga->id,
        'father_id' => $ancestor->id,
        'gender' => 'L',
    ]);
    $identitySibling = Person::factory()->create([
        'name' => 'Saudara Borsak Junjungan',
        'marga_id' => $marga->id,
        'father_id' => $ancestor->id,
        'gender' => 'L',
        'birth_order' => 2,
    ]);
    $marga->update(['identity_person_id' => $identity->id]);

    MargaAccessRequest::create([
        'requester_id' => $viewer->id,
        'marga_id' => $marga->id,
        'status' => MargaAccessRequest::STATUS_APPROVED,
    ]);

    $this->actingAs($viewer)
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $marga->id,
            'marga_direction' => 'upper',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('margaTree.identityPersonId', (string) $identity->id)
            ->where('margaTree.direction', 'upper')
            ->where('people', fn ($people) => collect($people)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === collect([$root, $ancestor, $identity, $ancestorSibling, $identitySibling])
                ->pluck('id')
                ->map(fn (int $id) => (string) $id)
                ->sort()
                ->values()
                ->all()));
});

test('approved users receive lower marga descendants beyond the person preview depth', function () {
    config()->set('tarombo.person_max_depth', 5);
    config()->set('tarombo.public_max_depth', 11);

    $marga = Marga::factory()->create(['name' => 'Silaban']);
    $descendantMarga = Marga::factory()->create(['name' => 'Silaban Sitio']);
    $viewerMarga = Marga::factory()->create(['name' => 'Sagala']);
    $viewer = User::factory()->withMarga($viewerMarga->id)->create();
    $root = Person::factory()->create([
        'name' => 'Borsak Junjungan',
        'marga_id' => $marga->id,
        'gender' => 'L',
    ]);
    $parent = $root;

    foreach (range(2, 7) as $generation) {
        $parent = Person::factory()->create([
            'name' => "Generasi {$generation}",
            'marga_id' => $generation >= 6 ? $descendantMarga->id : $marga->id,
            'father_id' => $parent->id,
            'gender' => 'L',
            'birth_order' => 1,
        ]);
    }

    $marga->update(['identity_person_id' => $root->id]);
    MargaAccessRequest::create([
        'requester_id' => $viewer->id,
        'marga_id' => $marga->id,
        'status' => MargaAccessRequest::STATUS_APPROVED,
    ]);

    $this->actingAs($viewer)
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $marga->id,
            'marga_direction' => 'lower',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people) => collect($people)->contains(
                fn (array $person) => $person['id'] === (string) $parent->id
                    && $person['name'] === 'Generasi 7',
            )));
});

test('marga tree marks people claimed by an account and exposes that account for contact requests', function () {
    $marga = Marga::factory()->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $claimedPerson = Person::factory()->create([
        'marga_id' => $marga->id,
        'gender' => 'L',
    ]);
    $account = User::factory()->withMarga($marga->id)->create([
        'current_person_id' => $claimedPerson->id,
    ]);
    $marga->update(['identity_person_id' => $claimedPerson->id]);
    MargaAccessRequest::create([
        'requester_id' => $viewer->id,
        'marga_id' => $marga->id,
        'status' => MargaAccessRequest::STATUS_APPROVED,
    ]);

    $this->actingAs($viewer)
        ->get(route('tarombo.fullscreen', [
            'view' => 'tree',
            'marga_id' => $marga->id,
            'marga_direction' => 'lower',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('identity.currentUserId', $viewer->id)
            ->where('people.0.claimedAccounts.0.id', $account->id)
            ->where('people.0.claimedAccounts.0.name', $account->name)
            ->where('people.0.claimedAccounts.0.isContact', true));
});
