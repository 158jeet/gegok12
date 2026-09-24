<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tagore_institutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagore_group_id')->constrained('tagore_groups')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('display_name');
            $table->string('type', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->json('settings_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tagore_group_id', 'code']);
            $table->unique('school_id');
        });

        Schema::create('tagore_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('tagore_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 60);
            $table->string('action', 60);
            $table->string('code', 120)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['module', 'action']);
        });

        Schema::create('tagore_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('tagore_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('tagore_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('tagore_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('tagore_roles')->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('tagore_institutions')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('tagore_user_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('scope_type', 40);
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('permission_id')->nullable()->constrained('tagore_permissions')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'scope_type', 'scope_id']);
            $table->unique(['user_id', 'scope_type', 'scope_id', 'permission_id'], 'tagore_user_scope_unique');
        });

        Schema::create('tagore_parent_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('parent_user_id');
            $table->foreign('parent_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('relationship', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_guardian')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['parent_user_id', 'student_id']);
        });

        Schema::create('tagore_fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('frequency', 30)->default('annual');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tagore_fee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained('tagore_fee_structures')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('category', 60)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['fee_structure_id', 'code']);
        });

        Schema::create('tagore_fee_obligations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained('tagore_fee_structures')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('concession_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['student_id', 'institution_id', 'status']);
        });

        Schema::create('tagore_fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_obligation_id')->constrained('tagore_fee_obligations')->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->string('name');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['fee_obligation_id', 'installment_no']);
        });

        Schema::create('tagore_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('parent_user_id')->nullable();
            $table->foreign('parent_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('purpose', 120)->nullable();
            $table->string('status', 30)->default('created');
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_order_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tagore_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->nullable()->constrained('tagore_payment_orders')->nullOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('parent_user_id')->nullable();
            $table->foreign('parent_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('status', 30)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        Schema::create('tagore_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('tagore_payments')->nullOnDelete();
            $table->string('gateway', 40);
            $table->string('event_id', 160);
            $table->string('event_type', 100);
            $table->json('payload_json')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 30)->default('received');
            $table->timestamps();
            $table->unique(['gateway', 'event_id']);
        });

        Schema::create('tagore_financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('parent_user_id')->nullable();
            $table->foreign('parent_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('transaction_type', 40);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('description')->nullable();
            $table->dateTime('transaction_date');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'student_id', 'transaction_date'], 'tagore_fin_txn_scope_date_idx');
        });

        Schema::create('tagore_payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('tagore_payments')->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->string('settlement_id', 160)->nullable();
            $table->decimal('settlement_amount', 12, 2)->nullable();
            $table->date('settlement_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('reconciled_by')->nullable();
            $table->foreign('reconciled_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tagore_feedback_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('tagore_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('submitted_by');
            $table->foreign('submitted_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('tagore_feedback_categories')->nullOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->timestamps();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('tagore_feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('tagore_feedback')->cascadeOnDelete();
            $table->unsignedInteger('responded_by');
            $table->foreign('responded_by')->references('id')->on('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('tagore_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('tagore_institutions')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 120)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['institution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $tables = [
            'tagore_audit_events', 'tagore_feedback_responses', 'tagore_feedback',
            'tagore_feedback_categories', 'tagore_payment_reconciliations',
            'tagore_financial_transactions', 'tagore_payment_events', 'tagore_payments',
            'tagore_payment_orders', 'tagore_fee_installments', 'tagore_fee_obligations',
            'tagore_fee_components', 'tagore_fee_structures', 'tagore_parent_students',
            'tagore_user_scopes', 'tagore_user_roles', 'tagore_role_permissions',
            'tagore_permissions', 'tagore_roles', 'tagore_institutions', 'tagore_groups'
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
