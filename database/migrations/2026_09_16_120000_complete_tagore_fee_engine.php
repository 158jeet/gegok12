<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tagore_fee_obligation_items')) {
            Schema::create('tagore_fee_obligation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_obligation_id')->constrained('tagore_fee_obligations')->cascadeOnDelete();
                $table->foreignId('fee_component_id')->nullable()->constrained('tagore_fee_components')->nullOnDelete();
                $table->string('fee_head');
                $table->string('code', 60)->nullable();
                $table->decimal('gross_amount', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('concession_amount', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('outstanding_amount', 12, 2)->default(0);
                $table->json('metadata_json')->nullable();
                $table->timestamps();
                $table->index(['fee_obligation_id', 'fee_component_id']);
            });
        }

        if (!Schema::hasTable('tagore_fee_concessions')) {
            Schema::create('tagore_fee_concessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->foreignId('fee_obligation_id')->nullable()->constrained('tagore_fee_obligations')->nullOnDelete();
                $table->string('type', 30)->default('fixed');
                $table->decimal('value', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('reason', 255)->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('status', 30)->default('pending');
                $table->timestamps();
                $table->index(['student_id', 'institution_id', 'status']);
            });
        }

        if (!Schema::hasTable('tagore_payment_allocations')) {
            Schema::create('tagore_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('tagore_payments')->cascadeOnDelete();
                $table->foreignId('fee_obligation_id')->constrained('tagore_fee_obligations')->cascadeOnDelete();
                $table->foreignId('fee_installment_id')->nullable()->constrained('tagore_fee_installments')->nullOnDelete();
                $table->foreignId('fee_obligation_item_id')->nullable()->constrained('tagore_fee_obligation_items')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamps();
                $table->index(['payment_id', 'fee_obligation_id']);
            });
        }

        Schema::table('tagore_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('tagore_payments', 'receipt_no')) {
                $table->string('receipt_no', 80)->nullable()->index();
            }
            if (!Schema::hasColumn('tagore_payments', 'payment_mode')) {
                $table->string('payment_mode', 30)->nullable();
            }
            if (!Schema::hasColumn('tagore_payments', 'reference_number')) {
                $table->string('reference_number', 160)->nullable();
            }
            if (!Schema::hasColumn('tagore_payments', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagore_payments', function (Blueprint $table) {
            foreach (['receipt_no', 'payment_mode', 'reference_number', 'notes'] as $column) {
                if (Schema::hasColumn('tagore_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('tagore_payment_allocations');
        Schema::dropIfExists('tagore_fee_concessions');
        Schema::dropIfExists('tagore_fee_obligation_items');
    }
};
