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
            // A value on a node starts a family branch. Descendants without
            // their own value inherit it through father_node_id.
            $table->string('family_name', 120)->nullable()->after('pending_father');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->dropColumn('family_name');
        });
    }
};
