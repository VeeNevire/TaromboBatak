<?php

use App\Events\GroupMessageSent;
use App\Events\MessageSent;
use App\Http\Controllers\PushSubscriptionController;
use App\Jobs\SendChatPushNotification;
use App\Models\ChatGroup;
use App\Models\Marga;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

test('a private message queues a push for the recipient', function () {
    Queue::fake();
    Event::fake([MessageSent::class]);
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create();
    $recipient = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($sender)
        ->post(route('contacts.messages.store', $recipient), ['body' => 'Horas, amang!'])
        ->assertRedirect();

    Queue::assertPushed(SendChatPushNotification::class, fn (SendChatPushNotification $job) => $job->kind === SendChatPushNotification::KIND_MESSAGE
        && $job->messageId === Message::query()->sole()->id);
});

test('the private message push reaches only the recipients devices and opens the chat', function () {
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create(['name' => 'Rehan']);
    $recipient = User::factory()->withMarga($marga->id)->create();
    $recipientDevice = PushSubscription::factory()->create(['user_id' => $recipient->id]);
    PushSubscription::factory()->create(['user_id' => $sender->id]);
    Event::fake([MessageSent::class]);
    Queue::fake();

    $this->actingAs($sender)
        ->post(route('contacts.messages.store', $recipient), ['body' => 'Apakah bisa dapat notifikasi di HP?']);

    $this->mock(WebPushSender::class, function ($mock) use ($recipientDevice, $sender) {
        $mock->shouldReceive('sendTo')->once()->withArgs(fn (Builder $subscriptions, array $message) => $subscriptions->pluck('id')->all() === [$recipientDevice->id]
            && $message['title'] === 'Rehan'
            && $message['body'] === 'Apakah bisa dapat notifikasi di HP?'
            && $message['url'] === route('contacts.show', $sender, absolute: false)
            && str_starts_with($message['tag'], 'chat-'));
    });

    SendChatPushNotification::forMessage(Message::query()->sole())->handle(app(WebPushSender::class));
});

test('a group message pushes to every member except the sender', function () {
    Event::fake([GroupMessageSent::class]);
    Queue::fake();
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create(['name' => 'Rehan']);
    $member = User::factory()->withMarga($marga->id)->create();
    $group = ChatGroup::query()->create([
        'owner_id' => $owner->id,
        'marga_id' => $marga->id,
        'name' => 'Grup Keluarga',
    ]);
    $group->members()->attach([$owner->id => ['role' => 'owner'], $member->id => ['role' => 'member']]);
    $memberDevice = PushSubscription::factory()->create(['user_id' => $member->id]);
    PushSubscription::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->post(route('groups.messages.store', $group), ['body' => 'Horas semua']);

    $message = $group->messages()->sole();
    Queue::assertPushed(SendChatPushNotification::class, fn (SendChatPushNotification $job) => $job->kind === SendChatPushNotification::KIND_GROUP
        && $job->messageId === $message->id);

    $this->mock(WebPushSender::class, function ($mock) use ($memberDevice, $group) {
        $mock->shouldReceive('sendTo')->once()->withArgs(fn (Builder $subscriptions, array $message) => $subscriptions->pluck('id')->all() === [$memberDevice->id]
            && $message['title'] === 'Grup Keluarga'
            && $message['body'] === 'Rehan: Horas semua'
            && $message['tag'] === 'group-'.$group->id);
    });

    SendChatPushNotification::forGroupMessage($message)->handle(app(WebPushSender::class));
});

test('logging out stops chat pushes to that device but keeps it subscribed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/device',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ])
        ->assertOk()
        ->assertSessionHas(PushSubscriptionController::SESSION_KEY);

    $this->post(route('logout'));

    expect(PushSubscription::query()->sole()->user_id)->toBeNull();
});
