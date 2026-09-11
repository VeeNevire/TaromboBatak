<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Services\NewsFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StatusController extends Controller
{
    public function __construct(private NewsFeedService $newsFeed) {}

    /**
     * Permalink for a single status. A hidden status answers 404 rather than
     * 403 so posts cannot be enumerated by probing ids.
     */
    public function show(Request $request, FeedPost $feedPost): Response
    {
        abort_if(Gate::denies('view', $feedPost), 404);

        return Inertia::render('news-feed/status', [
            'item' => $this->newsFeed->statusItem($feedPost, $request->user()),
        ]);
    }
}
