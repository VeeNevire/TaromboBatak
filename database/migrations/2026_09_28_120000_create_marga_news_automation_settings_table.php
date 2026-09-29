<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marga_news_automation_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('interval_minutes')->default(360);
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_status', 16)->default('idle');
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('run_lease_until')->nullable();
            $table->unsignedInteger('last_accepted')->default(0);
            $table->unsignedInteger('last_duplicates')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        DB::table('marga_news_automation_settings')->insert([
            'id' => 1,
            'enabled' => false,
            'interval_minutes' => 360,
            'last_status' => 'idle',
            'last_accepted' => 0,
            'last_duplicates' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('marga_news_automation_settings');
    }
};
