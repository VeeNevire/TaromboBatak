<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMargaSiblingOrderRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Services\ChainNumberingService;
use App\Services\SiblingOrderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MargaSiblingOrderController extends Controller
{
    public function update(UpdateMargaSiblingOrderRequest $request, Marga $marga): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isStaff() || ($user->isContributor() && $user->accessibleMargaIds()->contains($marga->id)),
            403,
            'Anda tidak memiliki izin untuk mengubah urutan anggota marga ini.',
        );

        $validated = $request->validated();
        $orderedIds = collect($validated['person_ids'])->map(fn ($id): int => (int) $id)->values();

        DB::transaction(function () use ($validated, $marga, $orderedIds): void {
            $siblings = Person::query()
                ->where('father_id', $validated['father_id'])
                ->where('marga_id', $marga->id)
                ->where(fn ($query) => $query->where('gender', 'L')->orWhereNull('gender'))
                ->lockForUpdate()
                ->get();

            if ($siblings->count() !== $orderedIds->count() || $siblings->pluck('id')->sort()->values()->all() !== $orderedIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'person_ids' => 'Daftar saudara berubah. Muat ulang pohon lalu coba lagi.',
                ]);
            }

            $orderedIds->each(fn (int $personId, int $index) => Person::query()
                ->whereKey($personId)
                ->update(['birth_order' => $index + 1]));
        });

        app(ChainNumberingService::class)->recomputeFromAncestor(
            Person::query()->findOrFail($validated['father_id']),
        );
        app(SiblingOrderSyncService::class)->personsToTrees((int) $validated['father_id'], $orderedIds->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Urutan abang–adik berhasil diperbarui.']);

        return back();
    }
}
