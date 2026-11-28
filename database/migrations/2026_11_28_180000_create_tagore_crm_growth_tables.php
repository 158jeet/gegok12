<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('tagore_admission_leads')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->string('name', 180);
            $table->string('opportunity_type', 40)->default('admission');
            $table->string('stage', 30)->default('qualification');
            $table->string('status', 20)->default('open');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(20);
            $table->date('expected_close_date')->nullable();
            $table->string('source', 60)->nullable();
            $table->string('campaign', 100)->nullable();
            $table->string('lost_reason', 180)->nullable();
            $table->unsignedInteger('converted_student_id')->nullable();
            $table->foreign('converted_student_id')->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['institution_id', 'stage', 'status'], 'tagore_crm_opp_pipeline_idx');
            $table->index(['institution_id', 'assigned_to', 'status'], 'tagore_crm_opp_owner_idx');
            $table->index(['institution_id', 'expected_close_date'], 'tagore_crm_opp_close_idx');
            $table->index(['lead_id', 'status'], 'tagore_crm_opp_lead_idx');
        });

        Schema::create('tagore_crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('campaign_code', 100);
            $table->string('channel', 40);
            $table->string('status', 24)->default('draft');
            $table->string('objective', 120)->nullable();
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('spend', 12, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('landing_url', 500)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['institution_id', 'campaign_code'], 'tagore_crm_campaign_code_unique');
            $table->index(['institution_id', 'status', 'starts_on'], 'tagore_crm_campaign_status_idx');
        });

        Schema::create('tagore_crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('tagore_admission_leads')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('tagore_crm_opportunities')->nullOnDelete();
            $table->foreignId('call_id')->nullable()->unique()->constrained('tagore_crm_calls')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('task_type', 30)->default('follow_up');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 24)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome', 180)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['institution_id', 'status', 'due_at'], 'tagore_crm_task_due_idx');
            $table->index(['institution_id', 'assigned_to', 'status'], 'tagore_crm_task_owner_idx');
        });

        Schema::create('tagore_crm_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('tagore_admission_leads')->nullOnDelete();
            $table->unsignedInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('parent_user_id')->nullable();
            $table->foreign('parent_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->string('subject', 180);
            $table->string('category', 50)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 24)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['institution_id', 'status', 'priority'], 'tagore_crm_case_status_idx');
            $table->index(['institution_id', 'assigned_to', 'status'], 'tagore_crm_case_owner_idx');
            $table->index(['student_id', 'status'], 'tagore_crm_case_student_idx');
            $table->index(['parent_user_id', 'status'], 'tagore_crm_case_parent_idx');
        });

        Schema::create('tagore_crm_telephony_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->restrictOnDelete();
            $table->foreignId('telephony_account_id')->constrained('tagore_crm_telephony_accounts')->restrictOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('agent_number', 40);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['telephony_account_id', 'user_id'], 'tagore_crm_agent_account_user_unique');
            $table->index(['institution_id', 'is_active'], 'tagore_crm_agent_institution_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_crm_telephony_agents');
        Schema::dropIfExists('tagore_crm_cases');
        Schema::dropIfExists('tagore_crm_tasks');
        Schema::dropIfExists('tagore_crm_campaigns');
        Schema::dropIfExists('tagore_crm_opportunities');
    }
};
