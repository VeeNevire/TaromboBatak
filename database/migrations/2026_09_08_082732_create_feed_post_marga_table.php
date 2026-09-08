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
        Schema::create('feed_post_marga', function (Blueprint $table) {
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marga_id')->constrained()->cascadeOnDelete();
            $table->primary(['feed_post_id', 'marga_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_post_marga');
    }
};
