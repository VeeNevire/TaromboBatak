<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
     * Send one notification to every subscribed browser.
     *
     * @param  array{title: string, body: string, url: string, tag?: string}  $message
     * @return int Number of subscriptions the notification was sent to.
     */
    public function sendToAll(array $message): int
    {
        return $this->sendTo(PushSubscription::query(), $message)['sent'];
    }

    /**
     * Send one notification to the given subscriptions and forget the ones
     * the push service reports as gone.
     *
     * @param  Builder<PushSubscription>  $subscriptions
     * @param  array{title: string, body: string, url: string, tag?: string}  $message
     * @return array{sent: int, expired: int, failed: array<string, string>}
     */
    public function sendTo(Builder $subscriptions, array $message): array
    {
        $result = ['sent' => 0, 'expired' => 0, 'failed' => []];

        if (! $this->isConfigured()) {
            return $result;
        }

        $payload = json_encode([
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/badge-72.png',
            ...$message,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $subscriptions->chunkById(500, function (Collection $chunk) use ($payload, &$result): void {
            ['expired' => $expired, 'failed' => $failed] = $this->deliver($chunk, $payload);

            if ($expired !== []) {
                PushSubscription::query()
                    ->whereIn('endpoint_hash', array_map(PushSubscription::hashEndpoint(...), $expired))
                    ->delete();
            }

            $result['sent'] += $chunk->count() - count($expired) - count($failed);
            $result['expired'] += count($expired);
            $result['failed'] += $failed;
        });

        return $result;
    }

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @return array{expired: array<int, string>, failed: array<string, string>} Gone endpoints, and other failures keyed by endpoint with their reason.
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
        $failed = [];

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $expired[] = $report->getEndpoint();

                continue;
            }

            $failed[$report->getEndpoint()] = $report->getReason();
            Log::warning('Web push gagal dikirim.', [
                'endpoint' => Str::limit($report->getEndpoint(), 60),
                'reason' => $report->getReason(),
            ]);
        }

        return ['expired' => $expired, 'failed' => $failed];
    }
}
