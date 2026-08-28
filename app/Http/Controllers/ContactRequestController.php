<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ContactRequestController extends Controller
{
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $user = $request->user();
        $recipientId = $request->integer('recipient_id');
        $existing = ContactRequest::query()
            ->where(function ($query) use ($user, $recipientId) {
                $query->where('requester_id', $user->id)->where('recipient_id', $recipientId)
                    ->orWhere(fn ($query) => $query->where('requester_id', $recipientId)->where('recipient_id', $user->id));
            })->first();

        if ($existing?->status === ContactRequest::STATUS_APPROVED) {
            return back()->with('toast', ['type' => 'info', 'message' => 'Akun tersebut sudah menjadi kontak Anda.']);
        }

        if ($existing?->status === ContactRequest::STATUS_PENDING) {
            return back()->with('toast', ['type' => 'info', 'message' => 'Permintaan kontak masih menunggu persetujuan.']);
        }

        ContactRequest::query()->updateOrCreate(
            ['requester_id' => $user->id, 'recipient_id' => $recipientId],
            ['status' => ContactRequest::STATUS_PENDING, 'reviewed_at' => null],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Permintaan kontak berhasil dikirim.']);
    }

    public function update(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        Gate::authorize('review', $contactRequest);

        $status = $request->string('status')->toString();
        abort_unless(in_array($status, [ContactRequest::STATUS_APPROVED, ContactRequest::STATUS_REJECTED], true), 422);

        $contactRequest->update(['status' => $status, 'reviewed_at' => now()]);

        return back()->with('toast', ['type' => 'success', 'message' => $status === ContactRequest::STATUS_APPROVED ? 'Permintaan kontak disetujui.' : 'Permintaan kontak ditolak.']);
    }
}
