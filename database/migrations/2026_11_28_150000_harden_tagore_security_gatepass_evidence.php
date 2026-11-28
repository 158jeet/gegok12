<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tagore_security_gatepasses', 'verified_at')) {
            Schema::table('tagore_security_gatepasses', function (Blueprint $table) {
                $table->timestamp('verified_at')->nullable();
            });
        }

        if (!Schema::hasColumn('tagore_security_gatepasses', 'verified_by')) {
            Schema::table('tagore_security_gatepasses', function (Blueprint $table) {
                $table->unsignedInteger('verified_by')->nullable();
                $table->foreign('verified_by', 'tag_sec_gatepass_verified_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        Schema::table('tagore_security_visitors', function (Blueprint $table) {
            $table->dropForeign('tagore_security_visitors_institution_id_foreign');
            $table->foreign('institution_id', 'tagore_security_visitors_institution_id_foreign')
                ->references('id')->on('tagore_institutions')->restrictOnDelete();
        });

        Schema::table('tagore_security_gatepasses', function (Blueprint $table) {
            $table->dropForeign('tagore_security_gatepasses_institution_id_foreign');
            $table->dropForeign('tagore_security_gatepasses_student_id_foreign');
            $table->foreign('institution_id', 'tagore_security_gatepasses_institution_id_foreign')
                ->references('id')->on('tagore_institutions')->restrictOnDelete();
            $table->foreign('student_id', 'tagore_security_gatepasses_student_id_foreign')
                ->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('tagore_security_events', function (Blueprint $table) {
            $table->dropForeign('tagore_security_events_institution_id_foreign');
            $table->foreign('institution_id', 'tagore_security_events_institution_id_foreign')
                ->references('id')->on('tagore_institutions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tagore_security_events', function (Blueprint $table) {
            $table->dropForeign('tagore_security_events_institution_id_foreign');
            $table->foreign('institution_id', 'tagore_security_events_institution_id_foreign')
                ->references('id')->on('tagore_institutions')->cascadeOnDelete();
        });

        Schema::table('tagore_security_gatepasses', function (Blueprint $table) {
            $table->dropForeign('tagore_security_gatepasses_institution_id_foreign');
            $table->dropForeign('tagore_security_gatepasses_student_id_foreign');
            $table->foreign('institution_id', 'tagore_security_gatepasses_institution_id_foreign')
                ->references('id')->on('tagore_institutions')->cascadeOnDelete();
            $table->foreign('student_id', 'tagore_security_gatepasses_student_id_foreign')
                ->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('tagore_security_visitors', function (Blueprint $table) {
            $table->dropForeign('tagore_security_visitors_institution_id_foreign');
            $table->foreign('institution_id', 'tagore_security_visitors_institution_id_foreign')
                ->references('id')->on('tagore_institutions')->cascadeOnDelete();
        });

        if (Schema::hasColumn('tagore_security_gatepasses', 'verified_by')) {
            Schema::table('tagore_security_gatepasses', function (Blueprint $table) {
                $table->dropForeign('tag_sec_gatepass_verified_by_fk');
                $table->dropColumn('verified_by');
            });
        }
        if (Schema::hasColumn('tagore_security_gatepasses', 'verified_at')) {
            Schema::table('tagore_security_gatepasses', function (Blueprint $table) {
                $table->dropColumn('verified_at');
            });
        }
    }
};
