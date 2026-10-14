<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marga_news', function (Blueprint $table) {
            $table->timestamp('push_notified_at')->nullable()->index();
        });

        // Only news approved after this release should reach phones.
        DB::table('marga_news')->update(['push_notified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marga_news', function (Blueprint $table) {
            $table->dropIndex(['push_notified_at']);
            $table->dropColumn('push_notified_at');
        });
    }
};
