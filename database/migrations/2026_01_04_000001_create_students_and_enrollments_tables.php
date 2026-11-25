<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 50)->unique();
            $table->string('lrn', 20)->nullable()->unique(); // 12-digit DepEd Learner Reference Number
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('gender', 20);
            $table->date('date_of_birth');
            $table->string('photo_path', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_contact', 50)->nullable();
            $table->string('status', 30)->default('active')->index(); // active, transferred_out, graduated, dropped, archived
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['last_name', 'first_name'], 'students_name_idx');
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $table->foreignId('shs_track_id')->nullable()->constrained('shs_tracks')->nullOnDelete();
            $table->foreignId('shs_strand_id')->nullable()->constrained('shs_strands')->nullOnDelete();
            $table->string('enrollment_status', 30)->default('enrolled')->index();
            // enrolled, transferred_section, transferred_out, dropped, completed, promoted, retained
            $table->date('enrolled_at');
            $table->foreignId('transferred_from_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->text('transfer_reason')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestampsTz();

            $table->index(['section_id', 'is_current'], 'se_section_current_idx');
            $table->index(['school_year_id', 'grade_level_id'], 'se_sy_grade_idx');
            $table->index(['student_id', 'school_year_id'], 'se_student_sy_idx');
        });

        // PostgreSQL partial unique index: at most one active enrollment per student per school year
        DB::statement('CREATE UNIQUE INDEX student_enrollments_one_current_per_sy ON student_enrollments (student_id, school_year_id) WHERE is_current = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
    }
};
