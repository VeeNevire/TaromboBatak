<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->index()->after('pending_father');
        });

        DB::table('people')
            ->whereNull('created_by')
            ->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }
};
