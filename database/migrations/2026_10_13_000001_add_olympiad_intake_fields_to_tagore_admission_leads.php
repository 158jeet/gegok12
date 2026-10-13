<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->string('medium', 12)->nullable();
            $table->string('school_name', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->string('privacy_notice_version', 40)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tagore_admission_leads', function (Blueprint $table) {
            $table->dropColumn([
                'medium', 'school_name', 'city', 'marketing_consent', 'consented_at',
                'privacy_notice_version', 'utm_source', 'utm_medium', 'utm_content', 'utm_term',
            ]);
        });
    }
};