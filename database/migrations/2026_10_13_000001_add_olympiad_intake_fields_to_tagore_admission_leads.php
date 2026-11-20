<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = 'tagore_admission_leads';
        // Older installations may already have some fields; add each missing field independently.
        // This also keeps fresh SQLite CI migrations aligned with the MySQL production schema.
        $columns = [
            'medium' => fn (Blueprint $table) => $table->string('medium', 20)->nullable(),
            'school_name' => fn (Blueprint $table) => $table->string('school_name', 190)->nullable(),
            'city' => fn (Blueprint $table) => $table->string('city', 120)->nullable(),
            'marketing_consent' => fn (Blueprint $table) => $table->boolean('marketing_consent')->default(false),
            'consented_at' => fn (Blueprint $table) => $table->dateTime('consented_at')->nullable(),
            'privacy_notice_version' => fn (Blueprint $table) => $table->string('privacy_notice_version', 40)->nullable(),
            'utm_source' => fn (Blueprint $table) => $table->string('utm_source', 100)->nullable(),
            'utm_medium' => fn (Blueprint $table) => $table->string('utm_medium', 100)->nullable(),
            'utm_content' => fn (Blueprint $table) => $table->string('utm_content', 150)->nullable(),
            'utm_term' => fn (Blueprint $table) => $table->string('utm_term', 150)->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn($tableName, $name)) {
                Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        // Keep intake metadata during rollback: legacy installations may have had these
        // columns before this migration, and dropping them could destroy live admissions data.
    }
};
