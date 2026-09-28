<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->string('orientation', 9)->nullable()->after('paper_size');
        });
    }

    public function down(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->dropColumn('orientation');
        });
    }
};
