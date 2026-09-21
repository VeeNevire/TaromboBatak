<?php

use App\Events\MargaMessageSent;
use App\Models\Marga;
use App\Models\MargaMessage;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('a signed in user can open a marga chat room and see its members', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $viewer = User::factory()->create();
    $member = User::factory()->create([
        'marga_id' => $marga->id,
        'role' => 'contributor_main',
        'name' => 'Kontributor Utama',
    ]);

    $this->actingAs($viewer)
        ->get(route('marga.chat', $marga))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marga/chat')
            ->where('marga.id', $marga->id)
            ->has('members', 1)
            ->where('members.0.id', $member->id)
            ->has('messages', 0));
});

test('any signed in user can post to a marga chat room', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->create();

    $this->actingAs($sender)
        ->post(route('marga.messages.store', $marga), [
            'body' => '  Mohon bantuan data silsilah.  ',
        ])
        ->assertRedirect();

    $message = MargaMessage::query()->firstOrFail();

    expect($message->marga_id)->toBe($marga->id)
        ->and($message->sender_id)->toBe($sender->id)
        ->and($message->body)->toBe('Mohon bantuan data silsilah.');
});

test('the chat room shows previous messages in order', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->create();
    $first = MargaMessage::query()->create([
        'marga_id' => $marga->id,
        'sender_id' => $sender->id,
        'body' => 'Pesan pertama',
    ]);
    $second = MargaMessage::query()->create([
        'marga_id' => $marga->id,
        'sender_id' => $sender->id,
        'body' => 'Pesan kedua',
    ]);

    $this->actingAs($sender)
        ->get(route('marga.chat', $marga))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.id', $first->id)
            ->where('messages.1.id', $second->id));
});

test('guests cannot open or post to a marga chat room', function () {
    $marga = Marga::factory()->create();

    $this->get(route('marga.chat', $marga))->assertRedirect(route('login'));

    $this->post(route('marga.messages.store', $marga), ['body' => 'Halo'])
        ->assertRedirect(route('login'));
});

test('a marga message requires a body', function () {
    $marga = Marga::factory()->create();
    $sender = User::factory()->create();

    $this->actingAs($sender)
        ->from(route('marga.chat', $marga))
        ->post(route('marga.messages.store', $marga), ['body' => '   '])
        ->assertSessionHasErrors('body');
});

test('unread marga messages are counted in the sidebar and marga list, then cleared after opening the chat', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $member = User::factory()->create(['marga_id' => $marga->id, 'role' => 'user']);
    $sender = User::factory()->create(['marga_id' => $marga->id, 'role' => 'user']);
    $outsider = User::factory()->create();

    MargaMessage::query()->create([
        'marga_id' => $marga->id,
        'sender_id' => $sender->id,
        'body' => 'Pesan baru',
    ]);

    $this->actingAs($member)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadMargaMessageCount', 1)
            ->where('margas.0.unread_count', 1));

    $this->actingAs($sender)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadMargaMessageCount', 0)
            ->where('margas.0.unread_count', 0));

    $this->actingAs($outsider)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadMargaMessageCount', 1)
            ->where('margas.0.unread_count', 1));

    $this->actingAs($member)
        ->get(route('marga.chat', $marga))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadMargaMessageCount', 0));

    $this->actingAs($member)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('margas.0.unread_count', 0));
});

test('posting a marga message broadcasts it to the marga channel', function () {
    Event::fake([MargaMessageSent::class]);
    $marga = Marga::factory()->create();
    $sender = User::factory()->create();

    $this->actingAs($sender)->post(route('marga.messages.store', $marga), [
        'body' => 'Halo semua',
    ]);

    Event::assertDispatched(MargaMessageSent::class, function (MargaMessageSent $event) use ($marga): bool {
        return $event->message->marga_id === $marga->id
            && $event->broadcastAs() === 'marga.message.sent'
            && $event->broadcastOn()[0]->name === 'private-marga.'.$marga->id;
    });
});

test('margas with unread messages are listed first', function () {
    $alpha = Marga::factory()->create(['is_public' => true, 'name' => 'Alpha']);
    $beta = Marga::factory()->create(['is_public' => true, 'name' => 'Beta']);
    $member = User::factory()->create(['marga_id' => $beta->id, 'role' => 'user']);

    MargaMessage::query()->create([
        'marga_id' => $beta->id,
        'sender_id' => null,
        'body' => 'Halo',
    ]);

    $this->actingAs($member)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('margas.0.id', $beta->id)
            ->where('margas.1.id', $alpha->id));
});
