<?php

namespace App\Http\Controllers;

use App\Events\GroupMessageSent;
use App\Http\Requests\StoreGroupMessageRequest;
use App\Jobs\SendGroupMessageToTelegram;
use App\Models\ChatGroup;
use App\Models\GroupMessage;
use Illuminate\Http\RedirectResponse;

class GroupMessageController extends Controller
{
    public function store(StoreGroupMessageRequest $request, ChatGroup $chatGroup): RedirectResponse
    {
        $message = $chatGroup->messages()->create([
            'sender_id' => $request->user()->id,
            'source' => GroupMessage::SOURCE_APP,
            'body' => $request->validated('body'),
            'telegram_delivery_status' => $chatGroup->telegram_chat_id !== null
                ? GroupMessage::DELIVERY_PENDING
                : null,
            'sent_at' => now(),
        ]);
        $chatGroup->touch();
        GroupMessageSent::dispatch($message);

        if ($chatGroup->telegram_chat_id !== null) {
            SendGroupMessageToTelegram::dispatch($message->id)->afterCommit();
        }

        return to_route('groups.show', $chatGroup);
    }
}
