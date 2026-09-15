<?php

namespace App\Http\Middleware;

use App\Models\ChatGroupMember;
use App\Models\ContactRequest;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Notifications\EventSubmitted;
use App\Notifications\FamilyTreeDeletionSubmitted;
use App\Notifications\FatherMatchSubmitted;
use App\Notifications\StorySubmitted;
use App\Services\NewsFeedService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'unreadContributionCount' => fn () => $request->user()?->canReviewContributions()
                ? $request->user()->unreadNotifications()
                    ->whereIn('type', [FatherMatchSubmitted::class, FamilyTreeDeletionSubmitted::class])
                    ->count()
                : 0,
            'unreadEventCount' => fn () => $request->user()?->canReviewContributions()
                ? $request->user()->unreadNotifications()->where('type', EventSubmitted::class)->count()
                : 0,
            'unreadStoryCount' => fn () => $request->user()?->canReviewContributions()
                ? $request->user()->unreadNotifications()->where('type', StorySubmitted::class)->count()
                : 0,
            'unreadContactCount' => function () use ($request): int {
                $user = $request->user();

                if ($user === null || $user->isAdmin()) {
                    return 0;
                }

                $unreadMessages = Message::query()
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at')
                    ->whereHas('conversation', fn ($query) => $query
                        ->where('user_one_id', $user->id)
                        ->orWhere('user_two_id', $user->id))
                    ->count();

                $pendingRequests = ContactRequest::query()
                    ->where('recipient_id', $user->id)
                    ->where('status', ContactRequest::STATUS_PENDING)
                    ->count();

                return $unreadMessages + $pendingRequests;
            },
            'unreadGroupMessageCount' => function () use ($request): int {
                $user = $request->user();

                if ($user === null || ! $user->canUseGroups()) {
                    return 0;
                }

                $messages = (new GroupMessage)->getTable();
                $memberships = (new ChatGroupMember)->getTable();

                return GroupMessage::query()
                    ->where(fn ($sender) => $sender
                        ->whereNull('sender_id')
                        ->orWhere('sender_id', '!=', $user->id))
                    ->whereHas('chatGroup.memberships', fn ($membership) => $membership
                        ->where('user_id', $user->id)
                        ->where(fn ($read) => $read
                            ->whereColumn("{$messages}.created_at", '>', "{$memberships}.last_read_at")
                            ->orWhere(fn ($unread) => $unread
                                ->whereNull("{$memberships}.last_read_at")
                                ->whereColumn("{$messages}.created_at", '>', "{$memberships}.created_at"))))
                    ->count();
            },
            'unreadNewsFeedCount' => fn () => $request->user() === null
                ? 0
                : app(NewsFeedService::class)->unreadCount($request->user()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
