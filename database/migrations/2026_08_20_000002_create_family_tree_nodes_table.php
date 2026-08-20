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
        Schema::create('family_tree_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_tree_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('father_node_id')->nullable()->constrained('family_tree_nodes')->nullOnDelete();
            $table->foreignId('mother_node_id')->nullable()->constrained('family_tree_nodes')->nullOnDelete();
            $table->unsignedInteger('birth_order')->nullable();
            $table->unsignedInteger('sibling_count')->nullable();
            $table->string('chain')->nullable();
            $table->boolean('pending_father')->default(false);
            $table->timestamps();

            $table->unique(['family_tree_id', 'person_id']);
            $table->index(['family_tree_id', 'father_node_id', 'birth_order'], 'ft_nodes_parent_order_idx');
            $table->index(['family_tree_id', 'chain'], 'ft_nodes_chain_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_tree_nodes');
    }
};
