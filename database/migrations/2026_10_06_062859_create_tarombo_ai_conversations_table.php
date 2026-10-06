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
        Schema::create('tarombo_ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marga_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'marga_id', 'id'], 'tarombo_ai_user_scope_idx');
        });

        Schema::create('tarombo_ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('tarombo_ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('text');
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarombo_ai_messages');
        Schema::dropIfExists('tarombo_ai_conversations');
    }
};
