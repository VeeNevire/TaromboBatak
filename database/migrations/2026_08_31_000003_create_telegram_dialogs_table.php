<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_dialogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_account_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_peer_id');
            $table->string('type', 20);
            $table->string('title');
            $table->string('username')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['telegram_account_id', 'telegram_peer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_dialogs');
    }
};
