<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('marga_id')->nullable()->after('created_by')->constrained('margas')->nullOnDelete();
            $table->string('classification')->default('umum')->after('marga_id')->index();
            $table->string('status')->default('approved')->after('published')->index();
            $table->unsignedInteger('review_version')->default(1)->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('review_version')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');

            $table->index(['classification', 'marga_id', 'status'], 'stories_approval_scope_index');
            $table->index(['created_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['marga_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex('stories_approval_scope_index');
            $table->dropIndex(['created_by', 'status']);
            $table->dropColumn([
                'created_by',
                'marga_id',
                'classification',
                'status',
                'review_version',
                'reviewed_by',
                'reviewed_at',
                'rejection_reason',
            ]);
        });
    }
};
