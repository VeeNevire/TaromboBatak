<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSharedFamilyTreePersonRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeAppendRequest;
use App\Models\FamilyTreeNode;
use App\Notifications\FamilyTreeAppendSubmitted;
use App\Services\FamilyTreeActivityLogger;
use App\Services\SharedFamilyTreeAppendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SharedFamilyTreePersonController extends Controller
{
    public function create(Request $request, FamilyTree $familyTree): Response
    {
        Gate::authorize('append', $familyTree);

        $nodes = $familyTree->nodes()
            ->with('person:id,name,gender,marga_id')
            ->orderBy('chain')
            ->orderBy('id')
            ->get();
        $parentNodeIds = $nodes->pluck('father_node_id')->filter()->flip();

        return Inertia::render('people/shared-tree-person-form', [
            'familyTree' => [
                'id' => $familyTree->id,
                'name' => $familyTree->name ?? $familyTree->rootPerson()->value('name') ?? 'Silsilah',
                'requires_approval' => ! $request->user()->can('manage', $familyTree),
            ],
            'fatherOptions' => $nodes
                ->filter(fn (FamilyTreeNode $node) => $node->person->gender !== 'P'
                    && ! $parentNodeIds->has($node->id))
                ->map(fn (FamilyTreeNode $node) => [
                    'id' => $node->id,
                    'name' => $node->person->name,
                    'chain' => $node->chain,
                ])->values()->all(),
            'motherOptions' => $nodes
                ->filter(fn (FamilyTreeNode $node) => $node->person->gender === 'P')
                ->map(fn (FamilyTreeNode $node) => [
                    'id' => $node->id,
                    'name' => $node->person->name,
                    'chain' => $node->chain,
                ])->values()->all(),
        ]);
    }

    public function store(
        StoreSharedFamilyTreePersonRequest $request,
        FamilyTree $familyTree,
        SharedFamilyTreeAppendService $appendService,
    ): RedirectResponse {
        $validated = $request->validated();
        $fatherNode = $familyTree->nodes()->with('person')->find($validated['father_node_id']);
        $motherNode = isset($validated['mother_node_id'])
            ? $familyTree->nodes()->with('person')->find($validated['mother_node_id'])
            : null;

        if ($fatherNode === null || (isset($validated['mother_node_id']) && $motherNode === null)) {
            throw ValidationException::withMessages([
                'father_node_id' => 'Orang tua harus berasal dari silsilah yang dibagikan ini.',
            ]);
        }

        if (! $request->user()->can('manage', $familyTree)) {
            $appendRequest = DB::transaction(function () use ($validated, $request, $familyTree): FamilyTreeAppendRequest {
                $tree = FamilyTree::query()->lockForUpdate()->findOrFail($familyTree->id);
                $tree->ensureStructureIsEditable();

                return FamilyTreeAppendRequest::create([
                    'family_tree_id' => $tree->id,
                    'requester_id' => $request->user()->id,
                    'payload' => $validated,
                ]);
            });
            $appendRequest->load(['requester', 'familyTree']);
            $appendRequest->familyTree->user->notify(new FamilyTreeAppendSubmitted($appendRequest));

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Pengajuan tambah anggota telah dikirim ke pemilik silsilah untuk disetujui.',
            ]);

            return to_route('family-trees.show', $familyTree);
        }

        $person = DB::transaction(function () use ($validated, $request, $familyTree, $appendService) {
            $familyTree = FamilyTree::query()->lockForUpdate()->findOrFail($familyTree->id);
            $familyTree->ensureStructureIsEditable();
            return $appendService->append(
                tree: $familyTree,
                payload: $validated,
                createdBy: $request->user()->id,
            );
        });

        app(TreeActivityLogger::class)->record(
            $person,
            $request->user(),
            'added',
            "{$person->name} ditambahkan ke silsilah yang dibagikan.",
            [],
            $familyTree,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$person->name} berhasil ditambahkan tanpa mengubah anggota lama."]);
        app(FamilyTreeActivityLogger::class)->log($familyTree, $request->user(), 'added', "Menambahkan anggota {$person->name}.");

        return to_route('family-trees.show', $familyTree);
    }
}
