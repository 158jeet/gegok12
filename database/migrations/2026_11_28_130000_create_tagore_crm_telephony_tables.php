<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_crm_telephony_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('provider', 30)->default('exotel');
            $table->string('account_sid', 120);
            $table->text('credentials');
            $table->string('caller_id', 40);
            $table->string('webhook_token_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'is_active']);
        });

        Schema::create('tagore_crm_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->foreignId('telephony_account_id')->constrained('tagore_crm_telephony_accounts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('tagore_admission_leads')->nullOnDelete();
            $table->string('provider_call_id', 150)->nullable();
            $table->string('direction', 12)->default('outbound');
            $table->string('from_number', 40)->nullable();
            $table->string('to_number', 40)->nullable();
            $table->string('status', 30)->default('queued');
            $table->string('disposition', 60)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->text('recording_url')->nullable();
            $table->json('provider_payload')->nullable();
            $table->unsignedInteger('initiated_by')->nullable();
            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['telephony_account_id', 'provider_call_id'], 'tagore_crm_call_provider_unique');
            $table->index(['institution_id', 'status', 'created_at'], 'tagore_crm_call_status_idx');
            $table->index(['lead_id', 'created_at'], 'tagore_crm_call_lead_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_crm_calls');
        Schema::dropIfExists('tagore_crm_telephony_accounts');
    }
};
