<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedItemCommentRequest;
use App\Models\Event;
use App\Models\Story;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedItemEngagementController extends Controller
{
    public function like(Request $request, string $feedType, int $feedId): RedirectResponse
    {
        $this->feedItem($feedType, $feedId)
            ->feedLikes()
            ->firstOrCreate(['user_id' => $request->user()->id]);

        return back();
    }

    public function unlike(Request $request, string $feedType, int $feedId): RedirectResponse
    {
        $this->feedItem($feedType, $feedId)
            ->feedLikes()
            ->where('user_id', $request->user()->id)
            ->delete();

        return back();
    }

    public function comment(StoreFeedItemCommentRequest $request, string $feedType, int $feedId): RedirectResponse
    {
        $this->feedItem($feedType, $feedId)
            ->feedComments()
            ->create([...$request->validated(), 'user_id' => $request->user()->id]);

        return back();
    }

    /** @return Story|Event */
    private function feedItem(string $feedType, int $feedId): Model
    {
        return match ($feedType) {
            'story' => Story::query()->publiclyVisible()->findOrFail($feedId),
            'announcement' => Event::query()->publiclyVisible()->findOrFail($feedId),
            default => abort(404),
        };
    }
}
