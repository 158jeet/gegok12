<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_operational_audit_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('tagore_institutions')->nullOnDelete();
            $table->string('template_code', 100);
            $table->unsignedInteger('version')->default(1);
            $table->string('title');
            $table->string('role_code', 60);
            $table->string('scope_type', 40)->default('INSTITUTION');
            $table->string('cadence', 30)->default('daily');
            $table->json('schedule_json')->nullable();
            $table->json('controls_json');
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'role_code', 'status'], 'tagore_audit_tpl_scope_idx');
            $table->unique(['template_code', 'institution_id', 'version'], 'tagore_audit_tpl_version_idx');
        });

        Schema::create('tagore_operational_audit_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('tagore_operational_audit_templates')->restrictOnDelete();
            $table->unsignedInteger('template_version');
            $table->string('template_code', 100);
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('assignee_user_id');
            $table->foreign('assignee_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->string('assigned_role_code', 60);
            $table->string('scope_type', 40);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('scheduled_for');
            $table->timestamp('due_at');
            $table->string('status', 24)->default('pending');
            $table->string('verification_status', 24)->default('unverified');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->json('template_snapshot_json');
            $table->timestamps();
            $table->index(['institution_id', 'scheduled_for', 'status'], 'tagore_audit_instance_daily_idx');
            $table->index(['assignee_user_id', 'due_at', 'status'], 'tagore_audit_instance_assignee_idx');
        });

        Schema::create('tagore_operational_audit_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('tagore_operational_audit_instances')->cascadeOnDelete();
            $table->string('control_key', 100);
            $table->json('value_json')->nullable();
            $table->string('evidence_class', 1)->default('C');
            $table->string('verification_status', 24)->default('unverified');
            $table->string('source_table', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('submitted_by');
            $table->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['instance_id', 'control_key'], 'tagore_audit_response_control_idx');
        });

        Schema::create('tagore_operational_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('tagore_operational_audit_instances')->cascadeOnDelete();
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('payload_json')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['instance_id', 'occurred_at'], 'tagore_audit_event_timeline_idx');
        });

        Schema::create('tagore_operational_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('tagore_operational_audit_instances')->cascadeOnDelete();
            $table->foreignId('response_id')->nullable()->constrained('tagore_operational_audit_responses')->nullOnDelete();
            $table->unsignedInteger('uploaded_by');
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->string('storage_disk', 40)->nullable();
            $table->text('storage_path')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('verification_status', 24)->default('unverified');
            $table->unsignedInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['instance_id', 'verification_status'], 'tagore_audit_evidence_status_idx');
            $table->index('sha256');
        });

        Schema::create('tagore_operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('tagore_institutions')->nullOnDelete();
            $table->string('alert_key', 100)->unique();
            $table->string('severity', 20);
            $table->string('category', 60);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('triggered_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'status', 'severity'], 'tagore_alert_scope_status_idx');
            $table->index(['status', 'due_at'], 'tagore_alert_escalation_idx');
        });

        Schema::create('tagore_operational_alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('tagore_operational_alerts')->cascadeOnDelete();
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('payload_json')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['alert_id', 'occurred_at'], 'tagore_alert_event_timeline_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'tagore_operational_alert_events',
            'tagore_operational_alerts',
            'tagore_operational_evidence',
            'tagore_operational_audit_events',
            'tagore_operational_audit_responses',
            'tagore_operational_audit_instances',
            'tagore_operational_audit_templates',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
