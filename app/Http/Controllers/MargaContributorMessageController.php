<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreMargaContributorMessageRequest;
use App\Models\Conversation;
use App\Models\Marga;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class MargaContributorMessageController extends Controller
{
    public function store(
        StoreMargaContributorMessageRequest $request,
        Marga $marga,
        User $contributor,
    ): RedirectResponse {
        /** @var User $sender */
        $sender = $request->user();

        abort_if($sender->is($contributor), 422, 'Anda tidak dapat mengirim pesan kepada diri sendiri.');

        $conversation = Conversation::query()->firstOrCreate(
            Conversation::participantAttributes($sender, $contributor),
        );
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $request->validated('body'),
        ]);

        MessageSent::dispatch($message);

        return back();
    }
}
