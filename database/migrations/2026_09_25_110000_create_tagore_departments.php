<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 60);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'status']);
        });

        Schema::create('tagore_user_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('tagore_departments')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('designation', 100)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('tagore_tasks', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('institution_id')
                ->constrained('tagore_departments')->nullOnDelete();
            $table->index(['institution_id', 'department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tagore_tasks', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['institution_id', 'department_id', 'status']);
            $table->dropColumn('department_id');
        });
        Schema::dropIfExists('tagore_user_departments');
        Schema::dropIfExists('tagore_departments');
    }
};