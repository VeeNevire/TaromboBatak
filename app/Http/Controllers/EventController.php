<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewEventRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\Marga;
use App\Models\User;
use App\Notifications\EventSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * List all events (admin).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $events = Event::query()
            ->with(['creator', 'marga', 'reviewer'])
            ->when(! $user->isStaff() && ! $user->isContributor(), fn ($query) => $query->where('created_by', $user->id))
            ->when($user->isContributor(), fn ($query) => $query->where('marga_id', $user->marga_id))
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
                'status' => $event->status,
                'creator' => $event->creator?->name,
                'marga' => $event->marga?->name,
                'reviewer' => $event->reviewer?->name,
                'reviewed_at' => $event->reviewed_at?->format('d M Y H:i'),
                'rejection_reason' => $event->rejection_reason,
                'can_edit' => $user->can('update', $event),
                'can_delete' => $user->can('delete', $event),
                'created_at' => $event->created_at?->format('d M Y'),
            ]);

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => ['search' => $request->string('search')->toString()],
            'canCreate' => $user->isStaff() || $user->marga_id !== null,
        ]);
    }

    /**
     * List all published events (public).
     */
    public function publicIndex(Request $request): Response
    {
        $events = Event::query()
            ->publiclyVisible()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('title', 'like', '%'.$request->string('search').'%');
            })
            ->orderByRaw('CASE WHEN date >= ? THEN 0 ELSE 1 END', [now()->toDateString()])
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
        abort_unless($event->published && $event->status === Event::STATUS_APPROVED, 404);

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
    public function create(Request $request): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('events/form', [
            'event' => null,
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', Event::class);
        $requiresApproval = ! $user->isStaff() && ! $user->isContributor();
        $margaId = $user->isStaff() ? $request->integer('marga_id') : $user->marga_id;
        abort_if($margaId === null, 403, 'Akun Anda belum memiliki marga.');

        $event = Event::create([
            ...$request->validated(),
            'created_by' => $user->id,
            'marga_id' => $margaId,
            'status' => $requiresApproval ? Event::STATUS_PENDING : Event::STATUS_APPROVED,
            'published' => $requiresApproval ? false : $request->boolean('published'),
        ]);

        if ($requiresApproval) {
            $this->notifyContributors($event);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $requiresApproval
                ? 'Event disimpan dan menunggu persetujuan kontributor.'
                : __('Event berhasil ditambahkan.'),
        ]);

        return to_route('events.index');
    }

    /**
     * Show the edit event form.
     */
    public function edit(Request $request, Event $event): Response
    {
        Gate::authorize('update', $event);

        return Inertia::render('events/form', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'registration_url' => $event->registration_url,
                'date' => $event->date->format('Y-m-d'),
                'published' => $event->published,
                'marga_id' => $event->marga_id,
                'status' => $event->status,
                'rejection_reason' => $event->rejection_reason,
            ],
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $event);
        [$event, $requiresApproval, $notifyReviewers] = DB::transaction(function () use ($request, $user, $event) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            Gate::authorize('update', $event);

            $requiresApproval = ! $user->isStaff() && ! $user->isContributor();
            $canApproveDirectly = $user->isAdmin() || $user->isContributor();
            $margaId = $user->isStaff() ? $request->integer('marga_id') : $user->marga_id;
            abort_if($margaId === null, 403, 'Akun Anda belum memiliki marga.');
            $nextStatus = $requiresApproval
                ? Event::STATUS_PENDING
                : ($canApproveDirectly ? Event::STATUS_APPROVED : $event->status);
            $notifyReviewers = $requiresApproval
                || ($event->status === Event::STATUS_PENDING && $event->marga_id !== $margaId);
            $updates = [
                ...$request->validated(),
                'marga_id' => $margaId,
                'status' => $nextStatus,
                'published' => $nextStatus === Event::STATUS_APPROVED && $request->boolean('published'),
                'review_version' => $event->review_version + 1,
            ];

            if ($requiresApproval || $canApproveDirectly) {
                $updates = [
                    ...$updates,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ];
            }

            $event->update($updates);

            return [$event, $requiresApproval, $notifyReviewers];
        });

        if ($notifyReviewers) {
            $this->clearEventNotifications($event);
            $this->notifyContributors($event);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $requiresApproval
                ? 'Perubahan event disimpan dan menunggu persetujuan ulang.'
                : __('Event berhasil diperbarui.'),
        ]);

        return to_route('events.index');
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);
        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event berhasil dihapus.')]);

        return to_route('events.index');
    }

    public function approve(ReviewEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeReview($request->user(), $event);

        $reviewed = DB::transaction(function () use ($request, $event) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->authorizeReview($request->user(), $event);

            if ($event->status !== Event::STATUS_PENDING || $event->review_version !== $request->integer('version')) {
                return false;
            }

            $event->update([
                'status' => Event::STATUS_APPROVED,
                'published' => true,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
            $this->markEventNotificationsRead($event);

            return true;
        });

        if (! $reviewed) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Event telah berubah atau sudah ditinjau. Muat ulang daftar.']);

            return to_route('contributions.index', ['tab' => 'events']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Event berhasil disetujui.']);

        return to_route('contributions.index', ['tab' => 'events']);
    }

    public function reject(ReviewEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeReview($request->user(), $event);

        $reviewed = DB::transaction(function () use ($request, $event) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->authorizeReview($request->user(), $event);

            if ($event->status !== Event::STATUS_PENDING || $event->review_version !== $request->integer('version')) {
                return false;
            }

            $event->update([
                'status' => Event::STATUS_REJECTED,
                'published' => false,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ]);
            $this->markEventNotificationsRead($event);

            return true;
        });

        if (! $reviewed) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Event telah berubah atau sudah ditinjau. Muat ulang daftar.']);

            return to_route('contributions.index', ['tab' => 'events']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Event ditolak.']);

        return to_route('contributions.index', ['tab' => 'events']);
    }

    /** @return array<string, mixed> */
    protected function formOptions(User $user): array
    {
        return [
            'margas' => $user->isStaff() ? Marga::query()->orderBy('name')->get(['id', 'name']) : [],
            'lockedMarga' => ! $user->isStaff() ? $user->marga?->only(['id', 'name']) : null,
            'canPublish' => $user->isStaff() || $user->isContributor(),
        ];
    }

    protected function notifyContributors(Event $event): void
    {
        $event->loadMissing('creator');

        User::query()
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->where('marga_id', $event->marga_id)
            ->each(fn (User $contributor) => $contributor->notify(new EventSubmitted($event)));
    }

    protected function canReview(User $user, Event $event): bool
    {
        return $user->isAdmin()
            || ($user->isContributor() && $user->marga_id === $event->marga_id);
    }

    protected function authorizeReview(User $user, Event $event): void
    {
        abort_unless($this->canReview($user, $event), 403);
    }

    protected function markEventNotificationsRead(Event $event): void
    {
        DatabaseNotification::query()
            ->where('type', EventSubmitted::class)
            ->where('data->event_id', $event->id)
            ->update(['read_at' => now()]);
    }

    protected function clearEventNotifications(Event $event): void
    {
        DatabaseNotification::query()
            ->where('type', EventSubmitted::class)
            ->where('data->event_id', $event->id)
            ->delete();
    }
}
