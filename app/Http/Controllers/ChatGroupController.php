<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatGroupRequest;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ContactRequest;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatGroupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('viewAny', ChatGroup::class), 403);

        return Inertia::render('groups/index', [
            'groups' => $this->groupsFor($request->user()),
            'contacts' => $this->contactsFor($request->user()),
        ]);
    }

    public function store(StoreChatGroupRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $group = DB::transaction(function () use ($request, $user): ChatGroup {
            $group = ChatGroup::query()->create([
                'owner_id' => $user->id,
                'marga_id' => $user->marga_id,
                'name' => $request->validated('name'),
            ]);
            $members = collect($request->memberIds())
                ->push($user->id)
                ->unique()
                ->mapWithKeys(fn (int $id): array => [$id => [
                    'role' => $id === $user->id ? ChatGroupMember::ROLE_OWNER : ChatGroupMember::ROLE_MEMBER,
                ]]);
            $group->members()->sync($members->all());

            return $group;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grup berhasil dibuat.']);

        return to_route('groups.show', $group);
    }

    public function show(Request $request, ChatGroup $chatGroup): Response
    {
        abort_unless($request->user()?->can('view', $chatGroup), 403);
        $chatGroup->load(['owner', 'memberships.user']);

        return Inertia::render('groups/show', [
            'group' => [
                'id' => $chatGroup->id,
                'name' => $chatGroup->name,
                'owner_id' => $chatGroup->owner_id,
                'owner_name' => $chatGroup->owner->name,
                'telegram_title' => $chatGroup->telegram_title,
                'telegram_linked' => $chatGroup->telegram_chat_id !== null,
                'telegram_account_linked' => $request->user()->telegramAccount?->isMtprotoConnected() ?? false,
                'can_manage' => $request->user()->can('update', $chatGroup),
                'can_announce' => $request->user()->can('announce', $chatGroup),
                'members' => $chatGroup->memberships->map(fn (ChatGroupMember $membership): array => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'role' => $membership->role,
                ])->values(),
            ],
            'messages' => $chatGroup->messages()->with('sender')->latest('id')->limit(100)->get()
                ->reverse()->values()->map(fn (GroupMessage $message): array => $this->messagePayload($message)),
            'availableContacts' => $this->contactsFor($request->user()),
            'telegramLinkCode' => $request->session()->get('telegram_link_code'),
        ]);
    }

    public function destroy(Request $request, ChatGroup $chatGroup): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $chatGroup), 403);
        $chatGroup->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grup dihapus.']);

        return to_route('groups.index');
    }

    /** @return array<int, array<string, bool|int|string|null>> */
    private function groupsFor(User $user): array
    {
        return ChatGroup::query()
            ->whereHas('members', fn ($query) => $query->whereKey($user->id))
            ->with(['latestMessage', 'owner'])
            ->withCount('members')
            ->latest('updated_at')
            ->get()
            ->map(fn (ChatGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'owner_name' => $group->owner->name,
                'members_count' => $group->members_count,
                'telegram_linked' => $group->telegram_chat_id !== null,
                'latest_message' => $group->latestMessage?->body,
                'latest_message_at' => $group->latestMessage?->created_at?->toISOString(),
            ])->all();
    }

    /** @return array<int, array{id: int, name: string, telegram_linked: bool}> */
    private function contactsFor(User $user): array
    {
        return User::query()
            ->with('telegramAccount')
            ->where(function ($query) use ($user): void {
                $query->when($user->marga_id !== null, fn ($query) => $query->where('marga_id', $user->marga_id))
                    ->when($user->marga_id === null, fn ($query) => $query->whereRaw('1 = 0'))
                    ->orWhereIn('id', ContactRequest::query()
                        ->where('status', ContactRequest::STATUS_APPROVED)
                        ->where(function ($requests) use ($user): void {
                            $requests->where('requester_id', $user->id)
                                ->orWhere('recipient_id', $user->id);
                        })
                        ->get()
                        ->map(fn (ContactRequest $request): int => $request->requester_id === $user->id
                            ? $request->recipient_id
                            : $request->requester_id));
            })
            ->where('id', '!=', $user->id)
            ->where('role', '!=', 'admin')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'telegram_linked' => $contact->telegramAccount?->isMtprotoConnected() ?? false,
            ])
            ->all();
    }

    /** @return array<string, int|string|null> */
    private function messagePayload(GroupMessage $message): array
    {
        $senderName = $message->sender_id === null
            ? $message->telegram_sender_name
            : $message->sender->name;

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $senderName,
            'body' => $message->body,
            'source' => $message->source,
            'delivery_status' => $message->telegram_delivery_status,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
