<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmutation_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('policy_reference_id')->nullable()->constrained('policy_references')->nullOnDelete();
            $table->decimal('passing_initial_grade', 5, 2)->default(60.00);
            $table->decimal('passing_transmuted_grade', 5, 2)->default(75.00);
            $table->boolean('is_confirmed_by_admin')->default(false)->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('transmutation_table_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transmutation_table_id')->constrained('transmutation_tables')->cascadeOnDelete();
            $table->decimal('min_initial_grade', 6, 2);
            $table->decimal('max_initial_grade', 6, 2);
            $table->decimal('transmuted_grade', 5, 2);

            $table->index(['transmutation_table_id', 'min_initial_grade', 'max_initial_grade'], 'ttr_lookup_idx');
        });

        DB::statement('ALTER TABLE transmutation_table_rows ADD CONSTRAINT ttr_range_chk CHECK (min_initial_grade <= max_initial_grade)');

        Schema::create('grading_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->restrictOnDelete();
            $table->foreignId('curriculum_id')->constrained('curricula')->restrictOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->restrictOnDelete();
            $table->string('weight_group', 50)->nullable()->index();
            $table->string('name', 180);
            $table->string('grading_mode', 30)->default('numerical_transmuted')->index();
            // numerical_transmuted, numerical_zero_based, descriptive
            $table->foreignId('transmutation_table_id')->nullable()->constrained('transmutation_tables')->nullOnDelete();
            $table->decimal('passing_grade', 5, 2)->default(75.00);
            $table->unsignedSmallInteger('rounding_precision')->default(0);
            $table->jsonb('descriptive_scale_json')->nullable();
            $table->jsonb('remarks_scale_json')->nullable();
            $table->foreignId('policy_reference_id')->nullable()->constrained('policy_references')->nullOnDelete();
            $table->boolean('is_confirmed_by_admin')->default(false)->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->index(['school_year_id', 'curriculum_id', 'is_active'], 'gc_sy_curr_active_idx');
        });

        Schema::create('grading_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_configuration_id')->constrained('grading_configurations')->cascadeOnDelete();
            $table->foreignId('parent_component_id')->nullable()->constrained('grading_components')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->decimal('weight_percentage', 5, 2);
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->timestampsTz();

            $table->index(['grading_configuration_id', 'parent_component_id'], 'gcomp_config_parent_idx');
        });

        DB::statement('ALTER TABLE grading_components ADD CONSTRAINT gcomp_weight_chk CHECK (weight_percentage > 0 AND weight_percentage <= 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_components');
        Schema::dropIfExists('grading_configurations');
        Schema::dropIfExists('transmutation_table_rows');
        Schema::dropIfExists('transmutation_tables');
    }
};
