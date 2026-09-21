<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Marga;
use App\Models\MargaChatConversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('a senders message reaches every contributor of the marga', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $member = User::factory()->asContributorMember()->withMarga($marga->id)->create();

    $this->actingAs($sender)
        ->post(route('marga.messages.store', $marga), ['body' => 'Halo pengurus'])
        ->assertRedirect();

    expect(Message::query()->count())->toBe(2)
        ->and(Conversation::between($sender, $main)->exists())->toBeTrue()
        ->and(Conversation::between($sender, $member)->exists())->toBeTrue();

    expect(Message::query()->where('body', 'Halo pengurus')->count())->toBe(2);
});

test('the marga chat page aggregates the senders thread with contributors', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    User::factory()->asMainContributor()->withMarga($marga->id)->create();
    User::factory()->asContributorMember()->withMarga($marga->id)->create();

    $this->actingAs($sender)->post(route('marga.messages.store', $marga), ['body' => 'Pesan saya']);

    $this->actingAs($sender)
        ->get(route('marga.chat', $marga))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marga/chat')
            ->has('members', 2)
            ->has('messages', 2));
});

test('a contributor sees the senders message on the marga chat page', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $this->actingAs($sender)
        ->post(route('marga.messages.store', $marga), ['body' => 'Pesan untuk pengurus']);

    $this->actingAs($main)
        ->get(route('marga.chat', $marga))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.body', 'Pesan untuk pengurus')
            ->where('messages.0.sender_id', $sender->id)
            ->where('members.0.id', $sender->id));
});

test('a contributor reply is delivered to the original sender', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $this->actingAs($sender)
        ->post(route('marga.messages.store', $marga), ['body' => 'Halo pengurus']);

    $this->actingAs($main)
        ->post(route('marga.messages.store', $marga), [
            'body' => 'Halo juga',
            'recipient_id' => $sender->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $conversation = Conversation::between($sender, $main)->firstOrFail();

    expect(Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('sender_id', $main->id)
        ->where('body', 'Halo juga')
        ->exists())->toBeTrue();

    $this->actingAs($sender)
        ->get(route('marga.chat', $marga))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.1.body', 'Halo juga')
            ->where('messages.1.sender_id', $main->id));
});

test('a contributor reply is visible only to the sender', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $other = User::factory()->asContributorMember()->withMarga($marga->id)->create();

    $conversation = Conversation::query()->firstOrCreate(
        Conversation::participantAttributes($sender, $main),
    );
    MargaChatConversation::query()->create([
        'marga_id' => $marga->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
    ]);
    $conversation->messages()->create([
        'sender_id' => $main->id,
        'body' => 'Balasan untukmu',
    ]);

    $this->actingAs($sender)
        ->get(route('marga.chat', $marga))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.body', 'Balasan untukmu')
            ->where('messages.0.sender_id', $main->id));

    $this->actingAs($other)
        ->get(route('marga.chat', $marga))
        ->assertInertia(fn (Assert $page) => $page->has('messages', 0));
});

test('guests cannot open or post to a marga chat', function () {
    $marga = Marga::factory()->create();

    $this->get(route('marga.chat', $marga))->assertRedirect(route('login'));

    $this->post(route('marga.messages.store', $marga), ['body' => 'Halo'])
        ->assertRedirect(route('login'));
});

test('a marga message requires a body', function () {
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);

    $this->actingAs($sender)
        ->from(route('marga.chat', $marga))
        ->post(route('marga.messages.store', $marga), ['body' => '   '])
        ->assertSessionHasErrors('body');
});

test('sending to a marga without contributors does not create messages', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);

    $this->actingAs($sender)
        ->post(route('marga.messages.store', $marga), ['body' => 'Halo'])
        ->assertRedirect();

    expect(Message::query()->count())->toBe(0);
});

test('each marga message broadcast notifies its recipient', function () {
    Event::fake([MessageSent::class]);
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    User::factory()->asMainContributor()->withMarga($marga->id)->create();
    User::factory()->asContributorMember()->withMarga($marga->id)->create();

    $this->actingAs($sender)->post(route('marga.messages.store', $marga), ['body' => 'Halo']);

    Event::assertDispatchedTimes(MessageSent::class, 2);
});

test('unread marga replies are counted then cleared after opening the chat', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->withMarga($marga->id)->create(['role' => 'user']);
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();

    $conversation = Conversation::query()->firstOrCreate(
        Conversation::participantAttributes($sender, $main),
    );
    MargaChatConversation::query()->create([
        'marga_id' => $marga->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
    ]);
    $conversation->messages()->create([
        'sender_id' => $main->id,
        'body' => 'Balasan',
    ]);

    $this->actingAs($sender)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadMargaMessageCount', 1)
            ->where('margas.0.unread_count', 1));

    $this->actingAs($sender)
        ->get(route('marga.chat', $marga))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadMargaMessageCount', 0));

    $this->actingAs($sender)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('margas.0.unread_count', 0));
});

test('a contributor linked only through marga management receives the message', function () {
    $marga = Marga::factory()->create(['is_public' => true, 'name' => 'Ambarita']);
    $homeMarga = Marga::factory()->create();

    $contributor = User::factory()->asMainContributor()->withMarga($homeMarga->id)->create();
    $contributor->managedMargas()->attach($marga);

    $sender = User::factory()->create();

    $this->actingAs($sender)
        ->post(route('marga.messages.store', $marga), ['body' => 'Halo Ambarita'])
        ->assertRedirect();

    expect(Conversation::between($sender, $contributor)->exists())->toBeTrue()
        ->and(MargaChatConversation::query()
            ->where('marga_id', $marga->id)
            ->where('sender_id', $sender->id)
            ->exists())->toBeTrue();

    $this->actingAs($contributor)
        ->get(route('marga.chat', $marga))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.body', 'Halo Ambarita')
            ->where('messages.0.sender_id', $sender->id)
            ->where('members.0.id', $sender->id));
});

test('margas with unread replies are listed first', function () {
    $alpha = Marga::factory()->create(['is_public' => true, 'name' => 'Alpha']);
    $beta = Marga::factory()->create(['is_public' => true, 'name' => 'Beta']);
    $sender = User::factory()->withMarga($beta->id)->create(['role' => 'user']);
    $main = User::factory()->asMainContributor()->withMarga($beta->id)->create();

    $conversation = Conversation::query()->firstOrCreate(
        Conversation::participantAttributes($sender, $main),
    );
    MargaChatConversation::query()->create([
        'marga_id' => $beta->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
    ]);
    $conversation->messages()->create([
        'sender_id' => $main->id,
        'body' => 'Balasan',
    ]);

    $this->actingAs($sender)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('margas.0.id', $beta->id)
            ->where('margas.1.id', $alpha->id));
});
