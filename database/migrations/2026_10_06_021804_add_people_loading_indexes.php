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
            $table->index(['name', 'id'], 'people_name_order_idx');
            $table->index(['marga_id', 'name', 'id'], 'people_marga_name_order_idx');
            $table->index(['gender', 'name', 'father_id'], 'people_gender_name_father_idx');
            $table->index(['father_id', 'birth_order', 'id'], 'people_father_birth_order_idx');
        });

        Schema::table('person_wife', function (Blueprint $table) {
            $table->index(['husband_id', 'position'], 'person_wife_husband_position_idx');
        });

        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->index(['father_node_id', 'is_removed', 'birth_order', 'id'], 'ft_nodes_active_child_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_tree_nodes', function (Blueprint $table) {
            $table->dropIndex('ft_nodes_active_child_order_idx');
        });

        Schema::table('person_wife', function (Blueprint $table) {
            $table->dropIndex('person_wife_husband_position_idx');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex('people_father_birth_order_idx');
            $table->dropIndex('people_gender_name_father_idx');
            $table->dropIndex('people_marga_name_order_idx');
            $table->dropIndex('people_name_order_idx');
        });
    }
};
