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
        Schema::create('tree_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_tree_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('marga_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('protection_scope')->nullable()->index();
            $table->string('summary');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['marga_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tree_activity_logs');
    }
};
