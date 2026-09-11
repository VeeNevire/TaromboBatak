<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->json('structure_overrides')->nullable()->after('pending_father');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->dropColumn('structure_overrides');
        });
    }
};
