<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('news_feed_read_at')->nullable()->index()->after('village_code');
        });

        Schema::table('chat_group_members', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable()->index()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('chat_group_members', function (Blueprint $table) {
            $table->dropIndex(['last_read_at']);
            $table->dropColumn('last_read_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['news_feed_read_at']);
            $table->dropColumn('news_feed_read_at');
        });
    }
};
