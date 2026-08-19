<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $people = DB::table('people')
            ->select(['id', 'created_by', 'father_id', 'mother_id', 'updated_at'])
            ->orderBy('id')
            ->get();
        $byId = $people->keyBy('id');
        $motherIds = $people->pluck('mother_id')->filter()->flip();
        $groups = [];

        foreach ($people as $person) {
            if ($person->created_by === null) {
                continue;
            }

            // A mother stored only as a spouse must not become a separate
            // patrilineal history item merely because father_id is null.
            if ($person->father_id === null && $motherIds->has($person->id)) {
                continue;
            }

            $root = $person;
            $seen = [];

            while ($root->father_id !== null && ! isset($seen[$root->id])) {
                $seen[$root->id] = true;
                $father = $byId->get($root->father_id);

                if ($father === null) {
                    break;
                }

                $root = $father;
            }

            $key = $person->created_by.':'.$root->id;
            $groups[$key]['user_id'] = $person->created_by;
            $groups[$key]['root_person_id'] = $root->id;
            $groups[$key]['person_ids'][] = $person->id;
            $groups[$key]['updated_at'][] = $person->updated_at;
        }

        foreach ($groups as $group) {
            $updatedAt = collect($group['updated_at'])->filter()->max() ?? now();
            $treeId = DB::table('family_trees')->insertGetId([
                'user_id' => $group['user_id'],
                'root_person_id' => $group['root_person_id'],
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);

            DB::table('family_tree_person')->insert(
                collect($group['person_ids'])
                    ->unique()
                    ->map(fn ($personId) => [
                        'family_tree_id' => $treeId,
                        'person_id' => $personId,
                    ])
                    ->all(),
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally retained: backfilled history is indistinguishable from
        // trees subsequently updated by the application.
    }
};
