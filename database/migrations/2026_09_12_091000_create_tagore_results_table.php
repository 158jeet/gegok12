<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('exam_name', 120);
            $table->string('subject', 120);
            $table->decimal('marks', 8, 2)->default(0);
            $table->decimal('max_marks', 8, 2)->default(100);
            $table->string('grade', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'institution_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_results');
    }
};
