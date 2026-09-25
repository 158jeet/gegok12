<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('tagore_departments')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('frequency', 20);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('run_at')->default('09:00:00');
            $table->unsignedInteger('due_after_minutes')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_generated_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['institution_id', 'active', 'next_run_at'], 'tagore_task_tpl_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_task_templates');
    }
};
