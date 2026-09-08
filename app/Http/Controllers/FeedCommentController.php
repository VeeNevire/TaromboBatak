<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedCommentRequest;
use App\Models\FeedPost;
use Illuminate\Http\RedirectResponse;

class FeedCommentController extends Controller
{
    public function store(
        StoreFeedCommentRequest $request,
        FeedPost $feedPost,
    ): RedirectResponse {
        abort_unless(FeedPost::query()->whereKey($feedPost->id)->visibleTo($request->user())->exists(), 404);

        $feedPost->comments()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return back();
    }
}
