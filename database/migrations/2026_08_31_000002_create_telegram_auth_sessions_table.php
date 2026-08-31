<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_auth_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('phone')->nullable();
            $table->string('session_path');
            $table->string('status', 30)->default('phone_code_required')->index();
            $table->longText('qr_svg')->nullable();
            $table->timestamp('qr_expires_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_auth_sessions');
    }
};
