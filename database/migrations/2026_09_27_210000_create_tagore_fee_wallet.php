<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_fee_wallets', function(Blueprint $table){
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedBigInteger('student_id'); $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('balance',12,2)->default(0);
            $table->timestamps();
            $table->unique(['institution_id','student_id']);
        });
        Schema::create('tagore_fee_wallet_transactions', function(Blueprint $table){
            $table->id();
            $table->foreignId('wallet_id')->constrained('tagore_fee_wallets')->cascadeOnDelete();
            $table->string('type',30);
            $table->decimal('amount',12,2);
            $table->decimal('balance_after',12,2);
            $table->string('reference_type',60)->nullable(); $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable(); $table->timestamps();
            $table->index(['wallet_id','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('tagore_fee_wallet_transactions'); Schema::dropIfExists('tagore_fee_wallets'); }
};
