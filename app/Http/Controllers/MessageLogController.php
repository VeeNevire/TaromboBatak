<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageLogController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $messages = Message::query()
            ->with(['sender:id,name', 'conversation.userOne:id,name', 'conversation.userTwo:id,name'])
            ->whereHas('conversation', fn ($query) => $query
                ->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(function (Message $message) use ($user): array {
                $conversation = $message->conversation;
                $counterpart = $conversation->user_one_id === $user->id
                    ? $conversation->userTwo
                    : $conversation->userOne;

                return [
                    'id' => $message->id,
                    'counterpart' => $counterpart->name,
                    'body' => $message->body,
                    'direction' => $message->sender_id === $user->id ? 'sent' : 'received',
                    'created_at' => $message->created_at?->format('d M Y H:i'),
                ];
            });

        return Inertia::render('message-logs/index', ['messages' => $messages]);
    }
}
