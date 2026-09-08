<?php

namespace App\Http\Controllers;

use App\Models\Marga;
use App\Services\NewsFeedService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsFeedController extends Controller
{
    public function __construct(private NewsFeedService $newsFeed) {}

    public function index(Request $request): Response
    {
        return Inertia::render('news-feed/index', [
            'items' => $this->newsFeed->latestItems($request->user()),
            'margas' => $request->user() ? Marga::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
