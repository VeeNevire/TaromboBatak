<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarombo_frames', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->unsignedInteger('canvas_width');
            $table->unsignedInteger('canvas_height');
            $table->unsignedInteger('area_x');
            $table->unsignedInteger('area_y');
            $table->unsignedInteger('area_width');
            $table->unsignedInteger('area_height');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarombo_frames');
    }
};
