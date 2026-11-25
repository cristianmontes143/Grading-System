<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name', 200);
            $table->string('school_id_code', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('division', 150)->nullable();
            $table->string('district', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('principal_name', 150)->nullable();
            $table->string('principal_title', 100)->default('School Principal');
            $table->string('logo_path', 255)->nullable();
            $table->boolean('attendance_enabled')->default(true);
            $table->boolean('observed_values_enabled')->default(true);
            $table->string('default_calendar_mode', 30)->default('trimester');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
