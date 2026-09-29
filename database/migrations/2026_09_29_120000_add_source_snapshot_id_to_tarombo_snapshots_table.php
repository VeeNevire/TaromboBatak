<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            // The original tree a Compile Gambar result was produced from.
            $table->foreignId('source_snapshot_id')
                ->nullable()
                ->after('tarombo_frame_id')
                ->constrained('tarombo_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_snapshot_id');
        });
    }
};
