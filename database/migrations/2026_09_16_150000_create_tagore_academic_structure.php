<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_academic_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
        });

        Schema::create('tagore_academic_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id');

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->unsignedInteger('standard_link_id');
            $table->foreign('standard_link_id')->references('id')->on('standards_link')->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained('tagore_academic_streams')->nullOnDelete();
            $table->string('name', 50);
            $table->string('code', 50);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['academic_year_id', 'standard_link_id', 'code'], 'tagore_academic_section_unique');
            $table->index(['institution_id', 'academic_year_id', 'standard_link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_academic_sections');
        Schema::dropIfExists('tagore_academic_streams');
    }
};
