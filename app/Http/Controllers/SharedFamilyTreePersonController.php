<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSharedFamilyTreePersonRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Services\FamilyTreeChainNumberingService;
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

        return Inertia::render('people/shared-tree-person-form', [
            'familyTree' => [
                'id' => $familyTree->id,
                'name' => $familyTree->name ?? $familyTree->rootPerson()->value('name') ?? 'Silsilah',
            ],
            'fatherOptions' => $nodes
                ->filter(fn (FamilyTreeNode $node) => $node->person->gender !== 'P')
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
        FamilyTreeChainNumberingService $numbering,
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

        $person = DB::transaction(function () use ($validated, $request, $familyTree, $fatherNode, $motherNode, $numbering): Person {
            $birthOrder = $validated['birth_order'] ?? ((int) $familyTree->nodes()
                ->where('father_node_id', $fatherNode->id)
                ->max('birth_order') + 1);

            $person = Person::create([
                'name' => $validated['name'],
                'alias' => $validated['alias'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'marga_id' => $fatherNode->person->marga_id,
                'created_by' => $request->user()->id,
                'father_id' => $fatherNode->person_id,
                'mother_id' => $motherNode?->person_id,
                'birth_order' => $birthOrder,
                'birth_year' => $validated['birth_year'] ?? null,
                'death_year' => $validated['death_year'] ?? null,
                'spouse' => $validated['spouse'] ?? null,
                'spouse_marga' => $validated['spouse_marga'] ?? null,
                'bio' => $validated['bio'] ?? null,
            ]);

            $familyTree->people()->attach($person->id);
            FamilyTreeNode::create([
                'family_tree_id' => $familyTree->id,
                'person_id' => $person->id,
                'father_node_id' => $fatherNode->id,
                'mother_node_id' => $motherNode?->id,
                'birth_order' => $birthOrder,
            ]);
            $numbering->recompute($familyTree);
            $familyTree->touch();

            return $person;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$person->name} berhasil ditambahkan tanpa mengubah anggota lama."]);

        return to_route('family-trees.show', $familyTree);
    }
}
