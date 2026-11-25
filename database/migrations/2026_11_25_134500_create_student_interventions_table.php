<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('grading_period_id')->nullable()->constrained('grading_periods')->nullOnDelete();
            $table->string('category', 30)->default('remediation'); // remediation, parent_conference, counseling, commendation
            $table->text('notes');
            $table->text('action_plan')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 20)->default('open'); // open, resolved
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['student_enrollment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_interventions');
    }
};
