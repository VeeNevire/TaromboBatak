<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSharedFamilyTreePersonRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeAppendRequest;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Notifications\FamilyTreeAppendSubmitted;
use App\Services\FamilyTreeActivityLogger;
use App\Services\SharedFamilyTreeAppendService;
use App\Services\TreeActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SharedFamilyTreePersonController extends Controller
{
    public function create(
        Request $request,
        FamilyTree $familyTree,
        SharedFamilyTreeAppendService $appendService,
    ): Response {
        Gate::authorize('append', $familyTree);

        $nodes = $familyTree->nodes()
            ->with([
                'person' => fn ($query) => $query->select('id', 'name', 'gender', 'marga_id'),
                'person.wives:id,name',
            ])
            ->orderBy('chain')
            ->orderBy('id')
            ->get();
        $fatherNodes = $nodes->filter(
            fn (FamilyTreeNode $node) => $node->person->gender !== 'P',
        );
        // Only fathers without descendants in this tree can take a new member.
        $parentNodeIds = $nodes->pluck('father_node_id')->filter()->unique();
        $fatherOptionNodes = $fatherNodes->reject(
            fn (FamilyTreeNode $node) => $parentNodeIds->contains($node->id),
        );
        $fatherPersonId = $request->integer('father_person_id');
        $initialFatherNode = $fatherPersonId > 0
            ? $nodes->first(fn (FamilyTreeNode $node) => $node->person_id === $fatherPersonId
                && $node->person->gender !== 'P')
            : null;
        $initialBranchFather = null;

        if ($fatherPersonId > 0 && $initialFatherNode === null) {
            $branchFather = Person::query()
                ->select('id', 'name', 'gender', 'father_id')
                ->find($fatherPersonId);

            if ($branchFather === null) {
                throw ValidationException::withMessages([
                    'father_person_id' => 'Ayah tidak ditemukan.',
                ]);
            }

            $appendService->validateBranchFather($familyTree, $branchFather);
            $initialBranchFather = [
                'id' => $branchFather->id,
                'name' => $branchFather->name,
            ];
        }
        $motherNodesByPersonId = $nodes
            ->filter(fn (FamilyTreeNode $node) => $node->person->gender === 'P')
            ->keyBy('person_id');

        return Inertia::render('people/shared-tree-person-form', [
            'familyTree' => [
                'id' => $familyTree->id,
                'name' => $familyTree->name ?? $familyTree->rootPerson()->value('name') ?? 'Silsilah',
                'requires_approval' => ! $request->user()->can('manage', $familyTree),
            ],
            'initialFatherNodeId' => $initialFatherNode?->id,
            'initialBranchFather' => $initialBranchFather,
            'fatherOptions' => $fatherOptionNodes
                ->map(fn (FamilyTreeNode $node) => [
                    'id' => $node->id,
                    'name' => $node->person->name,
                    'chain' => $node->chain,
                ])->values()->all(),
            'motherOptionsByFather' => $fatherOptionNodes
                ->mapWithKeys(fn (FamilyTreeNode $fatherNode) => [
                    (string) $fatherNode->id => $fatherNode->person->wives
                        ->map(fn (Person $wife) => $motherNodesByPersonId->get($wife->id))
                        ->filter()
                        ->map(fn (FamilyTreeNode $motherNode) => [
                            'id' => $motherNode->id,
                            'name' => $motherNode->person->name,
                            'chain' => $motherNode->chain,
                        ])->values()->all(),
                ])->all(),
        ]);
    }

    public function store(
        StoreSharedFamilyTreePersonRequest $request,
        FamilyTree $familyTree,
        SharedFamilyTreeAppendService $appendService,
    ): RedirectResponse {
        $validated = $request->validated();
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
        app(FamilyTreeActivityLogger::class)->log(
            $familyTree,
            $request->user(),
            'added',
            "Menambahkan anggota {$person->name}.",
            $person->name,
        );

        return to_route('family-trees.show', $familyTree);
    }
}
