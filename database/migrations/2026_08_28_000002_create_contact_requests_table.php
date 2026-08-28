<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['requester_id', 'recipient_id']);
            $table->index(['recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
