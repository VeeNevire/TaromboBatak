<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTelegramAnnouncementRequest;
use App\Jobs\SendTelegramAnnouncementRecipient;
use App\Models\ChatGroup;
use App\Models\TelegramAnnouncement;
use App\Models\TelegramAnnouncementRecipient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($user->isAdmin(), 403);

        $groups = ChatGroup::query()
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id);

                if ($user->isSubAdmin()) {
                    $query->orWhere('marga_id', $user->marga_id);
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn (ChatGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'telegram_linked' => $group->telegram_chat_id !== null,
            ]);
        $mayContactAnnounce = $user->isSubAdmin() || $user->ownedChatGroups()->exists();
        $contacts = $mayContactAnnounce
            ? User::query()
                ->with('telegramAccount')
                ->where('marga_id', $user->marga_id)
                ->where('id', '!=', $user->id)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get()
                ->map(fn (User $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'telegram_linked' => $contact->telegramAccount !== null,
                ])
            : collect();

        return Inertia::render('announcements/index', [
            'contacts' => $contacts,
            'groups' => $groups,
            'mayContactAnnounce' => $mayContactAnnounce,
            'recent' => TelegramAnnouncement::query()
                ->where('sender_id', $user->id)
                ->latest()->limit(20)->get()
                ->map(fn (TelegramAnnouncement $item): array => [
                    'id' => $item->id,
                    'body' => $item->body,
                    'target_type' => $item->target_type,
                    'sent_count' => $item->sent_count,
                    'failed_count' => $item->failed_count,
                    'skipped_count' => $item->skipped_count,
                    'completed' => $item->completed_at !== null,
                    'created_at' => $item->created_at?->toISOString(),
                ]),
        ]);
    }

    public function store(StoreTelegramAnnouncementRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $targetType = $request->validated('target_type');

        if ($targetType === TelegramAnnouncement::TARGET_CONTACTS) {
            abort_unless($user->isSubAdmin() || $user->ownedChatGroups()->exists(), 403);
            $contacts = User::query()
                ->with('telegramAccount')
                ->whereKey($request->contactIds())
                ->where('marga_id', $user->marga_id)
                ->where('role', '!=', 'admin')
                ->get();
            abort_unless($contacts->count() === count($request->contactIds()), 422, 'Kontak harus berasal dari marga yang sama.');

            DB::transaction(function () use ($request, $user, $contacts): void {
                $announcement = TelegramAnnouncement::query()->create([
                    'sender_id' => $user->id,
                    'target_type' => TelegramAnnouncement::TARGET_CONTACTS,
                    'body' => $request->validated('body'),
                ]);

                foreach ($contacts as $contact) {
                    $recipient = $announcement->recipients()->create([
                        'user_id' => $contact->id,
                        'chat_id' => $contact->telegramAccount?->private_chat_id,
                        'recipient_name' => $contact->name,
                        'status' => $contact->telegramAccount
                            ? TelegramAnnouncementRecipient::STATUS_PENDING
                            : TelegramAnnouncementRecipient::STATUS_SKIPPED,
                        'error' => $contact->telegramAccount ? null : 'Telegram belum terhubung.',
                    ]);

                    if ($contact->telegramAccount) {
                        SendTelegramAnnouncementRecipient::dispatch($recipient->id)->afterCommit();
                    }
                }

                $announcement->recipients()->first()?->refreshAnnouncementCounts();
            });
        } else {
            $group = ChatGroup::query()->findOrFail($request->chatGroupId());
            abort_unless($user->can('announce', $group), 403);
            abort_if($group->telegram_chat_id === null, 422, 'Grup belum terhubung ke Telegram.');

            DB::transaction(function () use ($request, $user, $group): void {
                $announcement = TelegramAnnouncement::query()->create([
                    'sender_id' => $user->id,
                    'target_type' => TelegramAnnouncement::TARGET_GROUP,
                    'chat_group_id' => $group->id,
                    'body' => $request->validated('body'),
                ]);
                $recipient = $announcement->recipients()->create([
                    'chat_id' => $group->telegram_chat_id,
                    'recipient_name' => $group->name,
                    'status' => TelegramAnnouncementRecipient::STATUS_PENDING,
                ]);
                SendTelegramAnnouncementRecipient::dispatch($recipient->id)->afterCommit();
            });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengumuman masuk antrean Telegram.']);

        return to_route('announcements.index');
    }
}
