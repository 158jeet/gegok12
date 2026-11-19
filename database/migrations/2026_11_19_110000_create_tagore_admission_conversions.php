<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_admission_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained('tagore_admission_leads')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->unique();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->unsignedInteger('standard_link_id');
            $table->foreign('standard_link_id')->references('id')->on('standards_link')->restrictOnDelete();
            $table->unsignedInteger('converted_by')->nullable();
            $table->foreign('converted_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_admission_conversions');
    }
};
