<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique(); // e.g. 2026-2027
            $table->date('start_date');
            $table->date('end_date');
            $table->string('term_structure', 30)->default('trimester'); // trimester, quarterly, semestral
            $table->boolean('is_active')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestampsTz();
        });

        // PostgreSQL partial unique index: guarantees at most one active school year at any time
        DB::statement('CREATE UNIQUE INDEX school_years_single_active_idx ON school_years (is_active) WHERE is_active = true');
        DB::statement('ALTER TABLE school_years ADD CONSTRAINT school_years_date_range_chk CHECK (end_date > start_date)');

        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->unique(); // K, G1..G12
            $table->string('name', 60); // Kindergarten, Grade 1..Grade 12
            $table->string('key_stage', 10)->index(); // KS1, KS2, KS3, KS4
            $table->string('education_level', 30)->index(); // kindergarten, elementary, junior_high, senior_high
            $table->unsignedSmallInteger('sort_order')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('curriculum_grade_implementations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $table->string('implementation_status', 30)->default('active'); // active, pilot, phasing_out
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['school_year_id', 'grade_level_id'], 'curr_grade_impl_sy_grade_unique');
            $table->index(['school_year_id', 'curriculum_id'], 'curr_grade_impl_sy_curr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_grade_implementations');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('school_years');
    }
};
