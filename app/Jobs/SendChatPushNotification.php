<?php

namespace App\Jobs;

use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\WebPushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Push a new chat message to the recipients' devices, so it arrives like a
 * WhatsApp notification even when Tarombo is not open.
 */
class SendChatPushNotification implements ShouldQueue
{
    use Queueable;

    public const KIND_MESSAGE = 'message';

    public const KIND_GROUP = 'group';

    public int $tries = 3;

    public function __construct(public string $kind, public int $messageId)
    {
        $this->afterCommit();
    }

    public static function forMessage(Message $message): self
    {
        return new self(self::KIND_MESSAGE, $message->id);
    }

    public static function forGroupMessage(GroupMessage $message): self
    {
        return new self(self::KIND_GROUP, $message->id);
    }

    /**
     * Execute the job.
     */
    public function handle(WebPushSender $sender): void
    {
        $notification = $this->kind === self::KIND_GROUP
            ? $this->groupNotification()
            : $this->messageNotification();

        if ($notification === null) {
            return;
        }

        // Every recipient gets their own signed "Tandai dibaca" link, so the
        // button only ever marks the chat read for that reader.
        foreach ($notification['recipients'] as $recipientId) {
            $sender->sendTo(
                PushSubscription::query()->where('user_id', $recipientId),
                [
                    ...$notification['message'],
                    'readUrl' => URL::temporarySignedRoute(
                        $notification['readRoute'],
                        now()->addDays(7),
                        [...$notification['readParameters'], 'reader' => $recipientId],
                    ),
                    'actions' => [
                        ['action' => 'reply', 'title' => 'Balas'],
                        ['action' => 'read', 'title' => 'Tandai dibaca'],
                    ],
                ],
            );
        }
    }

    /**
     * @return array{recipients: array<int, int>, message: array{title: string, body: string, url: string, tag: string}, readRoute: string, readParameters: array<string, int>}|null
     */
    private function messageNotification(): ?array
    {
        $message = Message::query()
            ->with(['conversation', 'sender:id,name'])
            ->withCount('attachments')
            ->find($this->messageId);

        if ($message === null || $message->sender === null) {
            return null;
        }

        return [
            'recipients' => [$message->conversation->otherParticipantId($message->sender_id)],
            'message' => [
                'title' => $message->sender->name,
                'body' => $this->preview($message->body, $message->attachments_count > 0),
                'url' => route('contacts.show', $message->sender_id, absolute: false),
                'tag' => 'chat-'.$message->conversation_id,
            ],
            'readRoute' => 'push.conversations.read',
            'readParameters' => ['conversation' => $message->conversation_id],
        ];
    }

    /**
     * @return array{recipients: array<int, int>, message: array{title: string, body: string, url: string, tag: string}, readRoute: string, readParameters: array<string, int>}|null
     */
    private function groupNotification(): ?array
    {
        $message = GroupMessage::query()
            ->with(['chatGroup:id,name', 'sender:id,name'])
            ->find($this->messageId);

        if ($message === null) {
            return null;
        }

        $senderName = $message->sender?->name ?? $message->telegram_sender_name ?? 'Anggota';

        return [
            'recipients' => $message->chatGroup->memberships()
                ->when($message->sender_id !== null, fn ($query) => $query->where('user_id', '!=', $message->sender_id))
                ->pluck('user_id')
                ->all(),
            'message' => [
                'title' => $message->chatGroup->name,
                'body' => $senderName.': '.$this->preview($message->body, false),
                'url' => route('groups.show', $message->chat_group_id, absolute: false),
                'tag' => 'group-'.$message->chat_group_id,
            ],
            'readRoute' => 'push.groups.read',
            'readParameters' => ['chatGroup' => $message->chat_group_id],
        ];
    }

    private function preview(?string $body, bool $hasAttachments): string
    {
        $text = Str::squish((string) $body);

        if ($text === '') {
            return $hasAttachments ? '📎 Mengirim lampiran' : 'Pesan baru';
        }

        return Str::limit($text, 120);
    }
}
