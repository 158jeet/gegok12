<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_fee_import_rows', function (Blueprint $table) {
            $table->string('row_type', 50)->default('opening_balance')->after('batch_id');
            $table->string('source_hash', 64)->nullable()->after('row_type');
            $table->string('target_type', 50)->nullable()->after('source_hash');
            $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            $table->index(['batch_id', 'row_type', 'status']);
            $table->index('source_hash');
        });

        Schema::create('tagore_transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('name');
            $table->string('code', 100);
            $table->decimal('annual_fee', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->string('source_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'academic_year_id', 'code'], 'tagore_transport_route_unique');
            $table->index(['institution_id', 'academic_year_id']);
        });

        Schema::create('tagore_transport_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('tagore_transport_routes')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->decimal('annual_fee', 12, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->string('source_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id'], 'tagore_transport_assignment_unique');
            $table->index(['institution_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_transport_assignments');
        Schema::dropIfExists('tagore_transport_routes');
        Schema::table('tagore_fee_import_rows', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'row_type', 'status']);
            $table->dropIndex(['source_hash']);
            $table->dropColumn(['row_type', 'source_hash', 'target_type', 'target_id']);
        });
    }
};
