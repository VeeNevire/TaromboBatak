<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewContributionRequest;
use App\Http\Requests\StoreContributorRequest;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\FamilyTree;
use App\Models\FamilyTreeDeletionRequest;
use App\Models\IdentityRequest;
use App\Models\Marga;
use App\Models\MargaAccessRequest;
use App\Models\Person;
use App\Models\Story;
use App\Models\User;
use App\Notifications\EventSubmitted;
use App\Notifications\FamilyTreeDeletionSubmitted;
use App\Notifications\FatherMatchSubmitted;
use App\Notifications\MargaAccessRequested;
use App\Notifications\StorySubmitted;
use App\Services\ChainNumberingService;
use App\Services\FamilyEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContributionController extends Controller
{
    public function storeMargaAccessRequest(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(! $user->isStaff() && ! $user->isContributor() && $user->marga_id !== null, 403);

        $existing = $user->margaAccessRequests()
            ->where('marga_id', $user->marga_id)
            ->whereIn('status', [MargaAccessRequest::STATUS_PENDING, MargaAccessRequest::STATUS_APPROVED])
            ->exists();
        abort_if($existing, 409, 'Permintaan akses marga sudah pernah diajukan.');

        $accessRequest = MargaAccessRequest::create([
            'requester_id' => $user->id,
            'marga_id' => $user->marga_id,
        ]);
        $accessRequest->load(['requester', 'marga']);

        User::query()
            ->where(function ($query) use ($accessRequest) {
                $query->where('role', 'admin')
                    ->orWhere(function ($contributors) use ($accessRequest) {
                        $contributors
                            ->whereIn('role', ['contributor_main', 'contributor_member'])
                            ->where(function ($scope) use ($accessRequest) {
                                $scope->where('marga_id', $accessRequest->marga_id)
                                    ->orWhereHas('managedMargas', fn ($margas) => $margas->whereKey($accessRequest->marga_id));
                            });
                    });
            })
            ->whereKeyNot($user->id)
            ->each(fn (User $recipient) => $recipient->notify(new MargaAccessRequested($accessRequest)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan akses marga berhasil dikirim.']);

        return to_route('people.index');
    }

    public function storeMargaTree(Request $request, FamilyTree $familyTree): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            (int) $familyTree->user_id === (int) $user->id
            && $user->accessibleMargaIds()->isNotEmpty(),
            403,
            'Hanya pemilik silsilah dengan marga yang dapat mengajukan silsilah.',
        );

        $familyTree->loadMissing('rootPerson');
        $root = $familyTree->rootPerson;

        abort_if(
            $root === null || ! $user->accessibleMargaIds()->contains($root->marga_id),
            422,
            'Akar silsilah harus berasal dari marga akun Anda.',
        );

        $alreadySubmitted = $familyTree->contributionRequests()
            ->whereIn('status', [ContributionRequest::STATUS_PENDING, ContributionRequest::STATUS_APPROVED])
            ->exists();

        abort_if($alreadySubmitted, 409, 'Silsilah ini sudah diajukan atau telah disetujui.');

        $contribution = ContributionRequest::create([
            'requester_id' => $user->id,
            'matched_father_id' => $root->id,
            'subject_person_id' => $root->id,
            'family_tree_id' => $familyTree->id,
            'affected_person_ids' => [],
        ]);
        $contribution->load(['requester', 'subjectPerson', 'matchedFather']);

        User::query()
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->where(function ($query) use ($root) {
                $query->where('marga_id', $root->marga_id)
                    ->orWhereHas('managedMargas', fn ($margas) => $margas->whereKey($root->marga_id));
            })
            ->each(fn (User $contributor) => $contributor->notify(new FatherMatchSubmitted($contribution)));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan silsilah marga berhasil dikirim ke kontributor.']);

        return to_route('people.create');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canReviewContributions(), 403);

        $requests = ContributionRequest::query()
            ->with(['requester.marga', 'matchedFather.marga', 'subjectPerson', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'matchedFather',
                fn ($father) => $father->whereIn('marga_id', $user->accessibleMargaIds()),
            ))
            ->latest()
            ->paginate(15, ['*'], 'family_page')
            ->withQueryString()
            ->through(fn (ContributionRequest $contribution) => [
                'id' => $contribution->id,
                'requester_id' => $contribution->requester_id,
                'status' => $contribution->status,
                'requester' => $contribution->requester->name,
                'requester_marga' => $contribution->requester->marga?->name,
                'subject' => $contribution->subjectPerson->name,
                'matched_father' => $contribution->matchedFather->name,
                'matched_father_id' => $contribution->matched_father_id,
                'matched_father_marga' => $contribution->matchedFather->marga?->name,
                'reviewer' => $contribution->reviewer?->name,
                'reviewed_at' => $contribution->reviewed_at?->format('d M Y H:i'),
                'reason' => $contribution->rejection_reason,
                'created_at' => $contribution->created_at?->format('d M Y H:i'),
                'family_tree_id' => $contribution->family_tree_id,
                'marga_tree' => $contribution->family_tree_id !== null
                    && empty($contribution->affected_person_ids),
            ]);

        $eventRequests = Event::query()
            ->with(['creator.marga', 'marga', 'reviewer'])
            ->where('status', Event::STATUS_PENDING)
            ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('marga_id', $user->accessibleMargaIds()))
            ->latest()
            ->paginate(15, ['*'], 'event_page')
            ->withQueryString()
            ->through(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'date' => $event->date->format('d M Y'),
                'location' => $event->location,
                'status' => $event->status,
                'creator' => $event->creator?->name,
                'marga' => $event->marga?->name,
                'reviewer' => $event->reviewer?->name,
                'reviewed_at' => $event->reviewed_at?->format('d M Y H:i'),
                'reason' => $event->rejection_reason,
                'review_version' => $event->review_version,
                'created_at' => $event->created_at?->format('d M Y H:i'),
            ]);

        $storyRequests = Story::query()
            ->with(['creator.marga', 'marga', 'reviewer'])
            ->where('status', Story::STATUS_PENDING)
            ->when(! $user->isAdmin(), fn ($query) => $query->where(fn ($query) => $query
                ->where('classification', Story::CLASSIFICATION_GENERAL)
                ->orWhereIn('marga_id', $user->accessibleMargaIds())))
            ->latest()
            ->paginate(15, ['*'], 'story_page')
            ->withQueryString()
            ->through(fn (Story $story) => [
                'id' => $story->id,
                'title' => $story->title,
                'description' => $story->description,
                'image' => $story->image,
                'classification' => $story->classification,
                'marga' => $story->marga?->name,
                'status' => $story->status,
                'creator' => $story->creator?->name,
                'reviewer' => $story->reviewer?->name,
                'reviewed_at' => $story->reviewed_at?->format('d M Y H:i'),
                'reason' => $story->rejection_reason,
                'review_version' => $story->review_version,
                'created_at' => $story->created_at?->format('d M Y H:i'),
            ]);

        $deletionRequests = FamilyTreeDeletionRequest::query()
            ->with(['requester.marga', 'marga', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('marga_id', $user->accessibleMargaIds()))
            ->latest()
            ->paginate(15, ['*'], 'deletion_page')
            ->withQueryString()
            ->through(fn (FamilyTreeDeletionRequest $deletion) => [
                'id' => $deletion->id,
                'tree' => $deletion->tree_name,
                'root' => $deletion->root_name,
                'marga' => $deletion->marga_name ?? $deletion->marga?->name,
                'requester' => $deletion->requester->name,
                'status' => $deletion->status,
                'reviewer' => $deletion->reviewer?->name,
                'reviewed_at' => $deletion->reviewed_at?->format('d M Y H:i'),
                'reason' => $deletion->rejection_reason,
                'created_at' => $deletion->created_at?->format('d M Y H:i'),
            ]);

        $identityRequests = IdentityRequest::query()
            ->with(['requester.marga', 'person.marga', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'person',
                fn ($person) => $person->whereIn('marga_id', $user->accessibleMargaIds()),
            ))
            ->latest()
            ->paginate(15, ['*'], 'identity_page')
            ->withQueryString()
            ->through(fn (IdentityRequest $identity) => [
                'id' => $identity->id,
                'status' => $identity->status,
                'requester' => $identity->requester->name,
                'requester_marga' => $identity->requester->marga?->name,
                'person' => $identity->person->name,
                'person_marga' => $identity->person->marga?->name,
                'reviewer' => $identity->reviewer?->name,
                'reviewed_at' => $identity->reviewed_at?->format('d M Y H:i'),
                'reason' => $identity->rejection_reason,
                'created_at' => $identity->created_at?->format('d M Y H:i'),
            ]);

        $margaAccessRequests = MargaAccessRequest::query()
            ->with(['requester.marga', 'marga', 'reviewer'])
            ->when(! $user->isAdmin(), fn ($query) => $query->whereIn('marga_id', $user->accessibleMargaIds()))
            ->latest()
            ->get()
            ->map(fn (MargaAccessRequest $accessRequest) => [
                'id' => $accessRequest->id,
                'requester' => $accessRequest->requester->name,
                'requester_marga' => $accessRequest->requester->marga?->name,
                'marga' => $accessRequest->marga->name,
                'status' => $accessRequest->status,
                'reviewer' => $accessRequest->reviewer?->name,
                'reviewed_at' => $accessRequest->reviewed_at?->format('d M Y H:i'),
                'reason' => $accessRequest->rejection_reason,
                'created_at' => $accessRequest->created_at?->format('d M Y H:i'),
            ])
            ->values()
            ->all();

        $managedMargaIds = ! $user->isAdmin()
            ? $user->managedMargas()->pluck('margas.id')
            : collect();

        $managedMargas = ! $user->isAdmin()
            ? Marga::query()
                ->with('identityPerson:id,name')
                ->withCount('people')
                ->whereKey($managedMargaIds)
                ->orderBy('name')
                ->get()
                ->map(fn (Marga $marga) => [
                    'id' => $marga->id,
                    'name' => $marga->name,
                    'people_count' => $marga->people_count,
                    'identity_person_id' => $marga->identity_person_id,
                    'identity_person_name' => $marga->identityPerson?->name,
                ])
                ->values()
                ->all()
            : [];

        $contributors = $user->isAdmin()
            ? User::query()
                ->whereIn('role', ['contributor_main', 'contributor_member'])
                ->with('marga')
                ->orderBy('name')
                ->get()
                ->map(fn (User $contributor) => [
                    'id' => $contributor->id,
                    'name' => $contributor->name,
                    'email' => $contributor->email,
                    'role' => $contributor->role,
                    'marga' => $contributor->marga?->name,
                ])
                ->all()
            : [];

        $requestedTab = $request->string('tab')->toString();
        $activeTab = in_array($requestedTab, ['events', 'stories', 'deletions', 'identity'], true) ? $requestedTab : 'requests';
        $notificationType = match ($activeTab) {
            'events' => EventSubmitted::class,
            'stories' => StorySubmitted::class,
            'deletions' => FamilyTreeDeletionSubmitted::class,
            default => FatherMatchSubmitted::class,
        };
        $user->unreadNotifications()
            ->where('type', $notificationType)
            ->update(['read_at' => now()]);

        return Inertia::render('contributions/index', [
            'requests' => $requests,
            'eventRequests' => $eventRequests,
            'storyRequests' => $storyRequests,
            'deletionRequests' => $deletionRequests,
            'identityRequests' => $identityRequests,
            'margaAccessRequests' => $margaAccessRequests,
            'managedMargas' => $managedMargas,
            'contributors' => $contributors,
            'margas' => $user->isAdmin()
                ? Marga::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'canManageContributors' => $user->isAdmin(),
            'activeTab' => $activeTab,
        ]);
    }

    public function storeContributor(StoreContributorRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun kontributor berhasil ditambahkan.']);

        return to_route('contributions.index');
    }

    public function destroyContributor(User $contributor): RedirectResponse
    {
        abort_unless($contributor->isContributor(), 404);
        $contributor->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun kontributor berhasil dihapus.']);

        return to_route('contributions.index');
    }

    public function approve(Request $request, ContributionRequest $contribution): RedirectResponse
    {
        $this->authorizeReview($request->user(), $contribution);

        DB::transaction(function () use ($request, $contribution) {
            $contribution = ContributionRequest::query()->lockForUpdate()->findOrFail($contribution->id);
            abort_unless($contribution->status === ContributionRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            $affectedIds = collect($contribution->affected_person_ids)->map(fn ($id) => (int) $id)->all();
            $affectedPeople = Person::query()->whereIn('id', $affectedIds)->get();

            abort_if(
                $affectedPeople->contains(fn (Person $person) => in_array(
                    $contribution->matched_father_id,
                    $person->ineligibleFatherIds(),
                    true,
                )),
                409,
                'Struktur silsilah berubah dan relasi ini akan membentuk siklus.',
            );

            Person::query()->whereIn('id', $affectedIds)->update([
                'father_id' => $contribution->matched_father_id,
                'pending_father' => false,
            ]);

            $contribution->update([
                'status' => ContributionRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            if ($contribution->familyTree !== null) {
                $ancestorIds = [];
                $current = $contribution->matchedFather;
                $seen = [];

                while ($current !== null && ! isset($seen[$current->id])) {
                    $seen[$current->id] = true;
                    $ancestorIds[] = $current->id;
                    $current = $current->father;
                }

                $contribution->familyTree->people()->syncWithoutDetaching($ancestorIds);
                app(FamilyEntryService::class)->syncTreeNodes($contribution->familyTree);
            }

            app(ChainNumberingService::class)->recomputeFromAncestor($contribution->matchedFather);
            $this->markRequestNotificationsRead($contribution);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan kontribusi disetujui.']);

        return to_route('contributions.index');
    }

    public function reject(ReviewContributionRequest $request, ContributionRequest $contribution): RedirectResponse
    {
        $this->authorizeReview($request->user(), $contribution);

        DB::transaction(function () use ($request, $contribution) {
            $contribution = ContributionRequest::query()->lockForUpdate()->findOrFail($contribution->id);
            abort_unless($contribution->status === ContributionRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            $contribution->update([
                'status' => ContributionRequest::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ]);
            $this->markRequestNotificationsRead($contribution);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan kontribusi ditolak.']);

        return to_route('contributions.index');
    }

    public function approveMargaAccess(Request $request, MargaAccessRequest $margaAccessRequest): RedirectResponse
    {
        $this->authorizeMargaAccessReview($request->user(), $margaAccessRequest);

        $margaAccessRequest->update([
            'status' => MargaAccessRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
        $this->markMargaAccessNotificationsRead($margaAccessRequest);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akses marga disetujui.']);

        return to_route('contributions.index');
    }

    public function rejectMargaAccess(ReviewContributionRequest $request, MargaAccessRequest $margaAccessRequest): RedirectResponse
    {
        $this->authorizeMargaAccessReview($request->user(), $margaAccessRequest);

        $margaAccessRequest->update([
            'status' => MargaAccessRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('reason'),
        ]);
        $this->markMargaAccessNotificationsRead($margaAccessRequest);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan akses marga ditolak.']);

        return to_route('contributions.index');
    }

    protected function authorizeReview(User $user, ContributionRequest $contribution): void
    {
        abort_unless(
            $user->isAdmin()
            || ($user->isContributor() && $user->accessibleMargaIds()->contains($contribution->matchedFather->marga_id)),
            403,
        );
    }

    protected function authorizeMargaAccessReview(User $user, MargaAccessRequest $accessRequest): void
    {
        abort_unless(
            $user->isAdmin()
            || ($user->isContributor() && $user->accessibleMargaIds()->contains($accessRequest->marga_id)),
            403,
        );
        abort_unless($accessRequest->status === MargaAccessRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');
    }

    protected function markRequestNotificationsRead(ContributionRequest $contribution): void
    {
        DatabaseNotification::query()
            ->where('data->contribution_request_id', $contribution->id)
            ->update(['read_at' => now()]);
    }

    protected function markMargaAccessNotificationsRead(MargaAccessRequest $accessRequest): void
    {
        DatabaseNotification::query()
            ->where('data->marga_access_request_id', $accessRequest->id)
            ->update(['read_at' => now()]);
    }
}
