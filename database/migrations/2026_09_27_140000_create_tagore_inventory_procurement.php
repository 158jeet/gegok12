<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_inventory_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->string('gstin', 30)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->index(['institution_id','status']);
        });

        Schema::create('tagore_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 30)->default('pcs');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->foreignId('vendor_id')->nullable()->constrained('tagore_inventory_vendors')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->unique(['institution_id','sku']);
            $table->index(['institution_id','category','status']);
        });

        Schema::create('tagore_inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('tagore_inventory_vendors')->cascadeOnDelete();
            $table->string('po_no', 80)->unique();
            $table->date('order_date');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id','status']);
        });

        Schema::create('tagore_inventory_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('tagore_inventory_purchase_orders')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('tagore_inventory_items')->cascadeOnDelete();
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('tagore_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('tagore_inventory_items')->cascadeOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedInteger('performed_by')->nullable();
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['institution_id','inventory_item_id','created_at']);
        });

        Schema::create('tagore_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('asset_tag', 100);
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->string('department', 100)->nullable();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->unique(['institution_id','asset_tag']);
        });

        Schema::create('tagore_expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('department', 100)->nullable();
            $table->string('category', 100);
            $table->text('description');
            $table->decimal('amount', 14, 2);
            $table->string('vendor', 190)->nullable();
            $table->unsignedInteger('submitted_by')->nullable();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('approval_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id','status','expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_expense_claims');
        Schema::dropIfExists('tagore_assets');
        Schema::dropIfExists('tagore_inventory_movements');
        Schema::dropIfExists('tagore_inventory_purchase_order_items');
        Schema::dropIfExists('tagore_inventory_purchase_orders');
        Schema::dropIfExists('tagore_inventory_items');
        Schema::dropIfExists('tagore_inventory_vendors');
    }
};
