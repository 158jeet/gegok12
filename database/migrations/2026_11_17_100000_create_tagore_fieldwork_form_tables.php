<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('draft_fields');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('tagore_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('tagore_form_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('fields');
            $table->unsignedInteger('published_by');
            $table->foreign('published_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['template_id', 'version']);
        });

        Schema::create('tagore_form_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('tagore_form_templates')->restrictOnDelete();
            $table->foreignId('form_version_id')->constrained('tagore_form_versions')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('status', 20)->default('active');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('tagore_form_campaign_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('tagore_form_campaigns')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['campaign_id', 'user_id']);
        });

        Schema::create('tagore_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('tagore_form_campaigns')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('tagore_form_versions')->restrictOnDelete();
            $table->unsignedInteger('submitted_by');
            $table->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete();
            $table->unsignedBigInteger('admission_lead_id')->nullable();
            $table->foreign('admission_lead_id')->references('id')->on('tagore_admission_leads')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->json('payload');
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'campaign_id', 'status']);
            $table->index(['submitted_by', 'created_at']);
        });

        Schema::create('tagore_form_submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('tagore_form_submissions')->cascadeOnDelete();
            $table->unsignedInteger('actor_id');
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->string('event_type', 30);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_form_submission_events');
        Schema::dropIfExists('tagore_form_submissions');
        Schema::dropIfExists('tagore_form_campaign_assignments');
        Schema::dropIfExists('tagore_form_campaigns');
        Schema::dropIfExists('tagore_form_versions');
        Schema::dropIfExists('tagore_form_templates');
    }
};
