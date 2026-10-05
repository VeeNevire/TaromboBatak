<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hidden_feed_items', function (Blueprint $table) {
            $table->id();
            $table->string('feed_type', 32);
            $table->unsignedBigInteger('feed_id');
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['feed_type', 'feed_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_feed_items');
    }
};
