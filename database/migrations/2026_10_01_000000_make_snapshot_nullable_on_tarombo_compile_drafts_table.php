<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A compile started on a blank canvas has no tree image to belong to.
        Schema::table('tarombo_compile_drafts', function (Blueprint $table) {
            $table->foreignId('tarombo_snapshot_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('tarombo_compile_drafts')->whereNull('tarombo_snapshot_id')->delete();

        Schema::table('tarombo_compile_drafts', function (Blueprint $table) {
            $table->foreignId('tarombo_snapshot_id')->nullable(false)->change();
        });
    }
};
