<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarombo_ai_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marga_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('topic', 100);
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['marga_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarombo_ai_lessons');
    }
};
