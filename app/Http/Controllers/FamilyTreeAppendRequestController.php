<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeAppendRequest;
use App\Services\FamilyTreeActivityLogger;
use App\Services\SharedFamilyTreeAppendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FamilyTreeAppendRequestController extends Controller
{
    public function approve(
        Request $request,
        FamilyTreeAppendRequest $appendRequest,
        SharedFamilyTreeAppendService $appendService,
    ): RedirectResponse {
        $tree = $appendRequest->familyTree;
        Gate::authorize('manage', $tree);

        $memberName = DB::transaction(function () use ($request, $appendRequest, $appendService): string {
            $appendRequest = FamilyTreeAppendRequest::query()
                ->lockForUpdate()
                ->findOrFail($appendRequest->id);
            $tree = FamilyTree::query()->lockForUpdate()->findOrFail($appendRequest->family_tree_id);
            Gate::authorize('manage', $tree);
            abort_unless($appendRequest->status === FamilyTreeAppendRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');
            $tree->ensureStructureIsEditable();

            $payload = $appendRequest->payload;
            $person = $appendService->append(
                tree: $tree,
                payload: $payload,
                createdBy: $appendRequest->requester_id,
            );
            $appendRequest->update([
                'status' => FamilyTreeAppendRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $person->name;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "$memberName berhasil ditambahkan ke silsilah."]);
        app(FamilyTreeActivityLogger::class)->log(
            $tree,
            $request->user(),
            'added',
            "Menyetujui penambahan anggota $memberName.",
            $memberName,
        );

        return back();
    }

    public function reject(
        ReviewContributionRequest $request,
        FamilyTreeAppendRequest $appendRequest,
    ): RedirectResponse {
        $tree = $appendRequest->familyTree;
        Gate::authorize('manage', $tree);

        DB::transaction(function () use ($request, $appendRequest): void {
            $appendRequest = FamilyTreeAppendRequest::query()
                ->lockForUpdate()
                ->findOrFail($appendRequest->id);
            $tree = FamilyTree::query()->lockForUpdate()->findOrFail($appendRequest->family_tree_id);
            Gate::authorize('manage', $tree);
            abort_unless($appendRequest->status === FamilyTreeAppendRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            $appendRequest->update([
                'status' => FamilyTreeAppendRequest::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan tambah anggota ditolak.']);
        app(FamilyTreeActivityLogger::class)->log($tree, $request->user(), 'rejected', 'Menolak pengajuan penambahan anggota.');

        return back();
    }
}
