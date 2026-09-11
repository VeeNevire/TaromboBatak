<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedPostRequest;
use App\Http\Requests\UpdateFeedPostRequest;
use App\Models\FeedPost;
use App\Models\FeedPostImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FeedPostController extends Controller
{
    public function store(StoreFeedPostRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $post = $request->user()->feedPosts()->create([
                ...$request->safe()->except(['marga_ids', 'images', 'body']),
                'body' => $request->string('body')->toString(),
            ]);
            $post->audienceMargas()->sync($request->validated('marga_ids', []));

            foreach (array_values($request->file('images', [])) as $position => $image) {
                $post->images()->create([
                    'path' => $image->store('feed', 'public'),
                    'position' => $position,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status berhasil dibagikan.',
        ]);

        return back();
    }

    public function update(UpdateFeedPostRequest $request, FeedPost $feedPost): RedirectResponse
    {
        DB::transaction(function () use ($request, $feedPost) {
            $feedPost->update([
                ...$request->safe()->except('marga_ids'),
                'edited_at' => now(),
            ]);
            $feedPost->audienceMargas()->sync($request->validated('marga_ids', []));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status berhasil diperbarui.',
        ]);

        return back();
    }

    public function destroy(FeedPost $feedPost): RedirectResponse
    {
        Gate::authorize('delete', $feedPost);

        DB::transaction(function () use ($feedPost) {
            $paths = $feedPost->images->map(fn (FeedPostImage $image) => $image->path)->all();

            $feedPost->delete();

            if ($paths !== []) {
                Storage::disk('public')->delete($paths);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Status berhasil dihapus.',
        ]);

        return back();
    }
}
