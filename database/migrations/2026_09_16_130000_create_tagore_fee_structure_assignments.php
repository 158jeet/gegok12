<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_fee_structure_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained('tagore_fee_structures')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->unsignedInteger('standard_link_id');
            $table->foreign('standard_link_id')->references('id')->on('standards_link')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['fee_structure_id', 'standard_link_id'], 'tagore_fee_assignment_unique');
            $table->index(['institution_id', 'academic_year_id', 'standard_link_id'], 'tagore_fee_assignment_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_fee_structure_assignments');
    }
};
