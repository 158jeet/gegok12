<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_cbt_question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('name', 160);
            $table->string('exam_track', 24); // JEE_MAIN, JEE_ADVANCED, NEET
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'exam_track'], 'tagore_cbt_bank_scope_idx');
        });

        Schema::create('tagore_cbt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained('tagore_cbt_question_banks')->cascadeOnDelete();
            $table->string('subject_code', 24); // physics, chemistry, mathematics, biology
            $table->string('chapter', 160)->nullable();
            $table->string('difficulty', 16)->default('medium');
            $table->string('question_type', 24); // single_choice, multiple_choice, numerical
            $table->longText('stem');
            $table->json('options_json')->nullable();
            $table->json('answer_key_json');
            $table->longText('explanation')->nullable();
            $table->decimal('default_marks', 7, 2)->default(4);
            $table->decimal('default_negative_marks', 7, 2)->default(1);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['question_bank_id', 'subject_code', 'status'], 'tagore_cbt_question_bank_idx');
            $table->index(['difficulty', 'question_type'], 'tagore_cbt_question_kind_idx');
        });

        Schema::create('tagore_cbt_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('title', 180);
            $table->string('exam_track', 24);
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(false);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('published_by')->nullable();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'exam_track', 'status'], 'tagore_cbt_exam_scope_idx');
        });

        Schema::create('tagore_cbt_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('tagore_cbt_exams')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('tagore_cbt_questions')->restrictOnDelete();
            $table->string('section_code', 24)->nullable();
            $table->unsignedInteger('display_order');
            $table->decimal('marks', 7, 2);
            $table->decimal('negative_marks', 7, 2)->default(0);
            $table->timestamps();
            $table->unique(['exam_id', 'question_id']);
            $table->unique(['exam_id', 'display_order']);
            $table->index(['exam_id', 'section_code'], 'tagore_cbt_exam_section_idx');
        });

        Schema::create('tagore_cbt_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('tagore_cbt_exams')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 9, 2)->nullable();
            $table->decimal('max_score', 9, 2)->nullable();
            $table->decimal('accuracy', 6, 2)->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedSmallInteger('unanswered_count')->default(0);
            $table->timestamps();
            $table->unique(['exam_id', 'student_id', 'attempt_no'], 'tagore_cbt_attempt_number_unique');
            $table->index(['exam_id', 'status', 'score'], 'tagore_cbt_leaderboard_idx');
        });

        Schema::create('tagore_cbt_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('tagore_cbt_attempts')->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained('tagore_cbt_exam_questions')->cascadeOnDelete();
            $table->json('answer_json')->nullable();
            $table->boolean('marked_for_review')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('is_correct')->nullable();
            $table->decimal('awarded_marks', 7, 2)->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'exam_question_id']);
            $table->index(['attempt_id', 'marked_for_review'], 'tagore_cbt_response_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_cbt_responses');
        Schema::dropIfExists('tagore_cbt_attempts');
        Schema::dropIfExists('tagore_cbt_exam_questions');
        Schema::dropIfExists('tagore_cbt_exams');
        Schema::dropIfExists('tagore_cbt_questions');
        Schema::dropIfExists('tagore_cbt_question_banks');
    }
};
