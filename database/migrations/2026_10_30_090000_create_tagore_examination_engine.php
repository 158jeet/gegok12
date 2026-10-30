<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->unsignedInteger('standard_id');
            $table->foreign('standard_id')->references('id')->on('standards')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('exam_type', 40)->default('unit_test');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('submitted_by')->nullable();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('moderated_by')->nullable();
            $table->foreign('moderated_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('published_by')->nullable();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'academic_year_id', 'standard_id', 'status'], 'tagore_exam_scope_status_idx');
        });

        Schema::create('tagore_exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('tagore_exams')->cascadeOnDelete();
            $table->unsignedInteger('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->string('subject_name', 120);
            $table->decimal('max_marks', 8, 2);
            $table->decimal('pass_marks', 8, 2);
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'subject_id']);
        });

        Schema::create('tagore_exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained('tagore_exam_subjects')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('remarks', 500)->nullable();
            $table->unsignedInteger('entered_by')->nullable();
            $table->foreign('entered_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_subject_id', 'student_id']);
            $table->index(['student_id', 'exam_subject_id']);
        });

        Schema::table('tagore_results', function (Blueprint $table) {
            $table->foreignId('exam_id')->nullable()->constrained('tagore_exams')->nullOnDelete();
            $table->index(['exam_id', 'student_id'], 'tagore_results_exam_student_idx');
        });

        Schema::create('tagore_exam_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('tagore_exams')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->json('details_json')->nullable();
            $table->timestamps();
            $table->index(['exam_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tagore_results', function (Blueprint $table) {
            $table->dropIndex('tagore_results_exam_student_idx');
            $table->dropConstrainedForeignId('exam_id');
        });
        Schema::dropIfExists('tagore_exam_events');
        Schema::dropIfExists('tagore_exam_marks');
        Schema::dropIfExists('tagore_exam_subjects');
        Schema::dropIfExists('tagore_exams');
    }
};
