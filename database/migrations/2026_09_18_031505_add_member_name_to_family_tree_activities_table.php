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
        Schema::table('family_tree_activities', function (Blueprint $table) {
            $table->string('member_name')->nullable()->after('tree_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_tree_activities', function (Blueprint $table) {
            $table->dropColumn('member_name');
        });
    }
};
