<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\Story;
use Illuminate\Support\Collection;

class NewsFeedService
{
    /** @return Collection<int, array<string, mixed>> */
    public function latestItems(): Collection
    {
        $statuses = FeedPost::query()
            ->select(['id', 'user_id', 'body', 'created_at'])
            ->with([
                'author:id,name',
                'comments' => fn ($query) => $query
                    ->select(['id', 'feed_post_id', 'user_id', 'body', 'created_at'])
                    ->with('author:id,name')
                    ->oldest(),
            ])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (FeedPost $post) => [
                'key' => 'status-'.$post->id,
                'type' => 'status',
                'id' => $post->id,
                'author' => $post->author->name,
                'title' => null,
                'body' => $post->body,
                'image' => null,
                'url' => null,
                'meta' => null,
                'created_at' => $post->created_at?->toIso8601String(),
                'comments' => $post->comments->map(fn (FeedComment $comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author->name,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])->values(),
            ]);

        $stories = Story::query()
            ->select(['id', 'created_by', 'marga_id', 'title', 'description', 'image', 'created_at'])
            ->with(['creator:id,name', 'marga:id,name'])
            ->publiclyVisible()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Story $story) => [
                'key' => 'story-'.$story->id,
                'type' => 'story',
                'id' => $story->id,
                'author' => $story->creator?->name ?? 'Tim Tarombo Batak',
                'title' => $story->title,
                'body' => $story->description,
                'image' => $story->image,
                'url' => route('cerita.show', $story),
                'meta' => $story->marga?->name,
                'created_at' => $story->created_at?->toIso8601String(),
                'comments' => [],
            ]);

        $announcements = Event::query()
            ->select(['id', 'created_by', 'title', 'description', 'location', 'date', 'created_at'])
            ->with('creator:id,name')
            ->publiclyVisible()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Event $event) => [
                'key' => 'announcement-'.$event->id,
                'type' => 'announcement',
                'id' => $event->id,
                'author' => $event->creator?->name ?? 'Tim Tarombo Batak',
                'title' => $event->title,
                'body' => $event->description,
                'image' => null,
                'url' => route('kegiatan.show', $event),
                'meta' => collect([
                    $event->date->translatedFormat('d F Y'),
                    $event->location,
                ])->filter()->join(' • '),
                'created_at' => $event->created_at?->toIso8601String(),
                'comments' => [],
            ]);

        return $statuses
            ->concat($stories)
            ->concat($announcements)
            ->sortByDesc('created_at')
            ->take(50)
            ->values();
    }
}
