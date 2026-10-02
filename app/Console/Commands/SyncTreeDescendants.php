<?php

namespace App\Console\Commands;

use App\Models\FamilyTree;
use App\Models\Person;
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

        $apply = (bool) $this->option('apply');
        $rows = [];

        foreach ($trees as $tree) {
            $missing = $this->missingDescendants($tree);
            $before = $tree->nodes()->count();

            if ($apply && $missing !== []) {
                $sync->syncTreeAndDescendantVersions($tree);
            }

            if ($missing === [] && ! $this->option('tree')) {
                continue;
            }

            $rows[] = [
                $tree->id,
                $tree->name,
                $before,
                count($missing),
                implode(', ', array_slice($missing, 0, 5)),
                $apply ? $tree->nodes()->count() : '-',
            ];
        }

        $this->table(['tree_id', 'name', 'nodes before', 'to add', 'examples', 'nodes after'], $rows);
        $this->info($apply ? 'Synced.' : 'Dry run: re-run with --apply to write.');

        return Command::SUCCESS;
    }

    /**
     * Names of people descended (via father_id) from the tree's nodes that have
     * no node yet. Read-only; mirrors the descendant walk in the sync service.
     *
     * @return array<int, string>
     */
    private function missingDescendants(FamilyTree $tree): array
    {
        $known = $tree->nodes()->pluck('person_id')->flip();
        $frontier = $known->keys()->all();
        $missing = [];

        while ($frontier !== []) {
            $children = Person::query()
                ->whereIn('father_id', $frontier)
                ->get(['id', 'name']);
            $frontier = [];

            foreach ($children as $child) {
                if ($known->has($child->id)) {
                    continue;
                }

                $known->put($child->id, true);
                $missing[] = $child->name;
                $frontier[] = $child->id;
            }
        }

        return $missing;
    }
}
