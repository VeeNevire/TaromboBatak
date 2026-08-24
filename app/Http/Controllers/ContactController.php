<?php

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function show(Request $request, User $contact): Response
    {
        abort_unless($request->user()?->canChatWith($contact), 403);

        return $this->render($request, $contact);
    }

    /**
     * Incremental JSON feed for the silent fallback poller. Deliberately
     * outside Inertia so polling never touches the page router (and can
     * never disturb typing, focus, or scroll).
     *
     * Cursors: after_id (messages newer than cursor), before_id + limit
     * (older history page). Without cursors the latest limit is returned.
     */
    public function messages(Request $request, User $contact): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canChatWith($contact), 403);

        $conversation = Conversation::between($user, $contact)->first();

        if ($conversation === null) {
            return response()->json(['messages' => [], 'has_more' => false]);
        }

        $this->markConversationRead($conversation, $user);

        $afterId = (int) $request->query('after_id', 0);
        $beforeId = (int) $request->query('before_id', 0);
        $limit = max(1, min(200, (int) $request->query('limit', 50)));

        $query = $conversation->messages();

        if ($afterId > 0) {
            $messages = $query->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get();
            $hasMore = false;
        } elseif ($beforeId > 0) {
            $messages = $query->where('id', '<', $beforeId)
                ->latest('id')
                ->limit($limit + 1)
                ->get()
                ->reverse()
                ->values();
            $hasMore = $messages->count() > $limit;

            if ($hasMore) {
                $messages = $messages->slice(1)->values();
            }
        } else {
            $messages = $query->latest('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
            $hasMore = $messages->count() >= $limit;
        }

        return response()->json([
            'messages' => $messages
                ->map(fn (Message $message) => $this->messagePayload($message, $user)),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Mark the counterpart's messages as read and notify their author in
     * real time so the sender sees the read receipt immediately.
     *
     * @return array<int, int> Affected message ids.
     */
    private function markConversationRead(Conversation $conversation, User $reader): array
    {
        $affected = $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->pluck('id');

        if ($affected->isEmpty()) {
            return [];
        }

        $now = now();
        Message::query()->whereIn('id', $affected)->update(['read_at' => $now]);

        MessageRead::dispatch(
            $conversation->otherParticipantId($reader->id),
            $conversation->id,
            $reader->id,
            $affected->all(),
            $now->toISOString(),
        );

        return $affected->all();
    }

    private function render(Request $request, ?User $selectedContact = null): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($user->isAdmin(), 403);

        $conversation = $selectedContact
            ? Conversation::between($user, $selectedContact)->first()
            : null;

        $selectedContact?->loadMissing('marga');

        if ($conversation) {
            $this->markConversationRead($conversation, $user);
        }

        $conversations = $this->conversationsFor($user);
        $contacts = $user->marga_id === null
            ? collect()
            : User::query()
                ->with('marga')
                ->where('marga_id', $user->marga_id)
                ->where('id', '!=', $user->id)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get()
                ->map(fn (User $contact): array => $this->contactPayload(
                    $contact,
                    $conversations->first(fn (Conversation $item): bool => $item->includes($contact)),
                ));

        $messages = $conversation
            ? $conversation->messages()->latest()->limit(100)->get()->reverse()->values()
                ->map(fn (Message $message): array => $this->messagePayload($message, $user))
            : collect();

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'selectedContact' => $selectedContact
                ? $this->contactPayload($selectedContact, $conversation)
                : null,
            'messages' => $messages,
        ]);
    }

    /**
     * @return Collection<int, Conversation>
     */
    private function conversationsFor(User $user): Collection
    {
        return Conversation::query()
            ->where(fn (Builder $query) => $query
                ->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id))
            ->with('latestMessage')
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            ->get();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function contactPayload(User $contact, ?Conversation $conversation): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'color' => $contact->marga?->color,
            'role_label' => $contact->isSubAdmin() ? 'Pengurus Marga' : 'Anggota Marga',
            'latest_message' => $conversation?->latestMessage?->body,
            'latest_message_at' => $conversation?->latestMessage?->created_at?->toISOString(),
            'unread_count' => (int) ($conversation?->getAttribute('unread_count') ?? 0),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function messagePayload(Message $message, User $user): array
    {
        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'read_at' => $message->read_at?->toISOString(),
            'is_mine' => $message->sender_id === $user->id,
        ];
    }
}
