<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFamilyTreeSiblingOrderRequest;
use App\Models\FamilyTree;
use App\Services\FamilyTreeStructureService;
use App\Services\SiblingOrderSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FamilyTreeSiblingOrderController extends Controller
{
    /**
     * Reorder brothers under one father inside a single tree version.
     */
    public function update(UpdateFamilyTreeSiblingOrderRequest $request, FamilyTree $familyTree, FamilyTreeStructureService $structure): RedirectResponse
    {
        abort_unless(
            $request->user()->isStaff() || $familyTree->user_id === $request->user()->id,
            403,
            'Anda tidak memiliki izin untuk mengubah urutan anggota silsilah ini.',
        );

        $fatherNodeId = (int) $request->validated('father_node_id');
        $nodeIds = collect($request->validated('node_ids'))->map(fn ($id): int => (int) $id)->values();

        $validChildren = $familyTree->nodes()
            ->whereIn('id', $nodeIds)
            ->where('father_node_id', $fatherNodeId)
            ->count();

        if ($validChildren !== $nodeIds->count()) {
            throw ValidationException::withMessages([
                'node_ids' => 'Daftar saudara berubah. Muat ulang pohon lalu coba lagi.',
            ]);
        }

        $structure->update($familyTree, $nodeIds->map(fn (int $nodeId, int $index): array => [
            'id' => $nodeId,
            'father_node_id' => $fatherNodeId,
            'birth_order' => $index + 1,
        ])->all());
        app(SiblingOrderSyncService::class)->treeToPersons($familyTree, $fatherNodeId, $nodeIds->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Urutan abang–adik berhasil diperbarui.']);

        return back();
    }
}
