<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keywords the news agent searches for.
        Schema::create('marga_news_topics', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 150);
            $table->foreignId('marga_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('marga_news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marga_news_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 300);
            $table->string('url', 2048);
            $table->char('url_hash', 40)->unique();
            $table->char('title_hash', 40)->index();
            $table->string('publisher', 120)->nullable();
            $table->string('excerpt', 300)->nullable();
            $table->string('summary', 500)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('status', 16)->default('pending')->index();
            $table->string('submitted_by', 60)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marga_marga_news', function (Blueprint $table) {
            $table->foreignId('marga_news_id')->constrained('marga_news')->cascadeOnDelete();
            $table->foreignId('marga_id')->constrained()->cascadeOnDelete();
            $table->primary(['marga_news_id', 'marga_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marga_marga_news');
        Schema::dropIfExists('marga_news');
        Schema::dropIfExists('marga_news_topics');
    }
};
