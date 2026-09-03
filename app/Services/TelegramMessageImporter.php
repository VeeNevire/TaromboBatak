<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Events\TelegramMessageReceived;
use App\Models\Conversation;
use App\Models\Message as WebMessage;
use App\Models\TelegramAccount;
use App\Models\TelegramDialog;
use App\Models\TelegramMessage;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Message\ChannelMessage;
use danog\MadelineProto\EventHandler\Message\PrivateMessage;
use Illuminate\Support\Arr;

class TelegramMessageImporter
{
    public function importEvent(TelegramAccount $account, Message $update): void
    {
        $peer = $update instanceof ChannelMessage
            ? ['channel_id' => $update->chatId]
            : ($update instanceof PrivateMessage ? ['user_id' => $update->chatId] : ['chat_id' => $update->chatId]);

        $this->import($account, ['message' => [
            'id' => $update->id,
            'peer_id' => $peer,
            'from_id' => ['user_id' => $update->senderId],
            'message' => $update->message,
            'date' => $update->date,
            'out' => $update->out,
            'media' => $update->media ? get_class($update->media) : null,
        ]]);
    }

    public function importMessage(TelegramAccount $account, TelegramDialog $dialog, array $message): void
    {
        $message['peer_id'] ??= ['chat_id' => $dialog->telegram_peer_id];
        $this->import($account, ['message' => $message]);
    }

    /** @param array<string, mixed> $update */
    public function import(TelegramAccount $account, array $update): void
    {
        $message = $update['message'] ?? null;
        if (! is_array($message) && is_array($update['messages'] ?? null)) {
            $message = $update['messages']['messages'][0] ?? $update['messages'][0] ?? null;
        }

        if (! is_array($message) || ! isset($message['id'])) {
            return;
        }

        $peer = $message['peer_id'] ?? $message['to_id'] ?? null;
        $peerId = is_array($peer) ? (int) ($peer['channel_id'] ?? $peer['chat_id'] ?? $peer['user_id'] ?? 0) : (int) $peer;

        if ($peerId === 0) {
            return;
        }

        $dialog = TelegramDialog::query()->firstOrCreate(
            ['telegram_account_id' => $account->id, 'telegram_peer_id' => $peerId],
            ['type' => $this->type($peer), 'title' => 'Telegram '.$peerId],
        );
        $sentAt = isset($message['date']) ? now()->setTimestamp((int) $message['date']) : now();
        $isOutgoing = (bool) ($message['out'] ?? false);
        $mediaType = $this->mediaType($message['media'] ?? null);

        $stored = TelegramMessage::query()->firstOrCreate(
            ['telegram_account_id' => $account->id, 'telegram_dialog_id' => $dialog->id, 'telegram_message_id' => (int) $message['id']],
            [
                'telegram_sender_id' => $this->senderId($message['from_id'] ?? null),
                'sender_name' => null,
                'body' => $message['message'] ?? $message['text'] ?? null,
                'media_type' => $mediaType,
                'is_outgoing' => $isOutgoing,
                'sent_at' => $sentAt,
                'metadata' => Arr::except($message, ['message', 'text']),
            ],
        );
        $dialog->update(['last_message_at' => $sentAt]);
        if (! $isOutgoing && $stored->wasRecentlyCreated) {
            $dialog->increment('unread_count');
            TelegramMessageReceived::dispatch($stored->load('account'));
        }
        $this->importToWebConversation($account, $stored, $peerId, $message, $isOutgoing);
        $account->update(['last_seen_at' => now(), 'last_error' => null]);
    }

    /** @param array<string, mixed> $message */
    private function importToWebConversation(TelegramAccount $account, TelegramMessage $telegramMessage, int $peerId, array $message, bool $isOutgoing): void
    {
        if ($telegramMessage->dialog?->type !== 'private' || ! filled($telegramMessage->body)) {
            return;
        }

        $otherTelegram = TelegramAccount::query()
            ->where('telegram_user_id', $isOutgoing
                ? $peerId
                : ($this->senderId($message['from_id'] ?? null) ?: $peerId))
            ->with('user')
            ->first();
        $sender = $isOutgoing ? $account->user : $otherTelegram?->user;
        $recipient = $isOutgoing ? $otherTelegram?->user : $account->user;

        if (! $sender || ! $recipient || ! $recipient->canChatWith($sender)) {
            return;
        }

        $conversation = Conversation::query()->firstOrCreate(
            Conversation::participantAttributes($recipient, $sender),
        );

        // A message sent from the web is inserted before Telegram confirms it.
        // The MTProto update for that same outgoing message can arrive before
        // MessageController has stored telegram_message_id. Reconcile it with
        // the pending web row instead of creating a second visible message.
        $webMessage = WebMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('telegram_message_id', $telegramMessage->telegram_message_id)
            ->first();
        $created = false;

        if ($webMessage === null && $isOutgoing) {
            $sentAt = $telegramMessage->sent_at ?? now();
            $webMessage = WebMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_id', $sender->id)
                ->whereNull('telegram_message_id')
                ->where('body', $telegramMessage->body)
                ->whereBetween('created_at', [
                    $sentAt->copy()->subMinutes(2),
                    $sentAt->copy()->addMinutes(2),
                ])
                ->latest('id')
                ->first();

            if ($webMessage !== null) {
                $webMessage->update([
                    'telegram_message_id' => $telegramMessage->telegram_message_id,
                ]);
            }
        }

        // Telegram sessions may expose different message IDs for the same
        // private message. When the other linked account imports that update,
        // reconcile it by body and a narrow timestamp window as well.
        if ($webMessage === null) {
            $sentAt = $telegramMessage->sent_at ?? now();
            $webMessage = WebMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('body', $telegramMessage->body)
                ->whereNotNull('telegram_message_id')
                ->whereBetween('created_at', [
                    $sentAt->copy()->subSeconds(30),
                    $sentAt->copy()->addSeconds(30),
                ])
                ->latest('id')
                ->first();
        }

        if ($webMessage === null) {
            $webMessage = WebMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $telegramMessage->body,
                'telegram_message_id' => $telegramMessage->telegram_message_id,
            ]);
            $created = true;
        }

        if ($created) {
            MessageSent::dispatch($webMessage->load('conversation', 'attachments'));
        }
    }

    private function senderId(mixed $sender): ?int
    {
        return is_array($sender) ? (int) ($sender['user_id'] ?? $sender['channel_id'] ?? 0) ?: null : (is_numeric($sender) ? (int) $sender : null);
    }

    private function type(mixed $peer): string
    {
        if (! is_array($peer)) {
            return 'unknown';
        }

        return isset($peer['channel_id']) ? 'channel' : (isset($peer['chat_id']) ? 'group' : 'private');
    }

    private function mediaType(mixed $media): ?string
    {
        if (is_string($media)) {
            $media = strtolower($media);
            foreach (['photo', 'document', 'video', 'audio', 'webpage', 'poll'] as $type) {
                if (str_contains($media, $type)) {
                    return $type;
                }
            }

            return null;
        }

        if (! is_array($media)) {
            return null;
        }

        foreach (['photo', 'document', 'video', 'audio', 'webpage', 'poll'] as $type) {
            if (array_key_exists($type, $media)) {
                return $type;
            }
        }

        return null;
    }
}
