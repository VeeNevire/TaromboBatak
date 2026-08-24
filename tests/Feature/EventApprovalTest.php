<?php

use App\Models\Event;
use App\Models\Marga;
use App\Models\User;
use App\Notifications\EventSubmitted;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function eventPayload(array $overrides = []): array
{
    return [
        'title' => 'Pesta Bona Taon',
        'description' => 'Kegiatan bersama keluarga besar.',
        'location' => 'Balige',
        'registration_url' => 'https://example.com/daftar',
        'date' => now()->addMonth()->toDateString(),
        'published' => true,
        ...$overrides,
    ];
}

test('an ordinary user submits a pending event and contributors in the same marga are notified', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $member = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $otherContributor = User::factory()->asMainContributor()->withMarga($otherMarga->id)->create();

    $this->actingAs($user)
        ->post(route('events.store'), eventPayload())
        ->assertRedirect(route('events.index'))
        ->assertSessionHasNoErrors();

    $event = Event::firstOrFail();

    expect($event->created_by)->toBe($user->id)
        ->and($event->marga_id)->toBe($marga->id)
        ->and($event->status)->toBe(Event::STATUS_PENDING)
        ->and($event->published)->toBeFalse();

    Notification::assertSentTo([$main, $member], EventSubmitted::class);
    Notification::assertNotSentTo($otherContributor, EventSubmitted::class);
});

test('a contributor creates an approved event for their own marga', function () {
    $marga = Marga::factory()->create();
    $contributor = User::factory()->asContributorMember()->withMarga($marga->id)->create();

    $this->actingAs($contributor)
        ->post(route('events.store'), eventPayload())
        ->assertRedirect(route('events.index'));

    $event = Event::firstOrFail();

    expect($event->status)->toBe(Event::STATUS_APPROVED)
        ->and($event->published)->toBeTrue()
        ->and($event->marga_id)->toBe($marga->id)
        ->and($event->created_by)->toBe($contributor->id);
});

test('a contributor can approve an event only for their own marga', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $reviewer = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $outsider = User::factory()->asContributorMember()->withMarga($otherMarga->id)->create();
    $event = Event::factory()->pending()->create(['marga_id' => $marga->id]);

    $this->actingAs($outsider)
        ->post(route('events.approve', $event), ['version' => $event->review_version])
        ->assertForbidden();

    $this->actingAs($reviewer)
        ->post(route('events.approve', $event), ['version' => $event->review_version])
        ->assertRedirect(route('contributions.index', ['tab' => 'events']));

    expect($event->fresh()->status)->toBe(Event::STATUS_APPROVED)
        ->and($event->fresh()->published)->toBeTrue()
        ->and($event->fresh()->reviewed_by)->toBe($reviewer->id);
});

test('a rejected event stores the reason and remains hidden from the public page', function () {
    $marga = Marga::factory()->create();
    $reviewer = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $event = Event::factory()->pending()->create(['marga_id' => $marga->id]);

    $this->actingAs($reviewer)
        ->post(route('events.reject', $event), [
            'reason' => 'Informasi belum lengkap.',
            'version' => $event->review_version,
        ])
        ->assertRedirect(route('contributions.index', ['tab' => 'events']));

    expect($event->fresh()->status)->toBe(Event::STATUS_REJECTED)
        ->and($event->fresh()->published)->toBeFalse()
        ->and($event->fresh()->rejection_reason)->toBe('Informasi belum lengkap.');

    $this->get(route('kegiatan.index'))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));
});

test('event lists are scoped for ordinary users contributors and admins', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $admin = User::factory()->asAdmin()->create();
    Event::factory()->create(['title' => 'Milik User', 'created_by' => $user->id, 'marga_id' => $marga->id]);
    Event::factory()->create(['title' => 'Satu Marga', 'marga_id' => $marga->id]);
    Event::factory()->create(['title' => 'Marga Lain', 'marga_id' => $otherMarga->id]);

    $this->actingAs($user)->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.title', 'Milik User'));

    $this->actingAs($contributor)->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 2));

    $this->actingAs($admin)->get(route('events.index'))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 3));
});

test('event approval queues are listed in contributions by marga and globally for admins', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $admin = User::factory()->asAdmin()->create();
    Event::factory()->pending()->create(['marga_id' => $marga->id]);
    Event::factory()->pending()->create(['marga_id' => $otherMarga->id]);

    $this->actingAs($contributor)
        ->get(route('contributions.index'))
        ->assertInertia(fn (Assert $page) => $page->has('eventRequests.data', 1));

    $this->actingAs($admin)
        ->get(route('contributions.index'))
        ->assertInertia(fn (Assert $page) => $page->has('eventRequests.data', 2));
});

test('only the creator and staff can edit or delete an event', function () {
    $marga = Marga::factory()->create();
    $creator = User::factory()->withMarga($marga->id)->create();
    $otherUser = User::factory()->withMarga($marga->id)->create();
    $admin = User::factory()->asAdmin()->create();
    $event = Event::factory()->create(['created_by' => $creator->id, 'marga_id' => $marga->id]);

    expect($creator->can('update', $event))->toBeTrue()
        ->and($creator->can('delete', $event))->toBeTrue()
        ->and($otherUser->can('update', $event))->toBeFalse()
        ->and($otherUser->can('delete', $event))->toBeFalse()
        ->and($admin->can('update', $event))->toBeTrue();
});

test('editing an approved event as an ordinary user requires approval again', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $event = Event::factory()->create([
        'created_by' => $user->id,
        'marga_id' => $marga->id,
        'status' => Event::STATUS_APPROVED,
        'published' => true,
    ]);

    $this->actingAs($user)
        ->put(route('events.update', $event), eventPayload(['title' => 'Judul Diperbarui']))
        ->assertRedirect(route('events.index'));

    expect($event->fresh()->title)->toBe('Judul Diperbarui')
        ->and($event->fresh()->status)->toBe(Event::STATUS_PENDING)
        ->and($event->fresh()->published)->toBeFalse();

    Notification::assertSentTo($contributor, EventSubmitted::class);
});

test('a sub-admin editing a pending event cannot bypass approval', function () {
    $marga = Marga::factory()->create();
    $subAdmin = User::factory()->asSubAdmin()->create();
    $event = Event::factory()->pending()->create(['marga_id' => $marga->id]);

    $this->actingAs($subAdmin)
        ->put(route('events.update', $event), eventPayload([
            'title' => 'Diperbaiki Sub Admin',
            'marga_id' => $marga->id,
        ]))
        ->assertRedirect(route('events.index'));

    expect($event->fresh()->title)->toBe('Diperbaiki Sub Admin')
        ->and($event->fresh()->status)->toBe(Event::STATUS_PENDING)
        ->and($event->fresh()->published)->toBeFalse();
});

test('a sub-admin edit preserves existing review audit data', function () {
    $marga = Marga::factory()->create();
    $reviewer = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $subAdmin = User::factory()->asSubAdmin()->create();
    $reviewedAt = now()->subDay()->startOfSecond();
    $event = Event::factory()->create([
        'marga_id' => $marga->id,
        'status' => Event::STATUS_REJECTED,
        'published' => false,
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => $reviewedAt,
        'rejection_reason' => 'Informasi belum lengkap.',
    ]);

    $this->actingAs($subAdmin)
        ->put(route('events.update', $event), eventPayload([
            'title' => 'Diperbaiki Tanpa Mengubah Audit',
            'marga_id' => $marga->id,
        ]))
        ->assertRedirect(route('events.index'));

    expect($event->fresh()->status)->toBe(Event::STATUS_REJECTED)
        ->and($event->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and($event->fresh()->reviewed_at->equalTo($reviewedAt))->toBeTrue()
        ->and($event->fresh()->rejection_reason)->toBe('Informasi belum lengkap.');
});

test('pending events never appear on public pages even if published is inconsistent', function () {
    $event = Event::factory()->pending()->create([
        'title' => 'Event Bocor',
        'published' => true,
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->has('events', 0));

    $this->get(route('kegiatan.index'))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 0));

    $this->get(route('kegiatan.show', $event))->assertNotFound();
});

test('a reviewer cannot approve a stale event version', function () {
    Notification::fake();
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $reviewer = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $event = Event::factory()->pending()->create([
        'created_by' => $user->id,
        'marga_id' => $marga->id,
    ]);
    $staleVersion = $event->review_version;

    $this->actingAs($user)
        ->put(route('events.update', $event), eventPayload(['title' => 'Konten Terbaru']))
        ->assertRedirect(route('events.index'));

    $this->actingAs($reviewer)
        ->post(route('events.approve', $event), ['version' => $staleVersion])
        ->assertRedirect(route('contributions.index', ['tab' => 'events']));

    expect($event->fresh()->status)->toBe(Event::STATUS_PENDING)
        ->and($event->fresh()->review_version)->toBe($staleVersion + 1);
});
