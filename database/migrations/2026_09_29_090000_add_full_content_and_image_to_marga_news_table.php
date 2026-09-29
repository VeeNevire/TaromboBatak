<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marga_news', function (Blueprint $table): void {
            $table->longText('content')->nullable();
            $table->string('image_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marga_news', function (Blueprint $table): void {
            $table->dropColumn(['content', 'image_url']);
        });
    }
};
