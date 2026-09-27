<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_fee_year_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->dateTime('closed_at');
            $table->unsignedInteger('closed_by')->nullable();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->string('archive_path', 500);
            $table->string('archive_sha256', 64);
            $table->unsignedBigInteger('student_count')->default(0);
            $table->unsignedBigInteger('payment_count')->default(0);
            $table->unsignedBigInteger('transaction_count')->default(0);
            $table->string('status', 30)->default('archived');
            $table->timestamps();
            $table->unique(['academic_year_id', 'institution_id']);
        });

        Schema::create('tagore_fee_editor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('fee_obligation_id')->nullable();
            $table->foreign('fee_obligation_id')->references('id')->on('tagore_fee_obligations')->nullOnDelete();
            $table->string('change_type', 50);
            $table->decimal('percentage', 8, 4)->nullable();
            $table->decimal('old_amount', 12, 2)->nullable();
            $table->decimal('new_amount', 12, 2)->nullable();
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->unsignedInteger('edited_by')->nullable();
            $table->foreign('edited_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('tagore_fee_receipt_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('tagore_payments')->cascadeOnDelete();
            $table->unsignedInteger('recipient_user_id')->nullable();
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('recipient_email', 190)->nullable();
            $table->string('channel', 30)->default('email');
            $table->string('status', 30)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_id', 'channel']);
        });

        Schema::create('tagore_fee_vault_access_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('owner_user_id');
            $table->foreign('owner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('archive_path', 500)->nullable();
            $table->timestamps();
            $table->index(['owner_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_fee_vault_access_events');
        Schema::dropIfExists('tagore_fee_receipt_deliveries');
        Schema::dropIfExists('tagore_fee_editor_events');
        Schema::dropIfExists('tagore_fee_year_closures');
    }
};
