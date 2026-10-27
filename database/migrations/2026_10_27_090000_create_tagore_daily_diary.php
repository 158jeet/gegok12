<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_daily_diary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->date('diary_date')->index();
            $table->decimal('study_hours', 4, 2)->nullable();
            $table->text('parent_feedback')->nullable();
            $table->text('school_feedback')->nullable();
            $table->text('homework')->nullable();
            $table->unsignedBigInteger('parent_updated_by')->nullable();
            $table->unsignedBigInteger('school_updated_by')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'diary_date']);
            $table->index(['institution_id', 'diary_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_daily_diary');
    }
};
