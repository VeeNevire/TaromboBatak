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
        Schema::create('telegram_announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_announcement_id');
            $table->foreign('telegram_announcement_id', 'tar_recipient_announcement_fk')
                ->references('id')
                ->on('telegram_announcements')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('chat_id')->nullable();
            $table->string('recipient_name');
            $table->string('status', 20)->default('pending')->index();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['telegram_announcement_id', 'user_id'],
                'tar_recipient_announcement_user_uq',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_announcement_recipients');
    }
};
