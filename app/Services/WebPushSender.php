<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushSender
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.public_key'))
            && filled(config('webpush.private_key'))
            && class_exists(WebPush::class);
    }

    /**
     * Send one notification to every subscribed browser and forget the ones
     * the push service reports as gone.
     *
     * @param  array{title: string, body: string, url: string, tag?: string}  $message
     * @return int Number of subscriptions the notification was sent to.
     */
    public function sendToAll(array $message): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $payload = json_encode([
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/badge-72.png',
            ...$message,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sent = 0;

        PushSubscription::query()->chunkById(500, function (Collection $subscriptions) use ($payload, &$sent): void {
            $expired = $this->deliver($subscriptions, $payload);

            if ($expired !== []) {
                PushSubscription::query()
                    ->whereIn('endpoint_hash', array_map(PushSubscription::hashEndpoint(...), $expired))
                    ->delete();
            }

            $sent += $subscriptions->count() - count($expired);
        });

        return $sent;
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @return array<int, string> Endpoints that no longer accept notifications.
     */
    protected function deliver(Collection $subscriptions, string $payload): array
    {
        // A logger keeps the library's requirement warnings out of
        // trigger_error(), which Laravel would turn into exceptions.
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ], logger: logger()->getLogger());

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload);
        }

        $expired = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $expired[] = $report->getEndpoint();
            }
        }

        return $expired;
    }
}
