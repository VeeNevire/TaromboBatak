<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_tree_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_tree_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('marga_id')->nullable()->constrained('margas')->nullOnDelete();
            $table->string('tree_name');
            $table->string('root_name');
            $table->string('marga_name')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['family_tree_id', 'status']);
            $table->index(['marga_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_tree_deletion_requests');
    }
};
