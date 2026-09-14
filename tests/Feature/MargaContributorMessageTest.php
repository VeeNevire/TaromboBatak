<?php

use App\Models\Conversation;
use App\Models\Marga;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('logged in users can see a marga contributor and send them a message', function () {
    $marga = Marga::factory()->create(['is_public' => true]);
    $sender = User::factory()->create();
    $main = User::factory()->create([
        'marga_id' => $marga->id,
        'role' => 'contributor_main',
        'name' => 'Kontributor Utama',
    ]);
    $member = User::factory()->create([
        'marga_id' => $marga->id,
        'role' => 'contributor_member',
        'name' => 'Kontributor Anggota',
    ]);

    $this->actingAs($sender)
        ->get(route('marga.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSendContributorMessage', true)
            ->has('margas.0.contributors', 2)
            ->where('margas.0.contributors.0.id', $member->id)
            ->where('margas.0.contributors.1.id', $main->id));

    $this->post(route('marga.contributors.messages.store', [$marga, $main]), [
        'body' => 'Mohon bantuan untuk data silsilah.',
    ])->assertRedirect();

    $conversation = Conversation::between($sender, $main)->firstOrFail();

    expect($conversation)->not->toBeNull()
        ->and(Message::query()->where('conversation_id', $conversation->id)->value('body'))
        ->toBe('Mohon bantuan untuk data silsilah.')
        ->and($sender->canChatWith($main))->toBeTrue()
        ->and($main->canChatWith($sender))->toBeTrue();
});

test('guests cannot send a message to a marga contributor', function () {
    $marga = Marga::factory()->create();
    $contributor = User::factory()->create([
        'marga_id' => $marga->id,
        'role' => 'contributor_main',
    ]);

    $this->post(route('marga.contributors.messages.store', [$marga, $contributor]), [
        'body' => 'Halo',
    ])->assertRedirect(route('login'));
});
