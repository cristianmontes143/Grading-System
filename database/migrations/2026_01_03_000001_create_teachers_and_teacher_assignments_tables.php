<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('employee_number', 50)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('specialization', 150)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['last_name', 'first_name'], 'teachers_name_idx');
        });

        // Add referential integrity FK on sections.adviser_id -> teachers.id
        Schema::table('sections', function (Blueprint $table) {
            $table->foreign('adviser_id')
                ->references('id')
                ->on('teachers')
                ->nullOnDelete();
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->string('schedule_summary', 150)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['school_year_id', 'teacher_id', 'is_active'], 'ta_sy_teacher_active_idx');
            $table->index(['section_id', 'is_active'], 'ta_section_active_idx');
        });

        // PostgreSQL partial unique index: only one active subject teacher per (school_year, section, subject)
        DB::statement('CREATE UNIQUE INDEX teacher_assignments_unique_active_subject ON teacher_assignments (school_year_id, section_id, subject_id) WHERE is_active = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');

        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['adviser_id']);
        });

        Schema::dropIfExists('teachers');
    }
};
