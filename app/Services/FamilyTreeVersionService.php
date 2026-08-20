<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyTreeVersionService
{
    /**
     * Copy a tree's people and contextual relationships into an independent
     * alternative version. Person records remain shared between versions.
     */
    public function duplicate(FamilyTree $source, User $owner, string $name): FamilyTree
    {
        return DB::transaction(function () use ($source, $owner, $name): FamilyTree {
            $source->load('nodes');

            $copy = FamilyTree::create([
                'user_id' => $owner->id,
                'root_person_id' => $source->root_person_id,
                'name' => $name,
                'description' => $source->description,
                'source_name' => $source->source_name,
                'source_url' => $source->source_url,
                'based_on_id' => $source->id,
            ]);

            $nodeIds = [];

            foreach ($source->nodes as $node) {
                $newNode = FamilyTreeNode::create([
                    'family_tree_id' => $copy->id,
                    'person_id' => $node->person_id,
                    'birth_order' => $node->birth_order,
                    'sibling_count' => $node->sibling_count,
                    'chain' => $node->chain,
                    'pending_father' => $node->pending_father,
                ]);
                $nodeIds[$node->id] = $newNode->id;
            }

            foreach ($source->nodes as $node) {
                FamilyTreeNode::query()
                    ->whereKey($nodeIds[$node->id])
                    ->update([
                        'father_node_id' => $node->father_node_id === null ? null : $nodeIds[$node->father_node_id],
                        'mother_node_id' => $node->mother_node_id === null ? null : $nodeIds[$node->mother_node_id],
                    ]);
            }

            $copy->people()->sync($source->nodes->pluck('person_id')->all());

            return $copy->fresh('nodes');
        });
    }
}
