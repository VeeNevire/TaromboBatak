<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedPostRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeedPostController extends Controller
{
    public function store(StoreFeedPostRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $post = $request->user()->feedPosts()->create($request->safe()->except('marga_ids'));
            $post->audienceMargas()->sync($request->validated('marga_ids', []));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status berhasil dibagikan.',
        ]);

        return back();
    }
}
