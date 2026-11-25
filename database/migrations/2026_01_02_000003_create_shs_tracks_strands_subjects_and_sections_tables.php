<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shs_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['curriculum_id', 'code'], 'shs_tracks_curriculum_code_unique');
        });

        Schema::create('shs_strands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shs_track_id')->constrained('shs_tracks')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('type', 30)->default('strand'); // strand (legacy) | elective_cluster (SSHS)
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['shs_track_id', 'code'], 'shs_strands_track_code_unique');
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('shs_track_id')->nullable()->constrained('shs_tracks')->nullOnDelete();
            $table->foreignId('shs_strand_id')->nullable()->constrained('shs_strands')->nullOnDelete();
            $table->foreignId('parent_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('subject_category', 40)->default('learning_area'); // learning_area, core, applied, specialized, elective, doorway
            $table->string('weight_group', 50)->default('core_academic')->index();
            $table->unsignedTinyInteger('semester')->nullable(); // 1 or 2 for SHS
            $table->boolean('is_graded')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['curriculum_id', 'grade_level_id', 'code'], 'subjects_curr_grade_code_unique');
            $table->index(['curriculum_id', 'grade_level_id', 'is_active'], 'subjects_curr_grade_active_idx');
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('shs_track_id')->nullable()->constrained('shs_tracks')->nullOnDelete();
            $table->foreignId('shs_strand_id')->nullable()->constrained('shs_strands')->nullOnDelete();
            $table->unsignedBigInteger('adviser_id')->nullable()->index(); // FK to teachers added in Module 3
            $table->string('name', 100);
            $table->string('room', 60)->nullable();
            $table->unsignedSmallInteger('capacity')->default(45);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['school_year_id', 'grade_level_id', 'name'], 'sections_sy_grade_name_unique');
            $table->index(['school_year_id', 'grade_level_id'], 'sections_sy_grade_idx');
        });

        DB::statement('ALTER TABLE sections ADD CONSTRAINT sections_capacity_chk CHECK (capacity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('shs_strands');
        Schema::dropIfExists('shs_tracks');
    }
};
