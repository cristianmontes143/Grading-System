<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedial_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->decimal('final_rating', 5, 2);
            $table->decimal('remedial_class_mark', 5, 2);
            $table->decimal('recomputed_final_grade', 5, 2);
            $table->string('remarks', 30); // PASSED or FAILED
            $table->date('conducted_from')->nullable();
            $table->date('conducted_to')->nullable();
            $table->string('conducted_by', 150)->nullable();
            $table->string('school_name', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'subject_id'], 'remedial_classes_enrollment_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedial_classes');
    }
};
