<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedPostRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeedPostController extends Controller
{
    public function store(StoreFeedPostRequest $request): RedirectResponse
    {
        $request->user()->feedPosts()->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status berhasil dibagikan.',
        ]);

        return back();
    }
}
