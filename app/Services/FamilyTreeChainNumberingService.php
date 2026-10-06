<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class FamilyTreeChainNumberingService
{
    /**
     * Rebuild derived chain labels inside one version only.
     */
    public function recompute(FamilyTree $tree): void
    {
        Cache::lock('tarombo-tree-chain-numbering:'.$tree->id, 10)->block(5, function () use ($tree): void {
            $nodes = $tree->nodes()->orderBy('id')->get();
            $children = $nodes->toBase()->groupBy('father_node_id');
            $rootNumber = 1;
            $visited = [];
            $updates = [];

            foreach ($children->get(null, collect())->sortBy('id') as $root) {
                $this->assign($root, (string) $rootNumber, $children, $visited, $updates);
                $rootNumber++;
            }

            if (count($visited) !== $nodes->count()) {
                throw ValidationException::withMessages([
                    'father_node_id' => 'Struktur silsilah mengandung siklus atau ayah di luar silsilah ini.',
                ]);
            }

            // Only changed labels need writing; avoid one UPDATE per member.
            foreach (array_chunk($updates, 200) as $batch) {
                FamilyTreeNode::query()->upsert($batch, ['id'], ['chain', 'updated_at']);
            }
        });
    }

    /**
     * @param  Collection<int|string, Collection<int, FamilyTreeNode>>  $children
     */
    protected function assign(FamilyTreeNode $node, string $chain, Collection $children, array &$visited, array &$updates): void
    {
        if (isset($visited[$node->id])) {
            throw ValidationException::withMessages([
                'father_node_id' => 'Struktur silsilah mengandung siklus.',
            ]);
        }

        $visited[$node->id] = true;
        $nodeChildren = $children->get($node->id, collect())->sortBy([
            ['birth_order', 'asc'],
            ['id', 'asc'],
        ])->values();
        $node->chain = $nodeChildren->isEmpty() && ! str_contains($chain, '-') ? null : $chain;
        if ($node->isDirty('chain')) {
            $node->updated_at = now();
            $updates[] = $node->getAttributes();
        }

        foreach ($nodeChildren as $index => $child) {
            $order = $child->birth_order ?? $index + 1;
            $this->assign($child, $chain.'-'.$order, $children, $visited, $updates);
        }
    }
}
