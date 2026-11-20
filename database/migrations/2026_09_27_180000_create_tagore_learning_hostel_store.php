<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('hostel_name');
            $table->string('room_no');
            $table->integer('beds')->default(1);
            $table->decimal('fee',12,2)->default(0);
            $table->string('status',30)->default('active');
            $table->timestamps();
            $table->unique(['institution_id','hostel_name','room_no']);
        });

        Schema::create('tagore_hostel_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('tagore_hostel_rooms')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('bed_no',30);
            $table->date('check_in');
            $table->date('check_out')->nullable();
            $table->string('status',30)->default('active');
            $table->timestamps();
            $table->index(['student_id','status']);
        });

        Schema::create('tagore_course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('tagore_course_records')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('enrolled_at');
            $table->decimal('progress',5,2)->default(0);
            $table->string('status',30)->default('active');
            $table->timestamps();
            $table->unique(['course_id','student_id']);
        });

        Schema::create('tagore_content_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('tagore_course_records')->cascadeOnDelete();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->decimal('progress',5,2)->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id','student_id','content_id']);
        });

        Schema::create('tagore_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('title');
            $table->json('questions_json');
            $table->boolean('anonymous')->default(false);
            $table->string('status',30)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tagore_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('tagore_surveys')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->json('answers_json');
            $table->timestamps();
            $table->index(['survey_id','created_at']);
        });

        Schema::create('tagore_store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku',100);
            $table->string('category',100)->nullable();
            $table->decimal('price',12,2);
            $table->integer('stock')->default(0);
            $table->string('status',30)->default('active');
            $table->timestamps();
            $table->unique(['institution_id','sku']);
        });

        Schema::create('tagore_store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('buyer_user_id');
            $table->foreign('buyer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('total_amount',12,2)->default(0);
            $table->string('status',30)->default('pending');
            $table->string('payment_status',30)->default('unpaid');
            $table->timestamps();
            $table->index(['institution_id','status']);
        });

        Schema::create('tagore_store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('tagore_store_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('tagore_store_products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price',12,2);
            $table->decimal('line_total',12,2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_store_order_items');
        Schema::dropIfExists('tagore_store_orders');
        Schema::dropIfExists('tagore_store_products');
        Schema::dropIfExists('tagore_survey_responses');
        Schema::dropIfExists('tagore_surveys');
        Schema::dropIfExists('tagore_content_progress');
        Schema::dropIfExists('tagore_course_enrollments');
        Schema::dropIfExists('tagore_hostel_allocations');
        Schema::dropIfExists('tagore_hostel_rooms');
    }
};
