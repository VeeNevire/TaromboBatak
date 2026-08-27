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
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('marga_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->bigInteger('telegram_chat_id')->nullable()->unique();
            $table->string('telegram_title')->nullable();
            $table->timestamp('telegram_linked_at')->nullable();
            $table->timestamps();

            $table->index(['marga_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_groups');
    }
};
