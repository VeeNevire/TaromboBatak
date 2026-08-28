<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewContributionRequest;
use App\Http\Requests\StoreIdentityRequest;
use App\Models\IdentityRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IdentityRequestController extends Controller
{
    public function store(StoreIdentityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $person = Person::query()->findOrFail($request->integer('person_id'));

        abort_unless($user->marga_id !== null && $person->marga_id === $user->marga_id, 403, 'Nama tersebut berada di luar marga Anda.');

        if ($user->current_person_id === $person->id) {
            return back()->with('toast', ['type' => 'info', 'message' => 'Nama tersebut sudah menjadi identitas Anda.']);
        }

        $pending = IdentityRequest::query()
            ->where('requester_id', $user->id)
            ->where('status', IdentityRequest::STATUS_PENDING)
            ->first();

        if ($pending) {
            return back()->with('toast', ['type' => 'info', 'message' => 'Pengajuan identitas Anda masih menunggu persetujuan.']);
        }

        IdentityRequest::query()->create([
            'requester_id' => $user->id,
            'person_id' => $person->id,
            'status' => IdentityRequest::STATUS_PENDING,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan identitas dikirim dan menunggu persetujuan kontributor.']);

        return back();
    }

    public function approve(Request $request, IdentityRequest $identityRequest): RedirectResponse
    {
        $this->authorize('review', $identityRequest);

        DB::transaction(function () use ($request, $identityRequest) {
            $identityRequest = IdentityRequest::query()->lockForUpdate()->findOrFail($identityRequest->id);
            abort_unless($identityRequest->status === IdentityRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            $identityRequest->requester()->update(['current_person_id' => $identityRequest->person_id]);
            $identityRequest->update([
                'status' => IdentityRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            IdentityRequest::query()
                ->where('requester_id', $identityRequest->requester_id)
                ->where('id', '!=', $identityRequest->id)
                ->where('status', IdentityRequest::STATUS_PENDING)
                ->update([
                    'status' => IdentityRequest::STATUS_CANCELLED,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identitas berhasil disetujui.']);

        return back();
    }

    public function reject(ReviewContributionRequest $request, IdentityRequest $identityRequest): RedirectResponse
    {
        $this->authorize('review', $identityRequest);

        $identityRequest->update([
            'status' => IdentityRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->validated('reason'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan identitas ditolak.']);

        return back();
    }

    public function cancel(Request $request, IdentityRequest $identityRequest): RedirectResponse
    {
        $this->authorize('cancel', $identityRequest);

        $identityRequest->update([
            'status' => IdentityRequest::STATUS_CANCELLED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($identityRequest->requester->current_person_id === $identityRequest->person_id) {
            $identityRequest->requester()->update(['current_person_id' => null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persetujuan identitas dibatalkan.']);

        return back();
    }
}
