<?php

namespace App\Http\Controllers;

use App\Models\ChatGroup;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The "Tandai dibaca" button of a chat notification. The service worker
 * cannot send the CSRF token, so these routes are authorised by the signed
 * URL that was put in the notification for one reader.
 */
class PushReadController extends Controller
{
    public function conversation(Conversation $conversation, User $reader): JsonResponse
    {
        abort_unless($conversation->includes($reader), 403);

        $conversation->markReadBy($reader);

        return response()->json(['read' => true]);
    }

    public function group(ChatGroup $chatGroup, User $reader): JsonResponse
    {
        $chatGroup->memberships()
            ->where('user_id', $reader->id)
            ->update(['last_read_at' => now()]);

        return response()->json(['read' => true]);
    }
}
