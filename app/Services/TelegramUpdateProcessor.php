<?php

namespace App\Services;

use App\Contracts\TelegramBot;
use App\Events\GroupMessageSent;
use App\Models\ChatGroup;
use App\Models\GroupMessage;
use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use Illuminate\Support\Facades\DB;

class TelegramUpdateProcessor
{
    public function __construct(private TelegramBot $telegram) {}

    /** @param array<string, mixed> $payload */
    public function process(array $payload): void
    {
        $message = $payload['message'] ?? null;

        if (! is_array($message) || ! is_array($message['chat'] ?? null)) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $chat = $message['chat'];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        if ($text !== '' && preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9]+)$/', $text, $matches)) {
            $this->linkAccount($matches[1], $chat, $from);

            return;
        }

        if ($text !== '' && preg_match('/^\/link(?:@\w+)?\s+([A-Za-z0-9]+)$/i', $text, $matches)) {
            $this->linkGroup(strtoupper($matches[1]), $chat, $from);

            return;
        }

        if ($text === '' || ($from['is_bot'] ?? false) || ! in_array($chat['type'] ?? null, ['group', 'supergroup'], true)) {
            return;
        }

        $chatGroup = ChatGroup::query()->where('telegram_chat_id', $chat['id'])->first();

        if ($chatGroup === null) {
            return;
        }

        $account = TelegramAccount::query()
            ->where('telegram_user_id', $from['id'] ?? 0)
            ->first();
        $senderId = $account && $chatGroup->hasMember($account->user)
            ? $account->user_id
            : null;
        $displayName = trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ]))) ?: ($from['username'] ?? 'Pengguna Telegram');

        $groupMessage = GroupMessage::query()->firstOrCreate([
            'chat_group_id' => $chatGroup->id,
            'telegram_message_id' => $message['message_id'],
        ], [
            'sender_id' => $senderId,
            'source' => GroupMessage::SOURCE_TELEGRAM,
            'body' => $text,
            'telegram_sender_id' => $from['id'] ?? null,
            'telegram_sender_name' => $displayName,
            'telegram_delivery_status' => GroupMessage::DELIVERY_SENT,
            'sent_at' => isset($message['date']) ? now()->setTimestamp((int) $message['date']) : now(),
        ]);

        if ($groupMessage->wasRecentlyCreated) {
            $chatGroup->touch();
            GroupMessageSent::dispatch($groupMessage);
        }
    }

    /**
     * @param  array<string, mixed>  $chat
     * @param  array<string, mixed>  $from
     */
    private function linkAccount(string $plainToken, array $chat, array $from): void
    {
        if (($chat['type'] ?? null) !== 'private' || ! isset($from['id'], $chat['id'])) {
            return;
        }

        $message = DB::transaction(function () use ($plainToken, $chat, $from): string {
            $token = TelegramLinkToken::query()
                ->where('purpose', TelegramLinkToken::PURPOSE_ACCOUNT)
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if ($token === null || ! $token->isUsable()) {
                return 'Tautan tidak valid atau sudah kedaluwarsa. Buat tautan baru dari Pengaturan Profil.';
            }

            $claimed = TelegramAccount::query()
                ->where('telegram_user_id', $from['id'])
                ->where('user_id', '!=', $token->user_id)
                ->exists();

            if ($claimed) {
                return 'Akun Telegram ini sudah terhubung ke akun aplikasi lain.';
            }

            TelegramAccount::query()->updateOrCreate(['user_id' => $token->user_id], [
                'telegram_user_id' => $from['id'],
                'private_chat_id' => $chat['id'],
                'username' => $from['username'] ?? null,
                'display_name' => trim(implode(' ', array_filter([$from['first_name'] ?? null, $from['last_name'] ?? null]))) ?: 'Pengguna Telegram',
                'linked_at' => now(),
            ]);
            $token->update(['used_at' => now()]);

            return 'Telegram berhasil terhubung dengan akun Tarombo Batak.';
        });

        $this->telegram->sendMessage($chat['id'], $message);
    }

    /**
     * @param  array<string, mixed>  $chat
     * @param  array<string, mixed>  $from
     */
    private function linkGroup(string $plainToken, array $chat, array $from): void
    {
        if (! isset($from['id'], $chat['id'])) {
            return;
        }

        if (! in_array($chat['type'] ?? null, ['group', 'supergroup'], true)) {
            $this->telegram->sendMessage(
                $chat['id'],
                'Perintah /link harus dikirim di dalam grup Telegram yang ingin dihubungkan, bukan melalui chat pribadi bot.',
            );

            return;
        }

        $account = TelegramAccount::query()->where('telegram_user_id', $from['id'])->first();
        $token = TelegramLinkToken::query()
            ->with('chatGroup')
            ->where('purpose', TelegramLinkToken::PURPOSE_GROUP)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($account === null || $token === null || ! $token->isUsable() || $account->user_id !== $token->user_id || $token->chatGroup?->owner_id !== $account->user_id) {
            $this->telegram->sendMessage($chat['id'], 'Kode tidak valid, kedaluwarsa, atau bukan milik Anda.');

            return;
        }

        $member = $this->telegram->getChatMember($chat['id'], $from['id']);

        if (! in_array($member['status'] ?? null, ['administrator', 'creator'], true)) {
            $this->telegram->sendMessage($chat['id'], 'Hanya admin grup Telegram yang dapat memasangkan grup.');

            return;
        }

        $alreadyLinked = ChatGroup::query()
            ->where('telegram_chat_id', $chat['id'])
            ->whereKeyNot($token->chat_group_id)
            ->exists();

        if ($alreadyLinked) {
            $this->telegram->sendMessage($chat['id'], 'Grup Telegram ini sudah terhubung ke grup aplikasi lain.');

            return;
        }

        DB::transaction(function () use ($token, $chat): void {
            $locked = TelegramLinkToken::query()->lockForUpdate()->findOrFail($token->id);

            if (! $locked->isUsable()) {
                return;
            }

            $locked->chatGroup()->update([
                'telegram_chat_id' => $chat['id'],
                'telegram_title' => $chat['title'] ?? null,
                'telegram_linked_at' => now(),
            ]);
            $locked->update(['used_at' => now()]);
        });

        $this->telegram->sendMessage($chat['id'], 'Grup berhasil terhubung dengan Tarombo Batak.');
    }
}
