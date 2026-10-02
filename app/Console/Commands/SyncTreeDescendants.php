<?php

namespace App\Console\Commands;

use App\Models\FamilyTree;
use App\Services\FamilyTreeDescendantSyncService;
use Illuminate\Console\Command;

class SyncTreeDescendants extends Command
{
    protected $signature = 'tarombo:sync-descendants {--tree= : Limit to one original tree id} {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Materialize people missing from original family trees (and the versions built on them).';

    public function handle(FamilyTreeDescendantSyncService $sync): int
    {
        $trees = FamilyTree::query()
            ->whereNull('based_on_id')
            ->whereNotNull('root_person_id')
            ->when($this->option('tree'), fn ($query) => $query->whereKey((int) $this->option('tree')))
            ->orderBy('id')
            ->get(['id', 'name']);

        $rows = [];

        foreach ($trees as $tree) {
            $before = $tree->nodes()->count();

            if ($this->option('apply')) {
                $sync->syncTreeAndDescendantVersions($tree);
                $rows[] = [$tree->id, $tree->name, $before, $tree->nodes()->count()];
            } else {
                $rows[] = [$tree->id, $tree->name, $before, '-'];
            }
        }

        $this->table(['tree_id', 'name', 'nodes before', 'nodes after'], $rows);
        $this->info($this->option('apply') ? 'Synced.' : 'Dry run: re-run with --apply to write.');

        return Command::SUCCESS;
    }
}
