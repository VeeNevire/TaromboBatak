<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A saved compile can be duplicated, so one account may keep several
        // named arrangements of the same snapshot.
        Schema::table('tarombo_compile_drafts', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('tarombo_snapshot_id');
            // Keeps an index for the user_id foreign key once the unique one is gone.
            $table->index(['user_id', 'tarombo_snapshot_id']);
            $table->dropUnique(['user_id', 'tarombo_snapshot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tarombo_compile_drafts', function (Blueprint $table) {
            $table->unique(['user_id', 'tarombo_snapshot_id']);
            $table->dropIndex(['user_id', 'tarombo_snapshot_id']);
            $table->dropColumn('name');
        });
    }
};
