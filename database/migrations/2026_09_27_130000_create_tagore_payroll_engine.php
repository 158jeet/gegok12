<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('month', 7);
            $table->string('status', 30)->default('draft');
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('deduction_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['institution_id','month']);
            $table->index(['institution_id','status']);
        });

        Schema::create('tagore_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('tagore_payroll_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('pf_amount', 14, 2)->default(0);
            $table->decimal('esi_amount', 14, 2)->default(0);
            $table->decimal('tds_amount', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->json('earnings_json')->nullable();
            $table->json('deductions_json')->nullable();
            $table->string('payslip_no', 80)->unique();
            $table->string('status', 30)->default('processed');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_payroll_items');
        Schema::dropIfExists('tagore_payroll_runs');
    }
};
