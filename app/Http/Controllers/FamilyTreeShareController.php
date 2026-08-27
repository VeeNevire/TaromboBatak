<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFamilyTreeShareRequest;
use App\Http\Requests\UpdateFamilyTreeShareRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeShare;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FamilyTreeShareController extends Controller
{
    public function store(StoreFamilyTreeShareRequest $request, FamilyTree $familyTree): RedirectResponse
    {
        $sender = $request->user();
        $recipient = User::query()->findOrFail($request->integer('recipient_id'));

        if ($recipient->id === $sender->id || $recipient->isAdmin()) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Pilih akun penerima lain yang tersedia.',
            ]);
        }

        if (! $sender->isStaff() && ($sender->marga_id === null || $sender->marga_id !== $recipient->marga_id)) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Pohon hanya dapat dibagikan kepada akun dengan marga yang sama.',
            ]);
        }

        FamilyTreeShare::query()->updateOrCreate(
            ['family_tree_id' => $familyTree->id, 'recipient_id' => $recipient->id],
            ['sender_id' => $sender->id, 'status' => FamilyTreeShare::STATUS_PENDING, 'responded_at' => null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Undangan berbagi silsilah berhasil dikirim.']);

        return back();
    }

    public function update(UpdateFamilyTreeShareRequest $request, FamilyTreeShare $familyTreeShare): RedirectResponse
    {
        $status = $request->validated('status');
        $familyTreeShare->update(['status' => $status, 'responded_at' => now()]);

        $message = $status === FamilyTreeShare::STATUS_ACCEPTED
            ? 'Silsilah dibagikan ke akun Anda. Anda dapat menambahkan anggota baru.'
            : 'Undangan berbagi silsilah ditolak.';
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function destroy(FamilyTreeShare $familyTreeShare): RedirectResponse
    {
        $familyTreeShare->loadMissing('familyTree');
        Gate::authorize('share', $familyTreeShare->familyTree);
        $familyTreeShare->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akses berbagi silsilah dicabut.']);

        return back();
    }
}
