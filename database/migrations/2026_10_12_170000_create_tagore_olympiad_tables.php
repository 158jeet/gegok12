<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_olympiads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->json('target_classes');
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->dateTime('exam_starts_at')->nullable();
            $table->dateTime('exam_ends_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->decimal('max_marks', 8, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'status', 'exam_starts_at']);
        });

        Schema::create('tagore_olympiad_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olympiad_id')->constrained('tagore_olympiads')->cascadeOnDelete();
            $table->text('question_text');
            $table->json('options');
            $table->string('correct_option', 20);
            $table->decimal('marks', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('source', 40)->default('manual');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->index(['olympiad_id', 'approved_at', 'sort_order'], 'tagore_oly_q_approved_idx');
        });
        Schema::create('tagore_olympiad_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olympiad_id')->constrained('tagore_olympiads')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('student_user_id');
            $table->foreign('student_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('registration_no', 60)->unique();
            $table->string('status', 24)->default('registered');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->json('answers')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['olympiad_id', 'student_user_id'], 'tagore_oly_reg_event_student_uq');
            $table->index(['institution_id', 'status'], 'tagore_oly_reg_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_olympiad_registrations');
        Schema::dropIfExists('tagore_olympiad_questions');
        Schema::dropIfExists('tagore_olympiads');
    }
};