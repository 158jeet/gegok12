<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_payment_order_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->constrained('tagore_payment_orders')->cascadeOnDelete();
            $table->foreignId('fee_obligation_id')->constrained('tagore_fee_obligations')->cascadeOnDelete();
            $table->foreignId('fee_installment_id')->nullable()->constrained('tagore_fee_installments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['payment_order_id', 'fee_obligation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_payment_order_allocations');
    }
};
