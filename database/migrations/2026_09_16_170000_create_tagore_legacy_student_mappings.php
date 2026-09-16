<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagore_legacy_student_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('source_system', 50)->default('legacy_erp');
            $table->string('source_key', 150);
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'source_system', 'source_key']);
            $table->unique(['institution_id', 'source_system', 'student_id']);
            $table->index(['source_system', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_legacy_student_mappings');
    }
};
