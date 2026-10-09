<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sessionTable = config('session.table', 'sessions');
        if (! Schema::hasTable($sessionTable)) {
            return;
        }

        DB::table($sessionTable)->whereNotNull('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->groupBy('user_id')->orderBy('user_id')
            ->chunk(100, function ($sessions): void {
                foreach ($sessions as $session) {
                    DB::table('users')->where('id', $session->user_id)->whereNull('last_active_at')
                        ->update(['last_active_at' => Carbon::createFromTimestamp($session->last_activity, 'UTC')]);
                }
            });
    }

    public function down(): void
    {
        // Keep genuine access timestamps; the preceding schema migration removes the column.
    }
};
