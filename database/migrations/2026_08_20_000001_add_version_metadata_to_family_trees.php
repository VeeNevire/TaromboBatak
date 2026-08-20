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
        Schema::table('family_trees', function (Blueprint $table) {
            $table->string('name')->nullable()->after('root_person_id');
            $table->text('description')->nullable()->after('name');
            $table->string('source_name')->nullable()->after('description');
            $table->string('source_url')->nullable()->after('source_name');
            $table->foreignId('based_on_id')->nullable()->after('source_url')->constrained('family_trees')->nullOnDelete();
            $table->boolean('is_primary')->default(false)->after('based_on_id');

            $table->dropUnique(['user_id', 'root_person_id']);
            $table->index(['user_id', 'root_person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_trees', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'root_person_id']);
            $table->unique(['user_id', 'root_person_id']);
            $table->dropConstrainedForeignId('based_on_id');
            $table->dropColumn(['name', 'description', 'source_name', 'source_url', 'is_primary']);
        });
    }
};
