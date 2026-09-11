<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeedPostLikeController extends Controller
{
    public function store(Request $request, FeedPost $feedPost): RedirectResponse
    {
        Gate::authorize('view', $feedPost);

        $feedPost->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return back();
    }

    public function destroy(Request $request, FeedPost $feedPost): RedirectResponse
    {
        Gate::authorize('view', $feedPost);

        $feedPost->likes()->where('user_id', $request->user()->id)->delete();

        return back();
    }
}
