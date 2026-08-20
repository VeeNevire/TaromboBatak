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
        DB::table('family_trees')->orderBy('id')->each(function (object $tree): void {
            $people = DB::table('people')
                ->join('family_tree_person', 'family_tree_person.person_id', '=', 'people.id')
                ->where('family_tree_person.family_tree_id', $tree->id)
                ->select([
                    'people.id',
                    'people.father_id',
                    'people.mother_id',
                    'people.birth_order',
                    'people.sibling_count',
                    'people.chain',
                    'people.pending_father',
                ])
                ->get();

            $personIds = $people->pluck('id')->flip();

            foreach ($people as $person) {
                DB::table('family_tree_nodes')->insert([
                    'family_tree_id' => $tree->id,
                    'person_id' => $person->id,
                    'birth_order' => $person->birth_order,
                    'sibling_count' => $person->sibling_count,
                    'chain' => $person->chain,
                    'pending_father' => $person->pending_father,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $nodes = DB::table('family_tree_nodes')
                ->where('family_tree_id', $tree->id)
                ->pluck('id', 'person_id');

            foreach ($people as $person) {
                DB::table('family_tree_nodes')
                    ->where('id', $nodes[$person->id])
                    ->update([
                        'father_node_id' => $person->father_id !== null && $personIds->has($person->father_id)
                            ? $nodes[$person->father_id]
                            : null,
                        'mother_node_id' => $person->mother_id !== null && $personIds->has($person->mother_id)
                            ? $nodes[$person->mother_id]
                            : null,
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('family_tree_nodes')->delete();
    }
};
