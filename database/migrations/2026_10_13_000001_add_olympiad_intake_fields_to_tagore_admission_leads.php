<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = 'tagore_admission_leads';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Several intake fields already exist in older school schemas; only add missing UTM fields.
            if (!Schema::hasColumn($tableName, 'utm_medium')) {
                $table->string('utm_medium', 100)->nullable();
            }
            if (!Schema::hasColumn($tableName, 'utm_content')) {
                $table->string('utm_content', 150)->nullable();
            }
            if (!Schema::hasColumn($tableName, 'utm_term')) {
                $table->string('utm_term', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        $tableName = 'tagore_admission_leads';
        $columns = array_values(array_filter(
            ['utm_medium', 'utm_content', 'utm_term'],
            fn (string $column): bool => Schema::hasColumn($tableName, $column)
        ));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
