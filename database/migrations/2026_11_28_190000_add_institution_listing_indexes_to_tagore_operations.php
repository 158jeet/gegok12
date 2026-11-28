<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    private const MODULES = [
        'payroll', 'inventory', 'expense', 'transport', 'hostel', 'alumni',
        'visitor', 'gatepass', 'survey', 'notification', 'document', 'course',
        'content', 'report', 'social', 'website', 'store', 'fee_plan',
        'integration', 'automation', 'creative',
    ];

    public function up(): void
    {
        foreach (self::MODULES as $module) {
            $tableName = 'tagore_'.$module.'_records';
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($module): void {
                // Operations lists are institution-filtered and ordered by newest ID.
                // This composite index avoids a full scan/filesort as records grow.
                $table->index(['institution_id', 'id'], 'tg_'.$module.'_scope_id_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (self::MODULES as $module) {
            $tableName = 'tagore_'.$module.'_records';
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($module): void {
                $table->dropIndex('tg_'.$module.'_scope_id_idx');
            });
        }
    }
};
