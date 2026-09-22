<?php

namespace App\Services;

use App\Models\FamilyTree;
use Illuminate\Support\Facades\DB;

class LocalOnlyFatherLinkFinder
{
    /**
     * Find person nodes on primary (non-alternative) trees whose local
     * father_node placement differs from their global people.father_id.
     *
     * @return array<int, array{tree_id: int, person_id: int, person_name: string, local_father_id: int, local_father_name: string, global_father_id: int|null}>
     */
    public function find(): array
    {
        $treeIds = FamilyTree::query()->whereNull('based_on_id')->pluck('id');
        $mismatches = [];

        foreach ($treeIds as $treeId) {
            $nodes = DB::table('family_tree_nodes')
                ->where('family_tree_id', $treeId)
                ->get(['id', 'person_id', 'father_node_id']);
            $nodesById = $nodes->keyBy('id');

            foreach ($nodes as $node) {
                $localFatherPersonId = $node->father_node_id !== null
                    ? ($nodesById[$node->father_node_id]->person_id ?? null)
                    : null;

                if ($localFatherPersonId === null) {
                    continue;
                }

                $globalFatherId = DB::table('people')->where('id', $node->person_id)->value('father_id');

                if ((int) $globalFatherId === (int) $localFatherPersonId) {
                    continue;
                }

                $mismatches[] = [
                    'tree_id' => $treeId,
                    'person_id' => $node->person_id,
                    'person_name' => DB::table('people')->where('id', $node->person_id)->value('name'),
                    'local_father_id' => $localFatherPersonId,
                    'local_father_name' => DB::table('people')->where('id', $localFatherPersonId)->value('name'),
                    'global_father_id' => $globalFatherId,
                ];
            }
        }

        return $mismatches;
    }
}
