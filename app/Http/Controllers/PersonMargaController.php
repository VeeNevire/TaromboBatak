<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePersonMargaRequest;
use App\Models\Marga;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PersonMargaController extends Controller
{
    public function index(Person $person): JsonResponse
    {
        Gate::authorize('update', $person);

        return response()->json(['margas' => Marga::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(ChangePersonMargaRequest $request, Person $person): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $person, $data): void {
            $ids = [$person->id];
            $frontier = $ids;
            if ($data['include_descendants']) {
                while ($frontier !== []) {
                    $frontier = Person::query()->whereIn('father_id', $frontier)
                        ->whereNotIn('id', $ids)->pluck('id')->all();
                    $ids = array_merge($ids, $frontier);
                }
            }

            $people = Person::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            foreach ($people as $member) {
                if ($request->user()->cannot('update', $member)) {
                    throw ValidationException::withMessages([
                        'include_descendants' => 'Anda tidak memiliki izin mengubah '.$member->name.'. Pilih hanya anggota ini atau minta akses untuk seluruh keturunannya.',
                    ]);
                }
            }

            foreach ($people as $member) {
                $member->update(['marga_id' => $data['marga_id']]);
            }
        });

        return back()->with('success', 'Marga berhasil diganti.');
    }
}
