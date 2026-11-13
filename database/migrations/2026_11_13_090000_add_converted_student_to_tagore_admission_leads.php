<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->unsignedInteger('converted_student_id')->nullable()->after('converted_at');
            $table->foreign('converted_student_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['institution_id', 'converted_student_id'], 'tag_adm_student_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->dropIndex('tag_adm_student_idx');
            $table->dropForeign(['converted_student_id']);
            $table->dropColumn('converted_student_id');
        });
    }
};
