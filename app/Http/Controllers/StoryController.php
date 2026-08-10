<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoryRequest;
use App\Http\Requests\UpdateStoryRequest;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoryController extends Controller
{
    /**
     * List all stories (admin).
     */
    public function index(Request $request): Response
    {
        $stories = Story::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%'.$request->string('search').'%');
            })
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Story $story) => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'published' => $story->published,
                'created_at' => $story->created_at?->format('d M Y'),
            ]);

        return Inertia::render('stories/index', [
            'stories' => $stories,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    /**
     * List all published stories (public).
     */
    public function publicIndex(Request $request): Response
    {
        $stories = Story::query()
            ->where('published', true)
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%'.$request->string('search').'%');
            })
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Story $story) => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'created_at' => $story->created_at?->format('d M Y'),
            ]);

        return Inertia::render('cerita/index', [
            'stories' => $stories,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    /**
     * Show a single story (public).
     */
    public function show(Story $story): Response
    {
        abort_if(! $story->published, 404);

        return Inertia::render('cerita/show', [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'created_at' => $story->created_at?->format('d M Y'),
            ],
        ]);
    }

    /**
     * Show the create story form.
     */
    public function create(): Response
    {
        return Inertia::render('stories/form', [
            'story' => null,
        ]);
    }

    /**
     * Store a newly created story.
     */
    public function store(StoreStoryRequest $request): RedirectResponse
    {
        Story::create([
            ...$request->validated(),
            'published' => $request->boolean('published'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cerita berhasil ditambahkan.')]);

        return to_route('stories.index');
    }

    /**
     * Show the edit story form.
     */
    public function edit(Story $story): Response
    {
        return Inertia::render('stories/form', [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'published' => $story->published,
            ],
        ]);
    }

    /**
     * Update the specified story.
     */
    public function update(UpdateStoryRequest $request, Story $story): RedirectResponse
    {
        $story->update([
            ...$request->validated(),
            'published' => $request->boolean('published'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cerita berhasil diperbarui.')]);

        return to_route('stories.index');
    }

    /**
     * Remove the specified story.
     */
    public function destroy(Story $story): RedirectResponse
    {
        $story->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cerita berhasil dihapus.')]);

        return to_route('stories.index');
    }
}
