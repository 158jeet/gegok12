<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tagore_sync_devices')) {
            Schema::create('tagore_sync_devices', function (Blueprint $t) {
                $t->id();
                $t->string('device_uuid', 100)->unique();
                $t->unsignedInteger('user_id')->index();
                $t->unsignedInteger('school_id')->nullable()->index();
                $t->string('platform', 30)->default('windows');
                $t->string('app_version', 40)->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('tagore_sync_changes')) {
            Schema::create('tagore_sync_changes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('user_id')->index();
                $t->unsignedInteger('school_id')->nullable()->index();
                $t->unsignedBigInteger('device_id')->nullable()->index();
                $t->string('client_change_id', 100)->unique();
                $t->string('entity', 60);
                $t->string('operation', 20);
                $t->unsignedBigInteger('entity_id')->nullable();
                $t->json('payload');
                $t->string('status', 20)->default('applied');
                $t->text('error_message')->nullable();
                $t->timestamp('client_occurred_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('tagore_sync_cursors')) {
            Schema::create('tagore_sync_cursors', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('device_id')->unique();
                $t->unsignedBigInteger('last_server_change_id')->default(0);
                $t->timestamp('last_synced_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_sync_cursors');
        Schema::dropIfExists('tagore_sync_changes');
        Schema::dropIfExists('tagore_sync_devices');
    }
};