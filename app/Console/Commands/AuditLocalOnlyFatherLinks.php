<?php

namespace App\Console\Commands;

use App\Services\LocalOnlyFatherLinkFinder;
use Illuminate\Console\Command;

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
    public function handle(LocalOnlyFatherLinkFinder $finder): int
    {
        $mismatches = $finder->find();

        if ($mismatches === []) {
            $this->info('No local-only father links found.');

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
        $this->warn(sprintf('%d node(s) placed locally under a father that does not match their global father_id.', count($mismatches)));

        return Command::SUCCESS;
    }
}
