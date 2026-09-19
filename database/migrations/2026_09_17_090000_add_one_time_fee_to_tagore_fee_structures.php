<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_fee_structures', function (Blueprint $table) {
            $table->decimal('one_time_amount', 12, 2)->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('tagore_fee_structures', function (Blueprint $table) {
            $table->dropColumn('one_time_amount');
        });
    }
};
