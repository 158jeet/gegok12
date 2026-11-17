<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_cbt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('exam_track', 30); // jee_main, jee_advanced, neet, foundation
            $table->string('subject', 40);
            $table->string('chapter', 120)->nullable();
            $table->string('topic', 120)->nullable();
            $table->string('difficulty', 20)->default('medium');
            $table->string('question_type', 30)->default('single_choice');
            $table->longText('question_text');
            $table->json('options_json')->nullable();
            $table->json('correct_answer_json');
            $table->longText('explanation')->nullable();
            $table->decimal('positive_marks', 7, 2)->default(4);
            $table->decimal('negative_marks', 7, 2)->default(1);
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'exam_track', 'subject', 'chapter', 'status'], 'tagore_cbt_q_scope_idx');
        });

        Schema::create('tagore_cbt_mock_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('title', 180);
            $table->string('exam_track', 30);
            $table->string('mode', 30)->default('full_mock'); // full_mock, sectional, chapter_test
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('attempt_limit')->default(1);
            $table->decimal('total_marks', 9, 2)->default(0);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('allow_calculator')->default(false);
            $table->json('instructions_json')->nullable();
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'exam_track', 'status'], 'tagore_cbt_test_scope_idx');
        });

        Schema::create('tagore_cbt_test_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_test_id')->constrained('tagore_cbt_mock_tests')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('subject', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->decimal('total_marks', 9, 2)->default(0);
            $table->timestamps();
            $table->unique(['mock_test_id', 'name']);
            $table->index(['mock_test_id', 'sort_order']);
        });

        Schema::create('tagore_cbt_test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('tagore_cbt_test_sections')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('tagore_cbt_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('positive_marks', 7, 2);
            $table->decimal('negative_marks', 7, 2);
            $table->timestamps();
            $table->unique(['section_id', 'question_id']);
            $table->index(['section_id', 'sort_order']);
        });

        Schema::create('tagore_cbt_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_test_id')->constrained('tagore_cbt_mock_tests')->restrictOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->dateTime('submitted_at')->nullable();
            $table->string('status', 24)->default('in_progress');
            $table->decimal('score', 9, 2)->nullable();
            $table->decimal('maximum_score', 9, 2)->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('incorrect_count')->default(0);
            $table->unsignedSmallInteger('unattempted_count')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->decimal('percentile', 7, 3)->nullable();
            $table->timestamps();
            $table->unique(['mock_test_id', 'student_id', 'attempt_no'], 'tagore_cbt_attempt_unique');
            $table->index(['mock_test_id', 'status', 'score'], 'tagore_cbt_attempt_rank_idx');
        });

        Schema::create('tagore_cbt_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('tagore_cbt_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('tagore_cbt_questions')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('tagore_cbt_test_sections')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->decimal('positive_marks', 7, 2);
            $table->decimal('negative_marks', 7, 2);
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
            $table->index(['attempt_id', 'sort_order']);
        });

        Schema::create('tagore_cbt_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_question_id')->constrained('tagore_cbt_attempt_questions')->cascadeOnDelete();
            $table->json('answer_json')->nullable();
            $table->boolean('marked_for_review')->default(false);
            $table->boolean('visited')->default(false);
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_awarded', 7, 2)->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();
            $table->unique('attempt_question_id');
        });

        Schema::create('tagore_cbt_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('tagore_cbt_attempts')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->string('event_type', 40);
            $table->unsignedBigInteger('attempt_question_id')->nullable();
            $table->json('details_json')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['attempt_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_cbt_attempt_events');
        Schema::dropIfExists('tagore_cbt_attempt_answers');
        Schema::dropIfExists('tagore_cbt_attempt_questions');
        Schema::dropIfExists('tagore_cbt_attempts');
        Schema::dropIfExists('tagore_cbt_test_questions');
        Schema::dropIfExists('tagore_cbt_test_sections');
        Schema::dropIfExists('tagore_cbt_mock_tests');
        Schema::dropIfExists('tagore_cbt_questions');
    }
};
