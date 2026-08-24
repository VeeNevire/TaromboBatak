<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, User $contact): RedirectResponse
    {
        /** @var User $sender */
        $sender = $request->user();

        $message = DB::transaction(function () use ($request, $sender, $contact): Message {
            $conversation = Conversation::query()->firstOrCreate(
                Conversation::participantAttributes($sender, $contact),
            );

            return $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $request->validated('body'),
            ]);
        });

        try {
            MessageSent::dispatch($message);
        } catch (Throwable $exception) {
            // Pesan sudah tersimpan; kegagalan broadcast tidak boleh
            // membuat pengirim mengira pesannya gagal terkirim.
            report($exception);
        }

        return to_route('contacts.show', $contact);
    }
}
