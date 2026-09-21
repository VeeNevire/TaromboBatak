<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreMargaMessageRequest;
use App\Models\Conversation;
use App\Models\Marga;
use App\Models\MargaChatConversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MargaChatController extends Controller
{
    /**
     * A marga's chat is a private thread between a sender and the marga's
     * contributors: the sender's message reaches every contributor, while a
     * contributor's reply only reaches the sender. Each thread is tagged with
     * its marga so both sides can find it.
     */
    public function show(Request $request, Marga $marga): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $threads = MargaChatConversation::query()
            ->where('marga_id', $marga->id)
            ->whereHas('conversation', fn ($query) => $query
                ->where('user_one_id', $viewer->id)
                ->orWhere('user_two_id', $viewer->id))
            ->with('conversation')
            ->get();

        $conversationIds = $threads->pluck('conversation_id');

        if ($conversationIds->isNotEmpty()) {
            Message::query()
                ->whereIn('conversation_id', $conversationIds)
                ->where('sender_id', '!=', $viewer->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $messages = $conversationIds->isEmpty()
            ? collect()
            : Message::query()
                ->whereIn('conversation_id', $conversationIds)
                ->with('sender:id,name')
                ->latest('id')
                ->limit(200)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (Message $message) => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender?->name ?? 'Pengguna terhapus',
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toISOString(),
                ]);

        return Inertia::render('marga/chat', [
            'marga' => [
                'id' => $marga->id,
                'name' => $marga->name,
                'color' => $marga->color,
            ],
            'members' => $this->membersFor($viewer, $marga, $threads),
            'messages' => $messages,
            'is_contributor' => $viewer->isContributorOf($marga->id),
        ]);
    }

    public function store(StoreMargaMessageRequest $request, Marga $marga): RedirectResponse
    {
        /** @var User $sender */
        $sender = $request->user();
        $isContributor = $sender->isContributorOf($marga->id);

        $recipients = $isContributor
            ? $this->replyRecipients($request, $marga, $sender)
            : $this->contributorsOf($marga)->reject(fn (User $user) => $user->is($sender))->values();

        if ($recipients->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => $isContributor
                    ? __('Belum ada pesan dari anggota untuk dibalas.')
                    : __('Belum ada kontributor marga yang dapat menerima pesan.'),
            ]);

            return back();
        }

        $body = $request->validated('body');

        $recipients->each(function (User $recipient) use ($marga, $sender, $isContributor, $body): void {
            $conversation = Conversation::query()->firstOrCreate(
                Conversation::participantAttributes($sender, $recipient),
            );

            MargaChatConversation::query()->firstOrCreate(
                ['marga_id' => $marga->id, 'conversation_id' => $conversation->id],
                ['sender_id' => $isContributor ? $recipient->id : $sender->id],
            );

            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            MessageSent::dispatch($message);
        });

        return back();
    }

    /**
     * A contributor replies to the sender(s) who wrote to this marga. When a
     * recipient is chosen, only that sender is answered.
     *
     * @return Collection<int, User>
     */
    private function replyRecipients(StoreMargaMessageRequest $request, Marga $marga, User $contributor): Collection
    {
        $senderIds = MargaChatConversation::query()
            ->where('marga_id', $marga->id)
            ->whereHas('conversation', fn ($query) => $query
                ->where('user_one_id', $contributor->id)
                ->orWhere('user_two_id', $contributor->id))
            ->with('conversation')
            ->get()
            ->map(fn (MargaChatConversation $thread) => $thread->conversation->otherParticipantId($contributor->id))
            ->unique()
            ->values();

        if ($request->filled('recipient_id')) {
            $recipientId = $request->integer('recipient_id');

            abort_unless($senderIds->contains($recipientId), 403);

            $senderIds = collect([$recipientId]);
        }

        return User::query()
            ->whereIn('id', $senderIds)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'marga_id']);
    }

    /**
     * Contributors for a sender's view, or the senders who wrote to this marga
     * for a contributor's view.
     *
     * @param  Collection<int, MargaChatConversation>  $threads
     * @return Collection<int, array{id: int, name: string}>
     */
    private function membersFor(User $viewer, Marga $marga, Collection $threads): Collection
    {
        if (! $viewer->isContributorOf($marga->id)) {
            return $this->contributorsOf($marga)
                ->reject(fn (User $user) => $user->is($viewer))
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values();
        }

        $senderIds = $threads
            ->map(fn (MargaChatConversation $thread) => $thread->conversation->otherParticipantId($viewer->id))
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $senderIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
    }

    /**
     * Contributors of a marga, taken from the marga management assigned to
     * user accounts (plus the account's own marga).
     *
     * @return Collection<int, User>
     */
    private function contributorsOf(Marga $marga): Collection
    {
        return User::query()
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->where(fn ($query) => $query
                ->where('marga_id', $marga->id)
                ->orWhereHas('managedMargas', fn ($margas) => $margas->whereKey($marga->id)))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'marga_id']);
    }
}
