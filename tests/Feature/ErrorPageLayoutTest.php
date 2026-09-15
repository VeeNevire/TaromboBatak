<?php

use App\Models\ChatGroup;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\FeedPost;
use App\Models\GroupMessage;
use App\Models\Marga;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated 404 includes the sidebar context', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/missing-authenticated-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/Page')
            ->where('status', 404)
            ->where('auth.user.id', $user->id)
            ->where('sidebarOpen', true)
            ->where('unreadContributionCount', 0));
});

test('a guest 404 keeps a null user context', function () {
    $this->get('/missing-guest-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/Page')
            ->where('status', 404)
            ->where('auth.user', null));
});

test('the shared sidebar count includes unread contact messages and pending requests', function () {
    $user = User::factory()->create();
    $contact = User::factory()->create();
    $conversation = Conversation::query()->create(
        Conversation::participantAttributes($user, $contact),
    );
    $conversation->messages()->create([
        'sender_id' => $contact->id,
        'body' => 'Pesan baru',
    ]);
    ContactRequest::query()->create([
        'requester_id' => $contact->id,
        'recipient_id' => $user->id,
        'status' => ContactRequest::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->get('/missing-authenticated-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadContactCount', 2));
});

test('the shared sidebar count includes unread group messages and news feed items', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create([
        'news_feed_read_at' => now()->subMinute(),
    ]);
    $sender = User::factory()->withMarga($marga->id)->create();
    $group = ChatGroup::query()->create([
        'owner_id' => $sender->id,
        'marga_id' => $marga->id,
        'name' => 'Grup keluarga',
    ]);
    $group->members()->attach([
        $user->id => ['role' => 'member', 'last_read_at' => now()->subMinute()],
        $sender->id => ['role' => 'owner', 'last_read_at' => null],
    ]);
    GroupMessage::query()->create([
        'chat_group_id' => $group->id,
        'sender_id' => $sender->id,
        'source' => GroupMessage::SOURCE_APP,
        'body' => 'Pesan grup baru',
    ]);
    FeedPost::query()->create([
        'user_id' => $sender->id,
        'body' => 'Kabar keluarga baru',
    ]);

    $this->actingAs($user)
        ->get('/missing-authenticated-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadGroupMessageCount', 1)
            ->where('unreadNewsFeedCount', 1));
});
