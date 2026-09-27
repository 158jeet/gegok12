<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tagore_fee_import_rows')) {
            throw new RuntimeException('tagore_fee_import_rows must exist before extending the legacy fee import schema.');
        }

        Schema::table('tagore_fee_import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('tagore_fee_import_rows', 'row_type')) {
                $table->string('row_type', 50)->default('opening_balance')->after('batch_id');
            }
            if (!Schema::hasColumn('tagore_fee_import_rows', 'source_hash')) {
                $table->string('source_hash', 64)->nullable()->after('row_type');
            }
            if (!Schema::hasColumn('tagore_fee_import_rows', 'target_type')) {
                $table->string('target_type', 50)->nullable()->after('source_hash');
            }
            if (!Schema::hasColumn('tagore_fee_import_rows', 'target_id')) {
                $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            }
        });

        // tagore_transport_routes is owned by the fee-ledger migration (2026_09_15).
        // This migration only adds the legacy-import assignment projection.
        if (!Schema::hasTable('tagore_transport_assignments')) {
            Schema::create('tagore_transport_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('student_id');
                $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreignId('route_id')->constrained('tagore_transport_routes')->cascadeOnDelete();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->unsignedInteger('academic_year_id')->nullable();
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
                $table->decimal('annual_fee', 12, 2)->default(0);
                $table->string('status', 20)->default('active');
                $table->string('source_hash', 64)->nullable();
                $table->timestamps();
                $table->unique(['student_id', 'academic_year_id'], 'tagore_transport_assignment_unique');
                $table->index(['institution_id', 'academic_year_id'], 'tagore_transport_assignment_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_transport_assignments');

        if (Schema::hasTable('tagore_fee_import_rows')) {
            Schema::table('tagore_fee_import_rows', function (Blueprint $table) {
                foreach (['row_type', 'source_hash', 'target_type', 'target_id'] as $column) {
                    if (Schema::hasColumn('tagore_fee_import_rows', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
