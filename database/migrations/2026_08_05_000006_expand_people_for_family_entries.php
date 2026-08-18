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
            $table->renameColumn('parent_id', 'father_id');

            $table->foreignId('mother_id')
                ->nullable()
                ->after('father_id')
                ->constrained('people')
                ->nullOnDelete();

            $table->string('gender')->nullable()->after('name');
            $table->unsignedInteger('birth_order')->nullable()->after('father_id');
            $table->unsignedInteger('sibling_count')->nullable()->after('birth_order');
            $table->string('nomor')->nullable()->unique()->after('sibling_count');
            $table->string('spouse')->nullable()->after('bio');
            $table->string('spouse_marga')->nullable()->after('spouse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['nomor']);

            $table->dropForeign(['mother_id']);
            $table->dropColumn('mother_id');

            $table->dropColumn([
                'spouse_marga',
                'spouse',
                'nomor',
                'sibling_count',
                'birth_order',
                'gender',
            ]);

            $table->renameColumn('father_id', 'parent_id');
        });
    }
};
