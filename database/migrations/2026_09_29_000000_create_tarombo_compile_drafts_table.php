<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The saved Compile Gambar arrangement of one account for one snapshot.
        Schema::create('tarombo_compile_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tarombo_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tarombo_frame_id')->nullable()->constrained()->nullOnDelete();
            $table->json('state');
            $table->timestamps();

            $table->unique(['user_id', 'tarombo_snapshot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarombo_compile_drafts');
    }
};
