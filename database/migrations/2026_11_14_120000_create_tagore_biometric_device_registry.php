<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->restrictOnDelete();
            $table->string('site_name', 120);
            $table->string('device_name', 120);
            $table->string('model', 60)->default('Realtime T304F');
            $table->string('serial_number', 120)->unique();
            $table->string('firmware_version', 80)->nullable();
            $table->string('connection_mode', 30)->default('realtime_api');
            $table->string('api_endpoint', 500)->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->unsignedSmallInteger('poll_interval_minutes')->default(5);
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->boolean('sync_enabled')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'sync_enabled']);
            $table->index(['site_name', 'sync_enabled']);
        });

        Schema::create('tagore_biometric_employee_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('tagore_biometric_devices')->restrictOnDelete();
            $table->string('device_user_id', 100);
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('users')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['device_id', 'device_user_id'], 'tagore_bio_map_device_user_unique');
            $table->index(['employee_id', 'active']);
        });

        Schema::create('tagore_biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('tagore_biometric_devices')->restrictOnDelete();
            $table->string('device_user_id', 100);
            $table->unsignedInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('users')->nullOnDelete();
            $table->string('vendor_event_id', 160)->nullable();
            $table->timestamp('punched_at');
            $table->string('verification_mode', 40)->nullable();
            $table->string('punch_state', 40)->nullable();
            $table->char('dedupe_hash', 64);
            $table->json('raw_payload')->nullable();
            $table->string('mapping_status', 24)->default('unmapped');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'dedupe_hash']);
            $table->index(['employee_id', 'punched_at']);
            $table->index(['device_id', 'punched_at']);
            $table->index(['mapping_status', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_biometric_punches');
        Schema::dropIfExists('tagore_biometric_employee_mappings');
        Schema::dropIfExists('tagore_biometric_devices');
    }
};
