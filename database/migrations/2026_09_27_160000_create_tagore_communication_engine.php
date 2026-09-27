<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_message_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('title');
            $table->string('channel',30);
            $table->json('audience_json')->nullable();
            $table->text('message');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status',30)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
            $table->index(['institution_id','status','scheduled_at']);
        });

        Schema::create('tagore_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('tagore_message_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('channel',30);
            $table->string('destination',190)->nullable();
            $table->string('status',30)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id','user_id','channel']);
            $table->index(['campaign_id','status']);
        });

        Schema::create('tagore_user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('token',500);
            $table->string('platform',30)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id','token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_user_devices');
        Schema::dropIfExists('tagore_message_deliveries');
        Schema::dropIfExists('tagore_message_campaigns');
    }
};
