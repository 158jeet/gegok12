<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tagore_fee_opening_balances')) {
            Schema::create('tagore_fee_opening_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('student_id');
                $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->unsignedInteger('academic_year_id')->nullable();
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
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
        }

        if (!Schema::hasTable('tagore_transport_routes')) {
            Schema::create('tagore_transport_routes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->unsignedInteger('academic_year_id')->nullable();
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
                $table->string('route_code', 80);
                $table->string('route_name');
                $table->decimal('annual_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->unique(['institution_id', 'academic_year_id', 'route_code'], 'tagore_transport_route_unique');
            });
        }

        if (!Schema::hasTable('tagore_student_transport')) {
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
                $table->unique(['student_id', 'route_id'], 'tagore_student_transport_unique');
                $table->index(['student_id', 'status'], 'tagore_student_transport_student_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_student_transport');
        Schema::dropIfExists('tagore_transport_routes');
        Schema::dropIfExists('tagore_fee_opening_balances');
    }
};
