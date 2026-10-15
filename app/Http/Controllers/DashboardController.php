<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Marga;
use App\Models\Story;
use App\Services\TaromboStatisticsService;
use App\Services\TaromboTreeService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the landing page inside the application shell.
     */
    public function index(): Response
    {
        $tarombo = app(TaromboTreeService::class);
        $publicTree = $tarombo->publicRows();

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

        $margas = Marga::query()->get(['id', 'name', 'color', 'identity_person_id']);
        $descendantCounts = app(TaromboStatisticsService::class)->descendantCounts(
            $margas->pluck('identity_person_id')->filter()->all(),
        );

        $margas = $margas
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color,
                'count' => $descendantCounts[$marga->identity_person_id] ?? 0,
            ])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->take(5)
            ->values();

        return Inertia::render('dashboard', [
            'stories' => $stories,
            'events' => $events,
            'margas' => $margas,
            'taromboPeople' => $publicTree['rows'],
            'taromboMargas' => $tarombo->margas(publicOnly: true),
        ]);
    }
}
