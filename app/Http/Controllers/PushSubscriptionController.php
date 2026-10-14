<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    /** Remember a browser that agreed to receive news notifications. */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $endpoint = $request->validated('endpoint');

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
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
