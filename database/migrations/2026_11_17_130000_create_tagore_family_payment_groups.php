<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_family_payment_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('parent_user_id');
            $table->foreign('parent_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('status', 30)->default('created');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['parent_user_id', 'status'], 'tagore_family_payment_parent_status_idx');
        });

        Schema::table('tagore_payment_orders', function (Blueprint $table) {
            $table->foreignId('family_payment_group_id')->nullable()
                ->after('parent_user_id')
                ->constrained('tagore_family_payment_groups')
                ->nullOnDelete();
            $table->index(['family_payment_group_id', 'status'], 'tagore_family_order_group_status_idx');
        });

        Schema::table('tagore_payments', function (Blueprint $table) {
            $table->foreignId('family_payment_group_id')->nullable()
                ->after('parent_user_id')
                ->constrained('tagore_family_payment_groups')
                ->nullOnDelete();
            $table->index(['family_payment_group_id', 'status'], 'tagore_family_payment_group_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_payments', function (Blueprint $table) {
            $table->dropIndex('tagore_family_payment_group_status_idx');
            $table->dropConstrainedForeignId('family_payment_group_id');
        });
        Schema::table('tagore_payment_orders', function (Blueprint $table) {
            $table->dropIndex('tagore_family_order_group_status_idx');
            $table->dropConstrainedForeignId('family_payment_group_id');
        });
        Schema::dropIfExists('tagore_family_payment_groups');
    }
};
