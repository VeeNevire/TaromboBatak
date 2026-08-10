<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * List all events (admin).
     */
    public function index(Request $request): Response
    {
        $events = Event::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%'.$request->string('search').'%');
            })
            ->orderBy('date', 'desc')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'date' => $event->date->format('d M Y'),
                'published' => $event->published,
                'created_at' => $event->created_at?->format('d M Y'),
            ]);

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    /**
     * List all published events (public).
     */
    public function publicIndex(Request $request): Response
    {
        $events = Event::query()
            ->where('published', true)
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%'.$request->string('search').'%');
            })
            ->orderByRaw('CASE WHEN date >= CURDATE() THEN 0 ELSE 1 END')
            ->orderBy('date')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'date' => $event->date->format('d M Y'),
                'month' => $event->date->format('M'),
                'day' => $event->date->format('d'),
                'is_past' => $event->date->isPast(),
            ]);

        return Inertia::render('kegiatan/index', [
            'events' => $events,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    /**
     * Show a single event (public).
     */
    public function show(Event $event): Response
    {
        abort_if(! $event->published, 404);

        return Inertia::render('kegiatan/show', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'registration_url' => $event->registration_url,
                'date' => $event->date->format('d M Y'),
                'month' => $event->date->format('M'),
                'day' => $event->date->format('d'),
                'is_past' => $event->date->isPast(),
            ],
        ]);
    }

    /**
     * Show the create event form.
     */
    public function create(): Response
    {
        return Inertia::render('events/form', [
            'event' => null,
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        Event::create([
            ...$request->validated(),
            'published' => $request->boolean('published'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event berhasil ditambahkan.')]);

        return to_route('events.index');
    }

    /**
     * Show the edit event form.
     */
    public function edit(Event $event): Response
    {
        return Inertia::render('events/form', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'registration_url' => $event->registration_url,
                'date' => $event->date->format('Y-m-d'),
                'published' => $event->published,
            ],
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->update([
            ...$request->validated(),
            'published' => $request->boolean('published'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event berhasil diperbarui.')]);

        return to_route('events.index');
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event berhasil dihapus.')]);

        return to_route('events.index');
    }
}
