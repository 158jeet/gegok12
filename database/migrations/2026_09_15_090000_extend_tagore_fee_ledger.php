<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_fee_obligation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_obligation_id')->constrained('tagore_fee_obligations')->cascadeOnDelete();
            $table->foreignId('fee_component_id')->nullable()->constrained('tagore_fee_components')->nullOnDelete();
            $table->string('fee_head', 100);
            $table->string('code', 60)->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('concession_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['fee_obligation_id', 'fee_head']);
        });

        Schema::create('tagore_fee_concessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('fee_obligation_id')->nullable()->constrained('tagore_fee_obligations')->nullOnDelete();
            $table->foreignId('fee_component_id')->nullable()->constrained('tagore_fee_components')->nullOnDelete();
            $table->string('concession_type', 40)->default('fixed');
            $table->string('name', 120);
            $table->decimal('percentage', 7, 3)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('approved');
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status']);
        });

        Schema::create('tagore_transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('route_code', 80);
            $table->string('route_name');
            $table->decimal('annual_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['institution_id', 'academic_year_id', 'route_code'], 'tagore_transport_route_unique');
        });

        Schema::create('tagore_student_transport', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('tagore_transport_routes')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('annual_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['student_id', 'route_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('tagore_fee_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('received_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->string('source', 80)->default('legacy_erp');
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('imported_by')->nullable();
            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'institution_id', 'academic_year_id'], 'tagore_opening_balance_unique');
        });

        Schema::create('tagore_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('tagore_payments')->cascadeOnDelete();
            $table->foreignId('fee_obligation_id')->constrained('tagore_fee_obligations')->cascadeOnDelete();
            $table->foreignId('fee_installment_id')->nullable()->constrained('tagore_fee_installments')->nullOnDelete();
            $table->foreignId('fee_obligation_item_id')->nullable()->constrained('tagore_fee_obligation_items')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['fee_obligation_id', 'payment_id']);
        });

        Schema::table('tagore_payments', function (Blueprint $table) {
            $table->string('receipt_no', 80)->nullable()->unique()->after('id');
            $table->string('payment_mode', 40)->nullable()->after('currency');
            $table->string('reference_number', 160)->nullable()->after('gateway_payment_id');
            $table->text('notes')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_no']);
            $table->dropColumn(['receipt_no', 'payment_mode', 'reference_number', 'notes']);
        });
        Schema::dropIfExists('tagore_payment_allocations');
        Schema::dropIfExists('tagore_fee_opening_balances');
        Schema::dropIfExists('tagore_student_transport');
        Schema::dropIfExists('tagore_transport_routes');
        Schema::dropIfExists('tagore_fee_concessions');
        Schema::dropIfExists('tagore_fee_obligation_items');
    }
};
