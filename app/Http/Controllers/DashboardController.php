<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Marga;
use App\Models\Story;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the landing page inside the application shell.
     */
    public function index(): Response
    {
        $stories = Story::query()
            ->publiclyVisible()
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
            ->publiclyVisible()
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
            ->withCount(['people' => fn ($query) => $query->public()])
            ->orderByDesc('people_count')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color,
                'count' => $marga->people_count,
            ]);

        return Inertia::render('dashboard', [
            'stories' => $stories,
            'events' => $events,
            'margas' => $margas,
        ]);
    }
}
