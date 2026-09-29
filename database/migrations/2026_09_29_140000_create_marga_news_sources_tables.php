<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marga_news_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('website_url', 2048);
            $table->string('domain', 253)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('applies_to_all_topics')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('marga_news_source_topic', function (Blueprint $table): void {
            $table->foreignId('marga_news_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marga_news_topic_id')->constrained()->cascadeOnDelete();
            $table->primary(['marga_news_source_id', 'marga_news_topic_id']);
        });

        Schema::table('marga_news', function (Blueprint $table): void {
            $table->foreignId('marga_news_source_id')
                ->nullable()
                ->after('marga_news_topic_id')
                ->constrained('marga_news_sources')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marga_news', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('marga_news_source_id');
        });

        Schema::dropIfExists('marga_news_source_topic');
        Schema::dropIfExists('marga_news_sources');
    }
};
