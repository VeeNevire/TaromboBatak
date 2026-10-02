<?php

namespace App\Console\Commands;

use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Services\SiblingOrderSyncService;
use Illuminate\Console\Command;

class SyncSiblingOrder extends Command
{
    protected $signature = 'tarombo:sync-sibling-order {--father= : Limit to one father person id} {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Copy the global brother order (people.birth_order) into every original family tree holding the same father (lists fathers it would process).';

    public function handle(SiblingOrderSyncService $sync): int
    {
        $fatherIds = FamilyTreeNode::query()
            ->whereHas('familyTree', fn ($tree) => $tree->whereNull('based_on_id'))
            ->whereNotNull('father_node_id')
            ->with('fatherNode:id,person_id')
            ->get()
            ->pluck('fatherNode.person_id')
            ->filter()
            ->unique()
            ->when($this->option('father'), fn ($ids) => $ids->filter(fn ($id) => (int) $id === (int) $this->option('father')));

        $rows = [];

        foreach ($fatherIds as $fatherId) {
            $children = Person::query()
                ->where('father_id', $fatherId)
                ->where(fn ($query) => $query->where('gender', 'L')->orWhereNull('gender'))
                ->whereNotNull('birth_order')
                ->orderBy('birth_order')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if (count($children) < 2) {
                continue;
            }

            $rows[] = [$fatherId, count($children)];

            if ($this->option('apply')) {
                $sync->personsToTrees((int) $fatherId, $children);
            }
        }

        $this->table(['father_person_id', 'brothers'], $rows);
        $this->info($this->option('apply') ? 'Synced.' : 'Dry run: re-run with --apply to write.');

        return Command::SUCCESS;
    }
}
