<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_admission_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('lead_no', 40);
            $table->string('student_name');
            $table->string('parent_name')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('alternate_mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('class_name', 80)->nullable();
            $table->string('source', 60)->nullable();
            $table->string('status', 30)->default('new');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['institution_id', 'lead_no']);
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'next_follow_up_at']);
            $table->index(['institution_id', 'mobile']);
            $table->index(['institution_id', 'assigned_to', 'status']);
        });

        Schema::create('tagore_admission_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('tagore_admission_leads')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('outcome', 80)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_admission_activities');
        Schema::dropIfExists('tagore_admission_leads');
    }
};
