<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Marga;
use App\Models\Story;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Show the landing page with stories & events from the database.
     */
    public function index(): Response
    {
        $stories = Story::query()
            ->where('published', true)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Story $story) => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
            ]);

        $events = Event::query()
            ->where('published', true)
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(3)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'month' => $event->date->format('M'),
                'day' => $event->date->format('d'),
                'is_past' => $event->date->isPast(),
            ]);

        $margas = Marga::query()
            ->withCount('people')
            ->orderByDesc('people_count')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color,
                'count' => $marga->people_count,
            ]);

        return Inertia::render('home', [
            'stories' => $stories,
            'events' => $events,
            'margas' => $margas,
        ]);
    }
}
