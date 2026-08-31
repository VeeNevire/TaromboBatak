<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table): void {
            $table->string('session_path')->nullable()->after('display_name');
            $table->string('connection_status', 20)->default('connected')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table): void {
            $table->dropColumn(['session_path', 'connection_status', 'last_error', 'last_seen_at']);
        });
    }
};
