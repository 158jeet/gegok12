<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kobo_submissions', function (Blueprint $table) {
            $table->foreignId('tagore_admission_lead_id')->nullable()->after('synced_at')
                ->constrained('tagore_admission_leads')->nullOnDelete();
            $table->timestamp('imported_at')->nullable()->after('tagore_admission_lead_id');
            $table->text('import_error')->nullable()->after('imported_at');
            $table->index(['asset_uid', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::table('kobo_submissions', function (Blueprint $table) {
            $table->dropForeign(['tagore_admission_lead_id']);
            $table->dropIndex(['asset_uid', 'imported_at']);
            $table->dropColumn(['tagore_admission_lead_id', 'imported_at', 'import_error']);
        });
    }
};