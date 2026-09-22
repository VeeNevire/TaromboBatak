<?php

namespace App\Console\Commands;

use App\Models\FamilyTree;
use App\Models\Person;
use App\Services\FatherConnectionService;
use App\Services\LocalOnlyFatherLinkFinder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class FixLocalOnlyFatherLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tarombo:fix-local-only-links {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect every tree-local-only father placement to its global father_id and sync affected trees.';

    /**
     * Execute the console command.
     */
    public function handle(LocalOnlyFatherLinkFinder $finder, FatherConnectionService $connections): int
    {
        $mismatches = $finder->find();

        if ($mismatches === []) {
            $this->info('No local-only father links found. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->table(
            ['tree_id', 'person_id', 'name', 'local father (node)', 'local father name', 'global father_id'],
            array_map(fn (array $row) => [
                $row['tree_id'],
                $row['person_id'],
                $row['person_name'],
                $row['local_father_id'],
                $row['local_father_name'],
                $row['global_father_id'],
            ], $mismatches),
        );

        if (! $this->option('force') && ! $this->confirm(sprintf(
            'Connect all %d node(s) above to their local father as their global father_id?',
            count($mismatches),
        ), true)) {
            $this->comment('Aborted. No changes made.');

            return Command::SUCCESS;
        }

        foreach ($mismatches as $row) {
            $child = Person::find($row['person_id']);
            $father = Person::find($row['local_father_id']);

            if ($child === null || $father === null) {
                $this->error(sprintf('Skipped person #%d: record no longer exists.', $row['person_id']));

                continue;
            }

            $tree = FamilyTree::find($row['tree_id']);
            $createdBy = $child->created_by ?? $tree?->user_id;

            if ($createdBy === null) {
                $this->error(sprintf('Skipped %s (#%d): no owner to attribute the change to.', $child->name, $child->id));

                continue;
            }

            try {
                $contribution = $connections->connect($child, $father, $createdBy, $tree);
            } catch (ValidationException $exception) {
                $this->error(sprintf(
                    'Skipped %s (#%d) -> %s (#%d): %s',
                    $child->name,
                    $child->id,
                    $father->name,
                    $father->id,
                    collect($exception->errors())->flatten()->first(),
                ));

                continue;
            }

            $this->info($contribution === null
                ? sprintf('Connected %s (#%d) -> %s (#%d).', $child->name, $child->id, $father->name, $father->id)
                : sprintf(
                    'Deferred %s (#%d) -> %s (#%d): pending contribution #%d awaiting approval.',
                    $child->name,
                    $child->id,
                    $father->name,
                    $father->id,
                    $contribution->id,
                ));
        }

        return Command::SUCCESS;
    }
}
