<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_fee_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('academic_year_id')->nullable();

            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->string('source_name', 255);
            $table->string('source_type', 30)->default('xlsx');
            $table->string('import_type', 40)->default('opening_balance');
            $table->string('status', 30)->default('uploaded');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('mapping_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tagore_fee_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('tagore_fee_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('external_student_key', 160)->nullable();
            $table->unsignedInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('users')->nullOnDelete();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('concession_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->decimal('opening_balance', 12, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_fee_import_rows');
        Schema::dropIfExists('tagore_fee_import_batches');
    }
};
