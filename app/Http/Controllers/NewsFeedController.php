<?php

namespace App\Http\Controllers;

use App\Services\NewsFeedService;
use Inertia\Inertia;
use Inertia\Response;

class NewsFeedController extends Controller
{
    public function __construct(private NewsFeedService $newsFeed) {}

    public function index(): Response
    {
        return Inertia::render('news-feed/index', [
            'items' => $this->newsFeed->latestItems(),
        ]);
    }
}
