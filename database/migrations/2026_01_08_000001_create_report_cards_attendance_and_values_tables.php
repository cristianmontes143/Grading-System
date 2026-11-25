<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('grading_period_id')->nullable()->constrained('grading_periods')->nullOnDelete();
            $table->unsignedTinyInteger('month_number'); // 1..12
            $table->unsignedSmallInteger('year_number');
            $table->decimal('school_days', 4, 1)->default(0);
            $table->decimal('days_present', 4, 1)->default(0);
            $table->decimal('days_absent', 4, 1)->default(0);
            $table->decimal('days_tardy', 4, 1)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['student_enrollment_id', 'year_number', 'month_number'], 'att_enrollment_ym_unique');
        });

        Schema::create('student_observed_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('grading_period_id')->constrained('grading_periods')->restrictOnDelete();
            $table->string('core_value', 40); // maka_diyos, makatao, maka_kalikasan, maka_bansa
            $table->string('behavior_statement_code', 20); // MD1, MD2, MT1, MT2, MK1, MB1, MB2
            $table->string('rating', 10); // AO, SO, RO, NO
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(
                ['student_enrollment_id', 'grading_period_id', 'behavior_statement_code'],
                'sov_enrollment_period_code_unique'
            );
        });

        Schema::create('report_card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('curriculum_id')->nullable()->constrained('curricula')->nullOnDelete();
            $table->string('education_level', 30)->nullable();
            $table->string('header_subtitle', 200)->default('Learner Progress Report Card');
            $table->string('disclaimer_label', 200)->default('DepEd-Compatible Learner Progress Report Card (School-Issued)');
            $table->text('footer_note')->nullable();
            $table->boolean('show_attendance')->default(true);
            $table->boolean('show_observed_values')->default(true);
            $table->boolean('show_lrn')->default(true);
            $table->string('paper_size', 20)->default('a4'); // a4, letter, legal
            $table->string('orientation', 20)->default('portrait'); // portrait, landscape
            $table->boolean('is_default')->default(false)->index();
            $table->timestampsTz();
        });

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('grading_period_id')->nullable()->constrained('grading_periods')->nullOnDelete();
            $table->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('report_card_templates')->nullOnDelete();
            $table->decimal('general_average', 5, 2)->nullable();
            $table->string('promotion_status', 30)->default('in_progress');
            // promoted, conditional, retained, incomplete, in_progress
            $table->boolean('is_official')->default(false)->index();
            $table->boolean('has_missing_grades')->default(false);
            $table->jsonb('missing_subjects_json')->nullable();
            $table->jsonb('snapshot_json')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('generated_at')->nullable();
            $table->timestampsTz();

            $table->index(['student_enrollment_id', 'school_year_id'], 'rc_enrollment_sy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('report_card_templates');
        Schema::dropIfExists('student_observed_values');
        Schema::dropIfExists('attendance_records');
    }
};
