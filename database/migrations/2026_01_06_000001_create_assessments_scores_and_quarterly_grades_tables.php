<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_assignment_id')->constrained('teacher_assignments')->restrictOnDelete();
            $table->foreignId('grading_period_id')->constrained('grading_periods')->restrictOnDelete();
            $table->foreignId('grading_component_id')->constrained('grading_components')->restrictOnDelete();
            $table->string('title', 150);
            $table->string('assessment_type', 40)->default('quiz');
            // quiz, written_activity, performance_task, project, summative_test, examination, other
            $table->decimal('max_score', 7, 2);
            $table->date('assessment_date')->nullable();
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['teacher_assignment_id', 'grading_period_id'], 'assessments_ta_period_idx');
            $table->index(['grading_component_id', 'grading_period_id'], 'assessments_comp_period_idx');
        });

        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_max_score_chk CHECK (max_score > 0)');

        Schema::create('student_assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->decimal('raw_score', 7, 2)->nullable();
            $table->string('status', 20)->default('scored'); // scored, missing, excused
            $table->string('remarks', 255)->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['assessment_id', 'student_enrollment_id'], 'sas_assessment_enrollment_unique');
            $table->index('student_enrollment_id', 'sas_enrollment_idx');
        });

        DB::statement('ALTER TABLE student_assessment_scores ADD CONSTRAINT sas_raw_score_nonneg_chk CHECK (raw_score IS NULL OR raw_score >= 0)');

        Schema::create('quarterly_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained('teacher_assignments')->restrictOnDelete();
            $table->foreignId('grading_period_id')->constrained('grading_periods')->restrictOnDelete();
            $table->foreignId('grading_configuration_id')->constrained('grading_configurations')->restrictOnDelete();
            $table->jsonb('component_breakdown_json')->nullable();
            $table->decimal('initial_grade', 6, 2)->nullable();
            $table->decimal('transmuted_grade', 5, 2)->nullable();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->string('descriptive_rating', 30)->nullable();
            $table->string('remarks', 120)->nullable();
            $table->unsignedInteger('missing_scores_count')->default(0);
            $table->boolean('is_official')->default(false)->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->timestampTz('computed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['student_enrollment_id', 'subject_id', 'grading_period_id'], 'qg_enrollment_subject_period_unique');
            $table->index(['teacher_assignment_id', 'grading_period_id'], 'qg_ta_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_grades');
        Schema::dropIfExists('student_assessment_scores');
        Schema::dropIfExists('assessments');
    }
};
