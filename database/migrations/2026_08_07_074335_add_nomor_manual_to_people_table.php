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
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('nomor_manual')->default(false)->after('nomor');
        });

        // Existing manually-entered numbers are preserved and treated as manual
        // overrides so the auto-numbering never overwrites them.
        DB::table('people')
            ->whereNotNull('nomor')
            ->update(['nomor_manual' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('nomor_manual');
        });
    }
};
