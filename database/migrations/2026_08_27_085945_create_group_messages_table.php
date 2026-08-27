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
        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20);
            $table->text('body');
            $table->bigInteger('telegram_sender_id')->nullable()->index();
            $table->string('telegram_sender_name')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('telegram_delivery_status', 20)->nullable()->index();
            $table->text('telegram_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_group_id', 'telegram_message_id']);
            $table->index(['chat_group_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
