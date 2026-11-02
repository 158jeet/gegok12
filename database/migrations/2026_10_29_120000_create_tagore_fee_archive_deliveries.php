<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tagore_fee_archive_deliveries')) {
            return;
        }

        Schema::create('tagore_fee_archive_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closure_id')->constrained('tagore_fee_year_closures')->cascadeOnDelete();
            $table->unsignedInteger('recipient_user_id')->nullable();
            $table->foreign('recipient_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('recipient_email', 190)->nullable();
            $table->string('status', 30)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique('closure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_fee_archive_deliveries');
    }
};
