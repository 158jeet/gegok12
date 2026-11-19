<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->string('campaign', 100)->nullable()->after('source');
            $table->index(['institution_id', 'source', 'status']);
            $table->index(['institution_id', 'campaign', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'source', 'status']);
            $table->dropIndex(['institution_id', 'campaign', 'status']);
            $table->dropColumn('campaign');
        });
    }
};