<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->boolean('is_removed')->default(false)->after('structure_overrides');
        });
    }

    public function down(): void
    {
        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->dropColumn('is_removed');
        });
    }
};
