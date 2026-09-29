<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marga_news_automation_settings', function (Blueprint $table): void {
            $table->text('prompt')->nullable()->after('interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('marga_news_automation_settings', function (Blueprint $table): void {
            $table->dropColumn('prompt');
        });
    }
};
