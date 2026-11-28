<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_payment_reconciliations', function (Blueprint $table) {
            if (!Schema::hasColumn('tagore_payment_reconciliations', 'refund_reference')) {
                $table->string('refund_reference', 160)->nullable();
            }
            if (!Schema::hasColumn('tagore_payment_reconciliations', 'refund_date')) {
                $table->date('refund_date')->nullable();
            }
            if (!Schema::hasColumn('tagore_payment_reconciliations', 'refund_notes')) {
                $table->text('refund_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagore_payment_reconciliations', function (Blueprint $table) {
            $columns = [];
            foreach (['refund_reference', 'refund_date', 'refund_notes'] as $column) {
                if (Schema::hasColumn('tagore_payment_reconciliations', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
