<?php

use App\Actions\CreateTelegramLinkToken;
use App\Jobs\ProcessTelegramUpdate;
use App\Jobs\SendGroupMessageToTelegram;
use App\Models\ChatGroup;
use App\Models\GroupMessage;
use App\Models\Marga;
use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\TelegramUpdate;
use App\Models\User;
use App\Services\TelegramUpdateProcessor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'tarombo_test_bot',
    ]);
    Http::preventStrayRequests();
});

test('a start token links one Telegram identity to the application user', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    $user = User::factory()->create();
    $plainToken = app(CreateTelegramLinkToken::class)->forAccount($user);

    app(TelegramUpdateProcessor::class)->process([
        'message' => [
            'message_id' => 10,
            'date' => now()->timestamp,
            'text' => '/start '.$plainToken,
            'chat' => ['id' => 9988, 'type' => 'private'],
            'from' => ['id' => 9988, 'first_name' => 'Bayo', 'username' => 'bayo'],
        ],
    ]);

    $account = TelegramAccount::query()->firstOrFail();
    expect($account->user_id)->toBe($user->id)
        ->and($account->telegram_user_id)->toBe(9988)
        ->and(TelegramLinkToken::query()->firstOrFail()->used_at)->not->toBeNull();
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendMessage'));
});

test('the profile connect button creates a one-time token and redirects to the bot', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('telegram-connection.store'))
        ->assertRedirectContains('https://t.me/tarombo_test_bot?start=');

    expect(TelegramLinkToken::query()->count())->toBe(1)
        ->and(TelegramLinkToken::query()->firstOrFail()->purpose)->toBe(TelegramLinkToken::PURPOSE_ACCOUNT);
});

test('a group link requires the linked owner to be a Telegram administrator', function () {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/getChatMember')) {
            return Http::response(['ok' => true, 'result' => ['status' => 'administrator']]);
        }

        return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
    });
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    TelegramAccount::query()->create([
        'user_id' => $owner->id,
        'telegram_user_id' => 77,
        'private_chat_id' => 77,
        'display_name' => 'Owner Telegram',
        'linked_at' => now(),
    ]);
    $group = ChatGroup::query()->create(['owner_id' => $owner->id, 'marga_id' => $marga->id, 'name' => 'Grup Raja']);
    $group->members()->attach($owner->id, ['role' => 'owner']);
    $code = app(CreateTelegramLinkToken::class)->forGroup($owner, $group);

    app(TelegramUpdateProcessor::class)->process([
        'message' => [
            'message_id' => 11,
            'date' => now()->timestamp,
            'text' => '/link '.$code,
            'chat' => ['id' => -1001234, 'type' => 'supergroup', 'title' => 'Telegram Raja'],
            'from' => ['id' => 77, 'first_name' => 'Owner'],
        ],
    ]);

    expect($group->fresh()->telegram_chat_id)->toBe(-1001234)
        ->and($group->fresh()->telegram_title)->toBe('Telegram Raja');
});

test('a private link command tells the user to send it inside the Telegram group', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    app(TelegramUpdateProcessor::class)->process([
        'message' => [
            'message_id' => 12,
            'date' => now()->timestamp,
            'text' => '/link ABC1234567',
            'chat' => ['id' => 88, 'type' => 'private'],
            'from' => ['id' => 88, 'first_name' => 'Pemilik'],
        ],
    ]);

    Http::assertSent(fn (Request $request) => str_contains(
        (string) $request->data()['text'],
        'harus dikirim di dalam grup Telegram',
    ));
});

test('repeated Telegram updates create only one group message and preserve an unlinked sender name', function () {
    Http::fake();
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $group = ChatGroup::query()->create([
        'owner_id' => $owner->id,
        'marga_id' => $marga->id,
        'name' => 'Grup',
        'telegram_chat_id' => -10099,
    ]);
    $group->members()->attach($owner->id, ['role' => 'owner']);
    $payload = ['message' => [
        'message_id' => 55,
        'date' => now()->timestamp,
        'text' => 'Pesan dari Telegram',
        'chat' => ['id' => -10099, 'type' => 'supergroup'],
        'from' => ['id' => 404, 'first_name' => 'Anggota', 'last_name' => 'Telegram'],
    ]];

    app(TelegramUpdateProcessor::class)->process($payload);
    app(TelegramUpdateProcessor::class)->process($payload);

    expect(GroupMessage::query()->count())->toBe(1)
        ->and(GroupMessage::query()->firstOrFail()->sender_id)->toBeNull()
        ->and(GroupMessage::query()->firstOrFail()->telegram_sender_name)->toBe('Anggota Telegram');
});

test('an application group message is sent once through the bot', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 901]])]);
    $marga = Marga::factory()->create();
    $sender = User::factory()->withMarga($marga->id)->create(['name' => 'Si Raja']);
    $group = ChatGroup::query()->create(['owner_id' => $sender->id, 'marga_id' => $marga->id, 'name' => 'Grup', 'telegram_chat_id' => -1001]);
    $message = GroupMessage::query()->create([
        'chat_group_id' => $group->id,
        'sender_id' => $sender->id,
        'source' => GroupMessage::SOURCE_APP,
        'body' => 'Horas',
        'telegram_delivery_status' => GroupMessage::DELIVERY_PENDING,
    ]);

    app()->call([new SendGroupMessageToTelegram($message->id), 'handle']);

    expect($message->fresh()->telegram_message_id)->toBe(901)
        ->and($message->fresh()->telegram_delivery_status)->toBe(GroupMessage::DELIVERY_SENT);
    Http::assertSentCount(1);
});

test('the polling command persists updates and queues their processing without a webhook', function () {
    Queue::fake([ProcessTelegramUpdate::class]);
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/getWebhookInfo')) {
            return Http::response(['ok' => true, 'result' => ['url' => '']]);
        }

        $update = [
            'update_id' => 7788,
            'message' => [
                'message_id' => 1,
                'text' => 'Horas',
                'chat' => ['id' => -100, 'type' => 'supergroup'],
                'from' => ['id' => 1, 'first_name' => 'Tester'],
            ],
        ];

        return Http::response(['ok' => true, 'result' => [$update]]);
    });

    $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();

    expect(TelegramUpdate::query()->where('update_id', 7788)->exists())->toBeTrue();
    Queue::assertPushed(ProcessTelegramUpdate::class);
});
