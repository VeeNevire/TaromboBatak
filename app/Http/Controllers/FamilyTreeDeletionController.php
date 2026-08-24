<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeDeletionRequest;
use App\Models\User;
use App\Notifications\FamilyTreeDeletionSubmitted;
use App\Services\FamilyTreeDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FamilyTreeDeletionController extends Controller
{
    public function destroy(Request $request, FamilyTree $familyTree): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $familyTree->user_id === $user->id, 403);

        $result = DB::transaction(function () use ($familyTree, $user) {
            $tree = FamilyTree::query()->with('rootPerson.marga')->lockForUpdate()->findOrFail($familyTree->id);
            abort_unless($user->isAdmin() || $tree->user_id === $user->id, 403);

            $existing = FamilyTreeDeletionRequest::query()
                ->where('family_tree_id', $tree->id)
                ->where('status', FamilyTreeDeletionRequest::STATUS_PENDING)
                ->first();

            if ($existing !== null) {
                return 'pending';
            }

            if (! app(FamilyTreeDeletionService::class)->isConnectedToOtherAccount($tree)) {
                $tree->delete();

                return 'deleted';
            }

            $deletion = FamilyTreeDeletionRequest::create([
                'family_tree_id' => $tree->id,
                'requester_id' => $user->id,
                'marga_id' => $tree->rootPerson?->marga_id,
                'tree_name' => $tree->name ?? 'Silsilah '.$tree->rootPerson?->name,
                'root_name' => $tree->rootPerson->name ?? 'Tanpa akar',
                'marga_name' => $tree->rootPerson?->marga?->name,
            ]);
            $deletion->load('requester');

            User::query()
                ->whereIn('role', ['contributor_main', 'contributor_member'])
                ->where('marga_id', $deletion->marga_id)
                ->each(fn (User $contributor) => $contributor->notify(new FamilyTreeDeletionSubmitted($deletion)));

            return 'created';
        });

        Inertia::flash('toast', [
            'type' => $result === 'pending' ? 'info' : 'success',
            'message' => match ($result) {
                'deleted' => 'Silsilah berhasil dihapus. Data anggota tetap tersimpan.',
                'created' => 'Penghapusan silsilah diajukan dan menunggu persetujuan Kontributor.',
                default => 'Penghapusan silsilah ini masih menunggu persetujuan.',
            },
        ]);

        return back();
    }

    public function approve(Request $request, FamilyTreeDeletionRequest $deletion): RedirectResponse
    {
        $this->authorizeReview($request->user(), $deletion);

        $reviewed = DB::transaction(function () use ($request, $deletion) {
            $tree = FamilyTree::query()->with('rootPerson')->lockForUpdate()->find($deletion->family_tree_id);
            $deletion = FamilyTreeDeletionRequest::query()->lockForUpdate()->findOrFail($deletion->id);
            $this->authorizeReview($request->user(), $deletion);
            abort_unless($deletion->status === FamilyTreeDeletionRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            if ($tree === null) {
                $deletion->update([
                    'status' => FamilyTreeDeletionRequest::STATUS_REJECTED,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => 'Silsilah sudah tidak tersedia.',
                ]);
                $this->markNotificationsRead($deletion);

                return false;
            }

            $deletion->update([
                'status' => FamilyTreeDeletionRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
            $tree->delete();
            $this->markNotificationsRead($deletion);

            return true;
        });

        Inertia::flash('toast', [
            'type' => $reviewed ? 'success' : 'error',
            'message' => $reviewed ? 'Penghapusan silsilah disetujui.' : 'Silsilah sudah tidak tersedia.',
        ]);

        return to_route('contributions.index', ['tab' => 'deletions']);
    }

    public function reject(ReviewContributionRequest $request, FamilyTreeDeletionRequest $deletion): RedirectResponse
    {
        $this->authorizeReview($request->user(), $deletion);

        DB::transaction(function () use ($request, $deletion) {
            $deletion = FamilyTreeDeletionRequest::query()->lockForUpdate()->findOrFail($deletion->id);
            $this->authorizeReview($request->user(), $deletion);
            abort_unless($deletion->status === FamilyTreeDeletionRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');
            $deletion->update([
                'status' => FamilyTreeDeletionRequest::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ]);
            $this->markNotificationsRead($deletion);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penghapusan silsilah ditolak.']);

        return to_route('contributions.index', ['tab' => 'deletions']);
    }

    protected function authorizeReview(User $user, FamilyTreeDeletionRequest $deletion): void
    {
        abort_unless(
            $user->isAdmin() || ($user->isContributor() && $user->marga_id === $deletion->marga_id),
            403,
        );
    }

    protected function markNotificationsRead(FamilyTreeDeletionRequest $deletion): void
    {
        DatabaseNotification::query()
            ->where('type', FamilyTreeDeletionSubmitted::class)
            ->where('data->family_tree_deletion_request_id', $deletion->id)
            ->update(['read_at' => now()]);
    }
}
