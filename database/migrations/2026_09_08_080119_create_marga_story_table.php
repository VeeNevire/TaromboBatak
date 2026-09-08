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
        Schema::create('marga_story', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marga_id')->constrained()->cascadeOnDelete();
            $table->primary(['story_id', 'marga_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marga_story');
    }
};
