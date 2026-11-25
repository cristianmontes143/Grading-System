<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_references', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 80)->unique();
            $table->string('title', 255);
            $table->date('publication_date')->nullable();
            $table->string('applicable_grade_levels', 150);
            $table->string('implementation_school_year', 100);
            $table->string('source_url', 255)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_confirmed_by_admin')->default(false)->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->string('version', 40)->default('1.0');
            $table->string('education_stage', 40)->index(); // kindergarten, elementary, junior_high, senior_high, k_to_10
            $table->foreignId('policy_reference_id')->nullable()->constrained('policy_references')->nullOnDelete();
            $table->string('status', 30)->default('active')->index(); // draft, active, transitioning, archived
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
        Schema::dropIfExists('policy_references');
    }
};
