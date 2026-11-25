<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->string('name', 80); // First Term / First Quarter
            $table->string('code', 20); // T1..T3 or Q1..Q4
            $table->unsignedSmallInteger('sequence'); // 1..4
            $table->unsignedTinyInteger('semester')->nullable(); // 1 or 2 when applicable
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('submission_deadline')->nullable();
            $table->string('status', 20)->default('upcoming')->index(); // upcoming, open, locked
            $table->timestampTz('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['school_year_id', 'code'], 'grading_periods_sy_code_unique');
            $table->unique(['school_year_id', 'sequence'], 'grading_periods_sy_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_periods');
    }
};
