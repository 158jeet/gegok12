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
            $table->string('question_code', 80);
            $table->string('subject', 40);
            $table->string('chapter', 120);
            $table->string('topic', 120)->nullable();
            $table->string('difficulty', 16)->default('medium');
            $table->string('question_type', 32);
            $table->longText('stem');
            $table->json('options_json')->nullable();
            $table->json('answer_json');
            $table->longText('explanation')->nullable();
            $table->decimal('default_marks', 8, 2)->default(4);
            $table->decimal('default_negative_marks', 8, 2)->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['institution_id', 'question_code', 'version'], 'tagore_cbt_question_version_unique');
            $table->index(['institution_id', 'subject', 'chapter', 'difficulty', 'status'], 'tagore_cbt_question_search_idx');
        });

        Schema::create('tagore_cbt_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('exam_type', 24);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->text('instructions')->nullable();
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(false);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('published_by')->nullable();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'exam_type', 'status'], 'tagore_cbt_paper_scope_idx');
        });

        Schema::create('tagore_cbt_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained('tagore_cbt_papers')->cascadeOnDelete();
            $table->string('section_key', 24);
            $table->string('title', 100);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->unsignedSmallInteger('question_limit')->nullable();
            $table->unsignedSmallInteger('required_answer_count')->nullable();
            $table->string('section_type', 24)->default('mixed');
            $table->timestamps();
            $table->unique(['paper_id', 'section_key']);
        });

        Schema::create('tagore_cbt_paper_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained('tagore_cbt_papers')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('tagore_cbt_sections')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('tagore_cbt_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->decimal('marks', 8, 2);
            $table->decimal('negative_marks', 8, 2)->default(0);
            $table->json('question_snapshot_json');
            $table->timestamps();
            $table->unique(['paper_id', 'question_id']);
            $table->index(['paper_id', 'section_id', 'sort_order'], 'tagore_cbt_paper_question_order_idx');
        });

        Schema::create('tagore_cbt_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained('tagore_cbt_papers')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->json('question_order_json');
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->unsignedInteger('unanswered_count')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->decimal('percentile', 7, 4)->nullable();
            $table->timestamps();
            $table->unique(['paper_id', 'student_id', 'attempt_no'], 'tagore_cbt_attempt_number_unique');
            $table->index(['paper_id', 'status', 'score'], 'tagore_cbt_attempt_results_idx');
        });

        Schema::create('tagore_cbt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('tagore_cbt_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('tagore_cbt_questions')->restrictOnDelete();
            $table->json('answer_json')->nullable();
            $table->boolean('is_marked_for_review')->default(false);
            $table->boolean('is_visited')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('tagore_cbt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->nullable()->constrained('tagore_cbt_papers')->nullOnDelete();
            $table->foreignId('attempt_id')->nullable()->constrained('tagore_cbt_attempts')->nullOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('event', 40);
            $table->json('details_json')->nullable();
            $table->timestamps();
            $table->index(['paper_id', 'created_at']);
            $table->index(['attempt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_cbt_events');
        Schema::dropIfExists('tagore_cbt_answers');
        Schema::dropIfExists('tagore_cbt_attempts');
        Schema::dropIfExists('tagore_cbt_paper_questions');
        Schema::dropIfExists('tagore_cbt_sections');
        Schema::dropIfExists('tagore_cbt_papers');
        Schema::dropIfExists('tagore_cbt_questions');
    }
};
