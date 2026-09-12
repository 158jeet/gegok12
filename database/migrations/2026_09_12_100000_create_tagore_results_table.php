<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('exam_name');
            $table->string('subject');
            $table->decimal('marks', 8, 2)->default(0);
            $table->decimal('max_marks', 8, 2)->default(100);
            $table->string('grade', 10)->nullable();
            $table->string('status', 20)->default('published');
            $table->timestamps();
            $table->index(['student_id', 'institution_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_results');
    }
};
