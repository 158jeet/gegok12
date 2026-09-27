<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_security_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('visitor_name');
            $table->string('phone',30)->nullable();
            $table->string('purpose',190);
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->foreign('host_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('otp_hash',128)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('status',30)->default('expected');
            $table->timestamps();
            $table->index(['institution_id','status']);
        });

        Schema::create('tagore_security_gatepasses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('pass_type',50);
            $table->text('reason');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->string('token_hash',128)->unique();
            $table->string('status',30)->default('pending');
            $table->timestamps();
            $table->index(['institution_id','student_id','status']);
        });

        Schema::create('tagore_security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('event_type',80);
            $table->string('subject_type',80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_address',64)->nullable();
            $table->timestamps();
            $table->index(['institution_id','event_type','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_security_events');
        Schema::dropIfExists('tagore_security_gatepasses');
        Schema::dropIfExists('tagore_security_visitors');
    }
};
