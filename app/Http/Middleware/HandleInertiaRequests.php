<?php

namespace App\Http\Middleware;

use App\Notifications\EventSubmitted;
use App\Notifications\FamilyTreeDeletionSubmitted;
use App\Notifications\FatherMatchSubmitted;
use App\Notifications\StorySubmitted;
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
