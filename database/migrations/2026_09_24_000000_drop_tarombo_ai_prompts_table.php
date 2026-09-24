<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tarombo_ai_prompts');
    }

    public function down(): void
    {
        Schema::create('tarombo_ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('prompt');
            $table->timestamps();
        });
    }
};
