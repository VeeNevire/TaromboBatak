<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_dialog_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_message_id');
            $table->bigInteger('telegram_sender_id')->nullable()->index();
            $table->string('sender_name')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['telegram_account_id', 'telegram_dialog_id', 'telegram_message_id'], 'telegram_message_identity');
            $table->index(['telegram_dialog_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
