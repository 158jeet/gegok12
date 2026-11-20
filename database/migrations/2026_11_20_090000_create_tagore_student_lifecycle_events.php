<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_student_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->unsignedInteger('source_academic_year_id');
            $table->unsignedInteger('target_academic_year_id');
            $table->unsignedInteger('source_student_academic_id');
            $table->unsignedInteger('target_student_academic_id');
            $table->unsignedInteger('target_standard_link_id');
            $table->unsignedInteger('performed_by')->nullable();
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['student_id', 'source_academic_year_id', 'target_academic_year_id', 'event_type'],
                'tagore_student_lifecycle_once'
            );
            $table->index(['institution_id', 'target_academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_student_lifecycle_events');
    }
};
