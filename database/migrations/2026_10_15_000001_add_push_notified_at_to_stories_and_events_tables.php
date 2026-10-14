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
        foreach (['stories', 'events'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('push_notified_at')->nullable()->index();
            });

            // Only stories and events published after this release reach phones.
            DB::table($table)->update(['push_notified_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['stories', 'events'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['push_notified_at']);
                $table->dropColumn('push_notified_at');
            });
        }
    }
};
