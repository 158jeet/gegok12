<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_payment_reconciliations', function (Blueprint $table) {
            $table->unique('payment_id', 'tagore_payment_reconciliation_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_payment_reconciliations', function (Blueprint $table) {
            $table->dropUnique('tagore_payment_reconciliation_payment_unique');
        });
    }
};
