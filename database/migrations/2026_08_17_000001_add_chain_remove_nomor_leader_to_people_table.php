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
        Schema::table('people', function (Blueprint $table) {
            $table->string('chain')->nullable()->index()->after('sibling_count');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['nomor']);
            $table->dropColumn(['nomor', 'nomor_manual', 'is_leader']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('is_leader')->default(false)->after('sibling_count');
            $table->boolean('nomor_manual')->default(false);
            $table->string('nomor')->nullable()->unique();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['chain']);
            $table->dropColumn('chain');
        });
    }
};
