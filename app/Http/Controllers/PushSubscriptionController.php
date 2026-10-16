<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    /** Session key holding this device's endpoint, detached again on logout. */
    public const SESSION_KEY = 'push_endpoint_hash';

    /**
     * Remember a browser that agreed to receive notifications, linked to the
     * signed in account so its chat messages reach this device.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $endpoint = $request->validated('endpoint');
        $endpointHash = PushSubscription::hashEndpoint($endpoint);

        if ($request->user() !== null) {
            $request->session()->put(self::SESSION_KEY, $endpointHash);
        }

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'endpoint' => $endpoint,
                'public_key' => $request->validated('keys.p256dh'),
                'auth_token' => $request->validated('keys.auth'),
                'content_encoding' => $request->validated('content_encoding') ?? 'aes128gcm',
                'user_id' => $request->user()?->id,
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ],
        );

        return response()->json(['subscribed' => true]);
    }

    /** Forget a browser that turned notifications off. */
    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string', 'max:2000']])['endpoint'];

        PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->delete();

        return response()->json(['subscribed' => false]);
    }
}
