<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kobo_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('asset_uid', 100);
            $table->string('submission_uid', 100)->nullable();
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('device_id')->nullable();
            $table->string('submitted_by')->nullable();
            $table->json('data');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_uid', 'submission_uid']);
            $table->index(['asset_uid', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kobo_submissions');
    }
};