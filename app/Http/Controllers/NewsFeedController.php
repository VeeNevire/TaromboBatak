<?php

namespace App\Http\Controllers;

use App\Models\Marga;
use App\Services\NewsFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsFeedController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private NewsFeedService $newsFeed) {}

    public function index(Request $request): Response|JsonResponse
    {
        $request->validate([
            'cursor' => ['sometimes', 'array'],
            'cursor.*' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var array<string, string|null>|null $cursor */
        $cursor = $request->has('cursor') ? $request->array('cursor') : null;

        $page = $this->newsFeed->page($request->user(), $cursor, self::PER_PAGE);

        if ($request->wantsJson()) {
            return response()->json($page);
        }

        return Inertia::render('news-feed/index', [
            'items' => $page['items'],
            'cursor' => $page['cursor'],
            'hasMore' => $page['has_more'],
            'margas' => $request->user() ? Marga::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
