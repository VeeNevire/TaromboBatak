<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_id');
            $table->string('provider_email');
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->index(['provider', 'provider_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_accounts');
    }
};
