<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_assignment_id')->constrained('teacher_assignments')->restrictOnDelete();
            $table->foreignId('grading_period_id')->constrained('grading_periods')->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index();
            // pending, draft, submitted, returned, approved, locked
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('graded_students')->default(0);
            $table->unsignedInteger('missing_scores_count')->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['teacher_assignment_id', 'grading_period_id'], 'gs_ta_period_unique');
            $table->index(['grading_period_id', 'status'], 'gs_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_submissions');
    }
};
