<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecoveryChallengesTable extends Migration
{
    public function up()
    {
        Schema::create('recovery_challenges', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('usergroup_id');
            $table->string('purpose', 50);
            $table->string('channel', 20)->default('sms');
            $table->string('code_hash', 255);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'usergroup_id', 'purpose', 'created_at'], 'recovery_challenge_lookup_idx');
            $table->index(['purpose', 'expires_at', 'consumed_at'], 'recovery_challenge_expiry_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('recovery_challenges');
    }
}
