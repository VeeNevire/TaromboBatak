<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('family_trees', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false)->after('is_primary');
        });

        // Trees created by the system are named "Keluarga {root person}" and
        // are never versions; trees without a name were also not named by hand.
        DB::table('family_trees')
            ->leftJoin('people', 'people.id', '=', 'family_trees.root_person_id')
            ->whereNull('family_trees.based_on_id')
            ->select(['family_trees.id', 'family_trees.name', 'people.name as root_name'])
            ->orderBy('family_trees.id')
            ->chunk(500, function ($trees) {
                $ids = $trees
                    ->filter(fn (object $tree) => $tree->name === null
                        || ($tree->root_name !== null && trim($tree->name) === 'Keluarga '.$tree->root_name))
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('family_trees')->whereIn('id', $ids)->update(['is_auto_generated' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_trees', function (Blueprint $table) {
            $table->dropColumn('is_auto_generated');
        });
    }
};
