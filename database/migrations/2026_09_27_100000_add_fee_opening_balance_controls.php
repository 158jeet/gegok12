<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_fee_obligations', function (Blueprint $table) {
            $table->boolean('is_opening_balance')->default(false)->after('status');
            $table->decimal('opening_balance_amount', 12, 2)->nullable()->after('is_opening_balance');
            $table->string('record_source', 40)->default('erp')->after('opening_balance_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_fee_obligations', function (Blueprint $table) {
            $table->dropColumn(['is_opening_balance','opening_balance_amount','record_source']);
        });
    }
};
