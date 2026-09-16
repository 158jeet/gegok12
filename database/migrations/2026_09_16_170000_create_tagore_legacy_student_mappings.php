<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_legacy_student_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('source_key', 160);
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_system', 60)->default('legacy_erp');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'source_system', 'source_key']);
            $table->index(['institution_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_legacy_student_mappings');
    }
};
