<?php

use App\Models\MargaNews;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Database\Eloquent\Collection;

function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        'content_encoding' => 'aes128gcm',
    ];
}

test('a guest can subscribe and the same endpoint is not stored twice', function () {
    $this->postJson(route('push-subscriptions.store'), subscriptionPayload())
        ->assertOk()
        ->assertJson(['subscribed' => true]);
    $this->postJson(route('push-subscriptions.store'), [
        ...subscriptionPayload(),
        'keys' => ['p256dh' => 'rotated-key', 'auth' => 'auth-token'],
    ])->assertOk();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and(PushSubscription::query()->sole())
        ->user_id->toBeNull()
        ->public_key->toBe('rotated-key');
});

test('a signed in subscriber is linked to the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), subscriptionPayload())
        ->assertOk();

    expect(PushSubscription::query()->sole()->user_id)->toBe($user->id);
});

test('a subscription needs an https endpoint and keys', function () {
    $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'http://insecure.test/push',
    ])->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

test('unsubscribing forgets the endpoint', function () {
    $subscription = PushSubscription::factory()->create();

    $this->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $subscription->endpoint])
        ->assertOk()
        ->assertJson(['subscribed' => false]);

    expect(PushSubscription::query()->exists())->toBeFalse();
});

test('news:push announces one newly approved article and never repeats it', function () {
    $news = MargaNews::factory()->approved()->create(['title' => 'Pesta Bona Taon Silaban']);
    MargaNews::factory()->create(['title' => 'Masih pending']);
    MargaNews::factory()->approved()->create(['push_notified_at' => now()->subDay()]);

    $this->mock(WebPushSender::class, function ($mock) use ($news) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendToAll')->once()->withArgs(fn (array $message) => $message['title'] === 'Pesta Bona Taon Silaban'
            && $message['url'] === route('marga-news.show', $news, false)
            && $message['tag'] === 'marga-news-'.$news->id)->andReturn(3);
    });

    $this->artisan('news:push')->assertSuccessful();
    $this->artisan('news:push')->assertSuccessful();

    expect($news->fresh()->push_notified_at)->not->toBeNull()
        ->and(MargaNews::query()->where('title', 'Masih pending')->sole()->push_notified_at)->toBeNull();
});

test('news:push sends one summary when several articles are approved together', function () {
    MargaNews::factory()->approved()->count(3)->create();

    $this->mock(WebPushSender::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendToAll')->once()->withArgs(fn (array $message) => $message['title'] === '3 berita baru di Tarombo'
            && $message['url'] === route('marga-news.index', absolute: false))->andReturn(1);
    });

    $this->artisan('news:push')->assertSuccessful();

    expect(MargaNews::query()->whereNull('push_notified_at')->exists())->toBeFalse();
});

test('news:push waits while web push is not configured', function () {
    $news = MargaNews::factory()->approved()->create();

    $this->mock(WebPushSender::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnFalse();
        $mock->shouldNotReceive('sendToAll');
    });

    $this->artisan('news:push')->assertSuccessful();

    expect($news->fresh()->push_notified_at)->toBeNull();
});

test('expired subscriptions are removed after sending', function () {
    config(['webpush.public_key' => 'public', 'webpush.private_key' => 'private']);
    $alive = PushSubscription::factory()->create();
    $gone = PushSubscription::factory()->create();

    $sender = new class($gone->endpoint) extends WebPushSender
    {
        public function __construct(private string $expiredEndpoint) {}

        public function isConfigured(): bool
        {
            return true;
        }

        protected function deliver(Collection $subscriptions, string $payload): array
        {
            return ['expired' => [$this->expiredEndpoint], 'failed' => []];
        }
    };

    expect($sender->sendToAll(['title' => 'Uji', 'body' => 'Isi', 'url' => '/']))->toBe(1)
        ->and(PushSubscription::query()->pluck('id')->all())->toBe([$alive->id]);
});

test('failed deliveries are reported but the subscription is kept', function () {
    $subscription = PushSubscription::factory()->create();

    $sender = new class extends WebPushSender
    {
        public function isConfigured(): bool
        {
            return true;
        }

        protected function deliver(Collection $subscriptions, string $payload): array
        {
            return ['expired' => [], 'failed' => [$subscriptions->first()->endpoint => '403 Forbidden']];
        }
    };

    expect($sender->sendTo(PushSubscription::query(), ['title' => 'Uji', 'body' => 'Isi', 'url' => '/']))
        ->toBe(['sent' => 0, 'expired' => 0, 'failed' => [$subscription->endpoint => '403 Forbidden']])
        ->and(PushSubscription::query()->exists())->toBeTrue();
});

test('webpush:test sends only to the latest subscription by default', function () {
    PushSubscription::factory()->create();
    $latest = PushSubscription::factory()->create();

    $this->mock(WebPushSender::class, function ($mock) use ($latest) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendTo')->once()->withArgs(fn ($query, array $message) => $query->pluck('id')->all() === [$latest->id]
            && $message['title'] === 'Tes notifikasi Tarombo')
            ->andReturn(['sent' => 1, 'expired' => 0, 'failed' => []]);
    });

    $this->artisan('webpush:test')
        ->expectsOutputToContain('Terkirim: 1 dari 1 perangkat.')
        ->assertSuccessful();
});

test('webpush:test can target every device of one account', function () {
    $user = User::factory()->create(['email' => 'ompung@example.com']);
    $phones = PushSubscription::factory()->count(2)->create(['user_id' => $user->id]);
    PushSubscription::factory()->create();

    $this->mock(WebPushSender::class, function ($mock) use ($phones) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendTo')->once()->withArgs(fn ($query) => $query->pluck('id')->sort()->values()->all() === $phones->pluck('id')->sort()->values()->all())
            ->andReturn(['sent' => 2, 'expired' => 0, 'failed' => []]);
    });

    $this->artisan('webpush:test', ['--user' => 'ompung@example.com'])->assertSuccessful();
});

test('webpush:test explains when no device has subscribed', function () {
    $this->mock(WebPushSender::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldNotReceive('sendTo');
    });

    $this->artisan('webpush:test')
        ->expectsOutputToContain('Tidak ada perangkat yang cocok')
        ->assertFailed();
});
