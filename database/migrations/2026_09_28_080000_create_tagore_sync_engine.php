<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tagore_sync_devices')) {
            Schema::create('tagore_sync_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('device_id', 120);
                $table->string('platform', 30)->nullable();
                $table->string('app_version', 40)->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'device_id'], 'tag_sync_device_user_idx');
                $table->index(['user_id', 'last_sync_at'], 'tag_sync_device_user_sync_idx');
            });
        }

        if (!Schema::hasTable('tagore_sync_events')) {
            Schema::create('tagore_sync_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('device_id', 120);
                $table->uuid('event_uuid');
                $table->string('entity_type', 80);
                $table->string('operation', 30);
                $table->json('payload');
                $table->string('status', 30)->default('pending');
                $table->text('error_message')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->unique('event_uuid', 'tag_sync_event_uuid_idx');
                $table->index(['user_id', 'status'], 'tag_sync_event_user_status_idx');
                $table->index(['device_id', 'created_at'], 'tag_sync_event_device_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_sync_events');
        Schema::dropIfExists('tagore_sync_devices');
    }
};
