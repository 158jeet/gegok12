<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->string('lost_reason', 120)->nullable()->after('status');
            $table->dateTime('converted_at')->nullable()->after('lost_reason');
            $table->dateTime('last_contacted_at')->nullable()->after('converted_at');
            $table->index(['institution_id', 'status', 'converted_at'], 'tag_adm_conv_idx');
            $table->index(['institution_id', 'assigned_to', 'last_contacted_at'], 'tag_adm_contact_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->dropIndex('tag_adm_conv_idx');
            $table->dropIndex('tag_adm_contact_idx');
            $table->dropColumn(['lost_reason', 'converted_at', 'last_contacted_at']);
        });
    }
};