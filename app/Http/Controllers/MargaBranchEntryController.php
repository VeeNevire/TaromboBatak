<?php

namespace App\Http\Controllers;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MargaBranchEntryController extends Controller
{
    public function store(Request $request, Person $person): RedirectResponse
    {
        abort_if($person->gender === 'P', 422, 'Ranting tidak dapat ditambahkan melalui anggota perempuan.');

        $familyTree = DB::transaction(function () use ($request, $person): FamilyTree {
            $father = Person::query()->lockForUpdate()->findOrFail($person->id);
            $trees = FamilyTree::query()
                ->where(fn ($query) => $query
                    ->where('root_person_id', $father->id)
                    ->orWhereHas('people', fn ($people) => $people->whereKey($father))
                    ->orWhereHas('nodes', fn ($nodes) => $nodes->where('person_id', $father->id)))
                ->orderByDesc('is_primary')
                ->orderByDesc('updated_at')
                ->get();

            $existingTree = $trees
                ->sortByDesc(fn (FamilyTree $tree) => $tree->root_person_id === $father->id)
                ->first(fn (FamilyTree $tree) => $request->user()->can('append', $tree));

            if ($existingTree !== null) {
                return $existingTree;
            }

            $familyTree = FamilyTree::create([
                'user_id' => $request->user()->id,
                'root_person_id' => $father->id,
                'name' => 'Keluarga '.$father->name,
            ]);
            $familyTree->people()->attach($father->id);
            FamilyTreeNode::create([
                'family_tree_id' => $familyTree->id,
                'person_id' => $father->id,
            ]);

            return $familyTree;
        });

        Gate::authorize('append', $familyTree);

        return to_route('family-trees.people.create', [
            'familyTree' => $familyTree,
            'father_person_id' => $person->id,
        ]);
    }
}
