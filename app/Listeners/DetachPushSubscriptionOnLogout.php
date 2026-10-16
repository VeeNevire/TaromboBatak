<?php

namespace App\Listeners;

use App\Http\Controllers\PushSubscriptionController;
use App\Models\PushSubscription;
use Illuminate\Auth\Events\Logout;

/**
 * Stop sending an account's chat notifications to the device it signed out
 * from, so the next person using that device does not see them. The device
 * keeps receiving the public news notifications.
 */
class DetachPushSubscriptionOnLogout
{
    public function handle(Logout $event): void
    {
        $endpointHash = session(PushSubscriptionController::SESSION_KEY);

        if ($event->user === null || ! is_string($endpointHash)) {
            return;
        }

        PushSubscription::query()
            ->where('endpoint_hash', $endpointHash)
            ->where('user_id', $event->user->getAuthIdentifier())
            ->update(['user_id' => null]);
    }
}
