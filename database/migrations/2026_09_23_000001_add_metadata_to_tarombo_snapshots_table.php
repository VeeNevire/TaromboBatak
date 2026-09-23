<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->string('title')->nullable()->after('view');
            $table->unsignedSmallInteger('resolution')->nullable()->after('title');
            $table->string('paper_size', 4)->nullable()->after('resolution');
            $table->json('included_person_ids')->nullable()->after('paper_size');
        });
    }

    public function down(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->dropColumn(['title', 'resolution', 'paper_size', 'included_person_ids']);
        });
    }
};
