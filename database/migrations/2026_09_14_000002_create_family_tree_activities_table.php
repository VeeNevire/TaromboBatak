<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_tree_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_tree_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tree_name');
            $table->string('action', 50)->index();
            $table->text('description');
            $table->timestamps();

            $table->index(['owner_id', 'created_at']);
            $table->index(['family_tree_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_tree_activities');
    }
};
