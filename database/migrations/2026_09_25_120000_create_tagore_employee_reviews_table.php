<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_employee_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('manager_id');
            $table->foreign('manager_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tagore_tasks')->nullOnDelete();
            $table->string('review_type', 40)->default('follow_up');
            $table->string('outcome', 40)->default('note');
            $table->text('notes');
            $table->boolean('action_required')->default(false);
            $table->dateTime('follow_up_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->index(['institution_id', 'completed_at'], 'tagore_reviews_followup_status_idx');
            $table->timestamps();

            $table->index(['institution_id', 'employee_id', 'created_at'], 'tagore_reviews_inst_emp_created_idx');
            $table->index(['manager_id', 'created_at'], 'tagore_reviews_manager_created_idx');
            $table->index(['task_id', 'created_at'], 'tagore_reviews_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_employee_reviews');
    }
};
