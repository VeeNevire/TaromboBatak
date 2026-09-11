<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->foreignId('tarombo_frame_id')
                ->nullable()
                ->after('center_person_id')
                ->constrained('tarombo_frames')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tarombo_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarombo_frame_id');
        });
    }
};
