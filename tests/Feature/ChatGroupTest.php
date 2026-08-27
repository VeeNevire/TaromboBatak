<?php

use App\Events\GroupMessageSent;
use App\Jobs\SendTelegramAnnouncementRecipient;
use App\Models\ChatGroup;
use App\Models\GroupMessage;
use App\Models\Marga;
use App\Models\TelegramAccount;
use App\Models\TelegramAnnouncement;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('a user can create a group with contacts from the same marga', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $member = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($owner)
        ->post(route('groups.store'), [
            'name' => 'Pomparan Raja',
            'member_ids' => [$member->id],
        ])
        ->assertRedirect();

    $group = ChatGroup::query()->firstOrFail();

    expect($group->owner_id)->toBe($owner->id)
        ->and($group->marga_id)->toBe($marga->id)
        ->and($group->members()->pluck('users.id')->all())->toContain($owner->id, $member->id);
});

test('a group cannot contain an account from another marga', function () {
    $owner = User::factory()->withMarga(Marga::factory()->create()->id)->create();
    $outsider = User::factory()->withMarga(Marga::factory()->create()->id)->create();

    $this->actingAs($owner)
        ->post(route('groups.store'), [
            'name' => 'Grup Terlarang',
            'member_ids' => [$outsider->id],
        ])
        ->assertSessionHasErrors('member_ids');

    expect(ChatGroup::query()->count())->toBe(0);
});

test('members can open and send messages while outsiders are rejected', function () {
    Event::fake([GroupMessageSent::class]);
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $member = User::factory()->withMarga($marga->id)->create();
    $outsider = User::factory()->withMarga($marga->id)->create();
    $group = ChatGroup::query()->create([
        'owner_id' => $owner->id,
        'marga_id' => $marga->id,
        'name' => 'Grup Keluarga',
    ]);
    $group->members()->attach([$owner->id => ['role' => 'owner'], $member->id => ['role' => 'member']]);

    $this->actingAs($member)
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('groups/show')
            ->where('group.name', 'Grup Keluarga'));

    $this->actingAs($member)
        ->post(route('groups.messages.store', $group), ['body' => '  Horas semua  '])
        ->assertRedirect(route('groups.show', $group));

    expect(GroupMessage::query()->firstOrFail()->body)->toBe('Horas semua');
    $this->actingAs($outsider)->get(route('groups.show', $group))->assertForbidden();
});

test('only the owner can change group members', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $member = User::factory()->withMarga($marga->id)->create();
    $replacement = User::factory()->withMarga($marga->id)->create();
    $group = ChatGroup::query()->create(['owner_id' => $owner->id, 'marga_id' => $marga->id, 'name' => 'Grup']);
    $group->members()->attach([$owner->id => ['role' => 'owner'], $member->id => ['role' => 'member']]);

    $this->actingAs($member)
        ->put(route('groups.members.update', $group), ['member_ids' => [$replacement->id]])
        ->assertRedirect();

    expect($group->members()->pluck('users.id')->all())
        ->toContain($owner->id, $member->id)
        ->not->toContain($replacement->id);

    $this->actingAs($owner)
        ->put(route('groups.members.update', $group), ['member_ids' => [$replacement->id]])
        ->assertRedirect(route('groups.show', $group));

    expect($group->members()->pluck('users.id')->all())
        ->toContain($owner->id, $replacement->id)
        ->not->toContain($member->id);
});

test('a group owner can queue announcements for linked contacts', function () {
    Queue::fake([SendTelegramAnnouncementRecipient::class]);
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $contact = User::factory()->withMarga($marga->id)->create();
    $group = ChatGroup::query()->create(['owner_id' => $owner->id, 'marga_id' => $marga->id, 'name' => 'Grup']);
    $group->members()->attach($owner->id, ['role' => 'owner']);
    TelegramAccount::query()->create([
        'user_id' => $contact->id,
        'telegram_user_id' => 12345,
        'private_chat_id' => 12345,
        'display_name' => $contact->name,
        'linked_at' => now(),
    ]);

    $this->actingAs($owner)->post(route('announcements.store'), [
        'target_type' => 'contacts',
        'body' => 'Pengumuman keluarga',
        'contact_ids' => [$contact->id],
    ])->assertRedirect(route('announcements.index'));

    expect(TelegramAnnouncement::query()->count())->toBe(1);
    Queue::assertPushed(SendTelegramAnnouncementRecipient::class);
});
