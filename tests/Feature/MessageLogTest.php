<?php

use App\Models\Conversation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a user can view only their own message log', function () {
    $user = User::factory()->create();
    $contact = User::factory()->create();
    $outsider = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::create(Conversation::participantAttributes($user, $contact));
    $conversation->messages()->create(['sender_id' => $user->id, 'body' => 'Pesan untuk kontributor']);
    $otherConversation = Conversation::create(Conversation::participantAttributes($outsider, $other));
    $otherConversation->messages()->create(['sender_id' => $outsider->id, 'body' => 'Pesan orang lain']);

    $this->actingAs($user)
        ->get(route('message-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('message-logs/index')
            ->has('messages.data', 1)
            ->where('messages.data.0.counterpart', $contact->name)
            ->where('messages.data.0.body', 'Pesan untuk kontributor')
            ->where('messages.data.0.direction', 'sent'));
});

test('the message log shows message times in WIB', function () {
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 19)->setTime(17, 30));

    $user = User::factory()->create();
    $contact = User::factory()->create();
    $conversation = Conversation::create(Conversation::participantAttributes($user, $contact));
    $conversation->messages()->create(['sender_id' => $user->id, 'body' => 'Halo']);

    // 17:30 UTC is 00:30 WIB on the next day, the same time the chat shows.
    $this->actingAs($user)
        ->get(route('message-logs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.data.0.created_at', '20 Oct 2026 00:30 WIB'));
});
