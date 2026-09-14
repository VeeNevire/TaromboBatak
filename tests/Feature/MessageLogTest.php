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
