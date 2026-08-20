<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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

            foreach ($children->get(null, collect())->sortBy('id') as $root) {
                $this->assign($root, (string) $rootNumber, $children);
                $rootNumber++;
            }
        });
    }

    /**
     * @param  Collection<int|string, Collection<int, FamilyTreeNode>>  $children
     */
    protected function assign(FamilyTreeNode $node, string $chain, Collection $children): void
    {
        $nodeChildren = $children->get($node->id, collect())->sortBy([
            ['birth_order', 'asc'],
            ['id', 'asc'],
        ])->values();
        $node->update(['chain' => $nodeChildren->isEmpty() && ! str_contains($chain, '-') ? null : $chain]);

        foreach ($nodeChildren as $index => $child) {
            $order = $child->birth_order ?? $index + 1;
            $this->assign($child, $chain.'-'.$order, $children);
        }
    }
}
