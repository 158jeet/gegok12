<?php

use Illuminate\DatabaseMigrationsMigration;
use Illuminate\DatabaseSchemaBlueprint;
use Illuminate\SupportFacadesSchema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_task_template_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('tagore_task_templates')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->foreign('task_id')->references('id')->on('tagore_tasks')->nullOnDelete();
            $table->dateTime('scheduled_for');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 20)->default('running');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'scheduled_for'], 'tagore_tpl_run_unique');
            $table->index(['institution_id', 'status', 'created_at'], 'tagore_tpl_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_task_template_runs');
    }
};