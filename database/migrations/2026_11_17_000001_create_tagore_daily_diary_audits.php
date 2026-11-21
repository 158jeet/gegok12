<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_daily_diary_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('diary_id')->index();
            $table->unsignedBigInteger('institution_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->date('diary_date');
            $table->unsignedBigInteger('actor_id')->index();
            $table->string('actor_role', 16);
            $table->string('field_name', 40);
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'diary_date', 'created_at'], 'diary_audit_student_date_idx');
            $table->index(['institution_id', 'created_at'], 'diary_audit_institution_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_daily_diary_audits');
    }
};
