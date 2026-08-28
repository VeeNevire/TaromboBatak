<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewStoryRequest;
use App\Http\Requests\StoreStoryRequest;
use App\Http\Requests\UpdateStoryRequest;
use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use App\Notifications\StorySubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoryController extends Controller
{
    /**
     * List all stories (admin).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $stories = Story::query()
            ->with(['creator', 'marga', 'reviewer'])
            ->when(! $user->isStaff() && ! $user->isContributor(), fn ($query) => $query->where('created_by', $user->id))
            ->when($user->isContributor(), fn ($query) => $query->where(fn ($query) => $query
                ->where('classification', Story::CLASSIFICATION_GENERAL)
                ->orWhere('marga_id', $user->marga_id)
                ->orWhere('created_by', $user->id)))
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
                'content_url' => $story->content_url,
                'published' => $story->published,
                'classification' => $story->classification,
                'marga' => $story->marga?->name,
                'creator' => $story->creator?->name,
                'status' => $story->status,
                'reviewer' => $story->reviewer?->name,
                'reviewed_at' => $story->reviewed_at?->format('d M Y H:i'),
                'rejection_reason' => $story->rejection_reason,
                'can_edit' => $user->can('update', $story),
                'can_delete' => $user->can('delete', $story),
                'created_at' => $story->created_at?->format('d M Y'),
            ]);

        return Inertia::render('stories/index', [
            'stories' => $stories,
            'filters' => ['search' => $request->string('search')->toString()],
            'canCreate' => true,
        ]);
    }

    /**
     * List all published stories (public).
     */
    public function publicIndex(Request $request): Response
    {
        $stories = Story::query()
            ->with('marga')
            ->publiclyVisible()
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
                'content_url' => $story->content_url,
                'classification' => $story->classification,
                'marga' => $story->marga?->name,
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
        abort_unless($story->published && $story->status === Story::STATUS_APPROVED, 404);
        $story->loadMissing('marga');

        return Inertia::render('cerita/show', [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'content_url' => $story->content_url,
                'classification' => $story->classification,
                'marga' => $story->marga?->name,
                'created_at' => $story->created_at?->format('d M Y'),
            ],
        ]);
    }

    /**
     * Show the create story form.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Story::class);

        return Inertia::render('stories/form', [
            'story' => null,
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Store a newly created story.
     */
    public function store(StoreStoryRequest $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', Story::class);
        $requiresApproval = ! $user->isStaff() && ! $user->isContributor();
        $classification = $request->string('classification')->toString();
        $margaId = $classification === Story::CLASSIFICATION_MARGA
            ? ($user->isStaff() ? $request->integer('marga_id') : $user->marga_id)
            : null;
        abort_if($classification === Story::CLASSIFICATION_MARGA && ! $margaId, 403, 'Marga cerita belum ditentukan.');

        DB::transaction(function () use ($request, $user, $margaId, $requiresApproval) {
            $story = Story::create([
                ...$request->validated(),
                'created_by' => $user->id,
                'marga_id' => $margaId,
                'status' => $requiresApproval ? Story::STATUS_PENDING : Story::STATUS_APPROVED,
                'published' => $requiresApproval ? false : $request->boolean('published'),
            ]);

            if ($requiresApproval) {
                $this->notifyContributors($story);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $requiresApproval
                ? 'Cerita disimpan dan menunggu persetujuan kontributor.'
                : __('Cerita berhasil ditambahkan.'),
        ]);

        return to_route('stories.index');
    }

    /**
     * Show the edit story form.
     */
    public function edit(Request $request, Story $story): Response
    {
        Gate::authorize('update', $story);

        return Inertia::render('stories/form', [
            'story' => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'content_url' => $story->content_url,
                'published' => $story->published,
                'classification' => $story->classification,
                'marga_id' => $story->marga_id,
                'status' => $story->status,
                'rejection_reason' => $story->rejection_reason,
            ],
            ...$this->formOptions($request->user()),
        ]);
    }

    /**
     * Update the specified story.
     */
    public function update(UpdateStoryRequest $request, Story $story): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $story);

        $requiresApproval = DB::transaction(function () use ($request, $user, $story) {
            $story = Story::query()->lockForUpdate()->findOrFail($story->id);
            Gate::authorize('update', $story);

            $requiresApproval = ! $user->isStaff() && ! $user->isContributor();
            $canApproveDirectly = $user->isAdmin() || $user->isContributor();
            $classification = $request->string('classification')->toString();
            $margaId = $classification === Story::CLASSIFICATION_MARGA
                ? ($user->isStaff() ? $request->integer('marga_id') : $user->marga_id)
                : null;
            abort_if($classification === Story::CLASSIFICATION_MARGA && ! $margaId, 403, 'Marga cerita belum ditentukan.');
            $nextStatus = $requiresApproval
                ? Story::STATUS_PENDING
                : ($canApproveDirectly ? Story::STATUS_APPROVED : $story->status);
            $scopeChanged = $story->classification !== $classification || $story->marga_id !== $margaId;
            $notifyReviewers = $requiresApproval || ($story->status === Story::STATUS_PENDING && $scopeChanged);
            $updates = [
                ...$request->validated(),
                'classification' => $classification,
                'marga_id' => $margaId,
                'status' => $nextStatus,
                'published' => $nextStatus === Story::STATUS_APPROVED && $request->boolean('published'),
                'review_version' => $story->review_version + 1,
            ];

            if ($requiresApproval || $canApproveDirectly) {
                $updates = [
                    ...$updates,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                ];
            }

            $story->update($updates);

            if ($notifyReviewers) {
                $this->clearStoryNotifications($story);
                $this->notifyContributors($story);
            }

            return $requiresApproval;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $requiresApproval
                ? 'Perubahan cerita disimpan dan menunggu persetujuan ulang.'
                : __('Cerita berhasil diperbarui.'),
        ]);

        return to_route('stories.index');
    }

    /**
     * Remove the specified story.
     */
    public function destroy(Story $story): RedirectResponse
    {
        Gate::authorize('delete', $story);
        DB::transaction(function () use ($story) {
            $story = Story::query()->lockForUpdate()->findOrFail($story->id);
            Gate::authorize('delete', $story);
            $this->clearStoryNotifications($story);
            $story->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cerita berhasil dihapus.')]);

        return to_route('stories.index');
    }

    public function approve(ReviewStoryRequest $request, Story $story): RedirectResponse
    {
        $this->authorizeReview($request->user(), $story);

        $reviewed = DB::transaction(function () use ($request, $story) {
            $story = Story::query()->lockForUpdate()->findOrFail($story->id);
            $this->authorizeReview($request->user(), $story);

            if ($story->status !== Story::STATUS_PENDING || $story->review_version !== $request->integer('version')) {
                return false;
            }

            $story->update([
                'status' => Story::STATUS_APPROVED,
                'published' => true,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
            $this->markStoryNotificationsRead($story);

            return true;
        });

        Inertia::flash('toast', [
            'type' => $reviewed ? 'success' : 'error',
            'message' => $reviewed ? 'Cerita berhasil disetujui.' : 'Cerita telah berubah atau sudah ditinjau. Muat ulang daftar.',
        ]);

        return to_route('contributions.index', ['tab' => 'stories']);
    }

    public function reject(ReviewStoryRequest $request, Story $story): RedirectResponse
    {
        $this->authorizeReview($request->user(), $story);

        $reviewed = DB::transaction(function () use ($request, $story) {
            $story = Story::query()->lockForUpdate()->findOrFail($story->id);
            $this->authorizeReview($request->user(), $story);

            if ($story->status !== Story::STATUS_PENDING || $story->review_version !== $request->integer('version')) {
                return false;
            }

            $story->update([
                'status' => Story::STATUS_REJECTED,
                'published' => false,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ]);
            $this->markStoryNotificationsRead($story);

            return true;
        });

        Inertia::flash('toast', [
            'type' => $reviewed ? 'success' : 'error',
            'message' => $reviewed ? 'Cerita ditolak.' : 'Cerita telah berubah atau sudah ditinjau. Muat ulang daftar.',
        ]);

        return to_route('contributions.index', ['tab' => 'stories']);
    }

    /** @return array<string, mixed> */
    protected function formOptions(User $user): array
    {
        return [
            'margas' => $user->isStaff() ? Marga::query()->orderBy('name')->get(['id', 'name']) : [],
            'lockedMarga' => ! $user->isStaff() ? $user->marga?->only(['id', 'name']) : null,
            'canPublish' => $user->isStaff() || $user->isContributor(),
            'canChooseMarga' => $user->isStaff(),
            'canCreateMarga' => $user->isStaff() || $user->marga_id !== null,
        ];
    }

    protected function notifyContributors(Story $story): void
    {
        $story->loadMissing('creator');

        User::query()
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->when(
                $story->classification === Story::CLASSIFICATION_MARGA,
                fn ($query) => $query->where('marga_id', $story->marga_id),
            )
            ->each(fn (User $contributor) => $contributor->notify(new StorySubmitted($story)));
    }

    protected function canReview(User $user, Story $story): bool
    {
        return $user->isAdmin()
            || ($user->isContributor() && (
                $story->classification === Story::CLASSIFICATION_GENERAL
                || $user->marga_id === $story->marga_id
            ));
    }

    protected function authorizeReview(User $user, Story $story): void
    {
        abort_unless($this->canReview($user, $story), 403);
    }

    protected function markStoryNotificationsRead(Story $story): void
    {
        DatabaseNotification::query()
            ->where('type', StorySubmitted::class)
            ->where('data->story_id', $story->id)
            ->update(['read_at' => now()]);
    }

    protected function clearStoryNotifications(Story $story): void
    {
        DatabaseNotification::query()
            ->where('type', StorySubmitted::class)
            ->where('data->story_id', $story->id)
            ->delete();
    }
}
