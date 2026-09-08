<?php

use App\Models\Event;
use App\Models\Marga;
use App\Models\User;
use App\Notifications\EventSubmitted;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('admin saves and replaces multiple event margas while retaining the selected owner', function () {
    $admin = User::factory()->asAdmin()->create();
    $ids = Marga::factory()->count(3)->create()->modelKeys();
    $payload = ['title' => 'Pesta bersama', 'description' => 'Acara lintas marga', 'date' => '2026-10-01', 'related_marga_ids' => [$ids[0], $ids[1]]];
    $this->actingAs($admin)->post(route('events.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::firstOrFail();
    expect($event->marga_id)->toBe($ids[0])
        ->and($event->relatedMargas()->allRelatedIds()->all())->toEqualCanonicalizing([$ids[0], $ids[1]]);
    $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->has('event.related_marga_ids', 2));
    $this->put(route('events.update', $event), [...$payload, 'related_marga_ids' => [$ids[2], $ids[0]]])->assertSessionHasNoErrors()->assertRedirect();
    expect($event->fresh()->marga_id)->toBe($ids[0])
        ->and($event->relatedMargas()->allRelatedIds()->all())->toEqualCanonicalizing([$ids[2], $ids[0]]);
    $this->put(route('events.update', $event), [...$payload, 'related_marga_ids' => [$ids[2]]])->assertSessionHasNoErrors()->assertRedirect();
    expect($event->fresh()->marga_id)->toBe($ids[2])
        ->and($event->relatedMargas()->allRelatedIds()->all())->toBe([$ids[2]]);
});

test('legacy events expose their existing marga as a selected related marga', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $event = Event::factory()->create(['marga_id' => $marga->id]);
    $this->actingAs($admin)->get(route('events.edit', $event))
        ->assertInertia(fn (Assert $page) => $page->where('event.related_marga_ids', [$marga->id]));
});

test('related event margas do not grant unrelated contributors review access', function () {
    Notification::fake();
    $primary = Marga::factory()->create();
    $related = Marga::factory()->create();
    $user = User::factory()->withMarga($primary->id)->create();
    $reviewer = User::factory()->asMainContributor()->withMarga($primary->id)->create();
    $outsider = User::factory()->asMainContributor()->withMarga($related->id)->create();
    $this->actingAs($user)->get(route('events.create'))->assertInertia(fn (Assert $page) => $page->has('margas', 2));
    $this->post(route('events.store'), ['title' => 'Event', 'description' => 'Deskripsi', 'date' => '2026-10-01', 'related_marga_ids' => [$related->id]])->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::firstOrFail();
    expect($event->marga_id)->toBe($primary->id)->and($event->status)->toBe(Event::STATUS_PENDING);
    Notification::assertSentTo($reviewer, EventSubmitted::class);
    Notification::assertNotSentTo($outsider, EventSubmitted::class);
});

test('invalid event marga selections cannot be saved', function (string $kind) {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $event = Event::factory()->create(['marga_id' => $marga->id]);
    $selection = match ($kind) {
        'empty' => [], 'scalar' => 'invalid', 'duplicate' => [$marga->id, $marga->id], 'missing' => [$marga->id + 1000],
    };
    $error = in_array($kind, ['empty', 'scalar']) ? 'related_marga_ids' : 'related_marga_ids.0';
    $payload = ['title' => 'Event', 'description' => 'Deskripsi', 'date' => '2026-10-01', 'related_marga_ids' => $selection];
    $this->actingAs($admin)->post(route('events.store'), $payload)->assertSessionHasErrors($error);
    $this->put(route('events.update', $event), $payload)->assertSessionHasErrors($error);
    expect(Event::count())->toBe(1)->and($event->fresh()->marga_id)->toBe($marga->id);
})->with(['empty', 'scalar', 'duplicate', 'missing']);
