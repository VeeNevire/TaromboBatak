<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_dialogs', function (Blueprint $table): void {
            $table->unsignedInteger('unread_count')->default(0)->after('last_message_at');
            $table->timestamp('last_read_at')->nullable()->after('unread_count');
        });

        Schema::table('telegram_messages', function (Blueprint $table): void {
            $table->boolean('is_outgoing')->default(false)->after('telegram_sender_id');
            $table->string('media_type', 40)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_dialogs', function (Blueprint $table): void {
            $table->dropColumn(['unread_count', 'last_read_at']);
        });

        Schema::table('telegram_messages', function (Blueprint $table): void {
            $table->dropColumn(['is_outgoing', 'media_type']);
        });
    }
};
