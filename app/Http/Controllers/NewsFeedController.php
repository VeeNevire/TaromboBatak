<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Marga;
use App\Models\Story;
use App\Services\NewsFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NewsFeedController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private NewsFeedService $newsFeed) {}

    public function hide(Request $request, string $feedType, int $feedId): RedirectResponse
    {
        abort_unless($request->user()?->isStaff(), 403);
        match ($feedType) {
            'status' => FeedPost::query()->findOrFail($feedId),
            'story' => Story::query()->publiclyVisible()->findOrFail($feedId),
            'announcement' => Event::query()->publiclyVisible()->findOrFail($feedId),
            default => abort(404),
        };
        DB::table('hidden_feed_items')->insertOrIgnore([
            'feed_type' => $feedType,
            'feed_id' => $feedId,
            'hidden_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'News feed berhasil disembunyikan.']);
    }

    public function index(Request $request): Response|JsonResponse
    {
        $request->validate([
            'cursor' => ['sometimes', 'array'],
            'cursor.*' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var array<string, string|null>|null $cursor */
        $cursor = $request->has('cursor') ? $request->array('cursor') : null;

        $user = $request->user();
        $page = $this->newsFeed->page($user, $cursor, self::PER_PAGE);

        if ($request->wantsJson()) {
            return response()->json($page);
        }

        if ($user !== null) {
            $this->newsFeed->markRead($user);
        }

        return Inertia::render('news-feed/index', [
            'items' => $page['items'],
            'cursor' => $page['cursor'],
            'hasMore' => $page['has_more'],
            'margas' => $user ? Marga::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
