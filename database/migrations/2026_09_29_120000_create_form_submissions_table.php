<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 100);
            $table->string('submission_key', 80);
            $table->timestamp('created_at');

            $table->unique(['user_id', 'scope', 'submission_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
