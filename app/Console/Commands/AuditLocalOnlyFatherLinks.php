<?php

namespace App\Console\Commands;

use App\Models\FamilyTree;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLocalOnlyFatherLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tarombo:audit-local-only-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List person nodes whose tree-local father placement differs from their global father_id (read-only).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $treeIds = FamilyTree::query()->whereNull('based_on_id')->pluck('id');

        $rows = [];

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

                $rows[] = [
                    $treeId,
                    $node->person_id,
                    DB::table('people')->where('id', $node->person_id)->value('name'),
                    $localFatherPersonId,
                    DB::table('people')->where('id', $localFatherPersonId)->value('name'),
                    $globalFatherId,
                ];
            }
        }

        if ($rows === []) {
            $this->info('No local-only father links found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['tree_id', 'person_id', 'name', 'local father (node)', 'local father name', 'global father_id'],
            $rows,
        );
        $this->warn(sprintf('%d node(s) placed locally under a father that does not match their global father_id.', count($rows)));

        return Command::SUCCESS;
    }
}
