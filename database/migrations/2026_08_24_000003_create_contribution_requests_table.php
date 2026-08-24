<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('matched_father_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('subject_person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('family_tree_id')->nullable()->constrained()->nullOnDelete();
            $table->json('affected_person_ids');
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'subject_person_id', 'status'], 'contribution_subject_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_requests');
    }
};
