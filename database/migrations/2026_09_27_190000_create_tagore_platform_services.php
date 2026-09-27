<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_alumni_profiles', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->unsignedBigInteger('student_id'); $t->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $t->integer('passout_year')->nullable(); $t->string('course',100)->nullable(); $t->string('phone',30)->nullable();
            $t->string('email',190)->nullable(); $t->string('current_org',190)->nullable(); $t->string('designation',190)->nullable();
            $t->text('notes')->nullable(); $t->timestamps(); $t->unique(['institution_id','student_id']);
        });
        Schema::create('tagore_alumni_events', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->string('title'); $t->timestamp('starts_at'); $t->timestamp('ends_at')->nullable(); $t->text('description')->nullable();
            $t->string('status',30)->default('planned'); $t->timestamps();
        });
        Schema::create('tagore_alumni_donations', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->unsignedBigInteger('alumni_id'); $t->foreign('alumni_id')->references('id')->on('tagore_alumni_profiles')->cascadeOnDelete();
            $t->decimal('amount',12,2); $t->string('purpose',190)->nullable(); $t->string('status',30)->default('pledged'); $t->timestamps();
        });

        Schema::create('tagore_fee_finance_plans', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->string('name'); $t->decimal('amount',12,2); $t->integer('tenure_months'); $t->decimal('interest_rate',8,4)->default(0);
            $t->decimal('processing_fee',12,2)->default(0); $t->string('provider',190)->nullable(); $t->string('status',30)->default('active'); $t->timestamps();
        });
        Schema::create('tagore_fee_finance_applications', function(Blueprint $t){
            $t->id(); $t->foreignId('plan_id')->constrained('tagore_fee_finance_plans')->cascadeOnDelete();
            $t->unsignedBigInteger('student_id'); $t->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $t->decimal('principal',12,2); $t->decimal('emi',12,2); $t->string('external_reference',190)->nullable();
            $t->string('status',30)->default('pending'); $t->timestamps();
        });

        Schema::create('tagore_cms_pages', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->string('slug',190); $t->string('title'); $t->longText('body')->nullable(); $t->string('meta_title',190)->nullable();
            $t->text('meta_description')->nullable(); $t->timestamp('published_at')->nullable(); $t->string('status',30)->default('draft'); $t->timestamps();
            $t->unique(['institution_id','slug']);
        });
        Schema::create('tagore_social_posts', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->unsignedBigInteger('author_id'); $t->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
            $t->string('group_name',100)->nullable(); $t->string('title',190)->nullable(); $t->text('body'); $t->string('attachment_path',1000)->nullable();
            $t->string('status',30)->default('published'); $t->timestamps(); $t->index(['institution_id','created_at']);
        });
        Schema::create('tagore_social_comments', function(Blueprint $t){
            $t->id(); $t->foreignId('post_id')->constrained('tagore_social_posts')->cascadeOnDelete();
            $t->unsignedBigInteger('author_id'); $t->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
            $t->text('body'); $t->timestamps();
        });

        Schema::create('tagore_automation_rules', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->string('name'); $t->string('trigger',100); $t->string('action',100); $t->json('config_json')->nullable();
            $t->string('schedule',100)->nullable(); $t->boolean('enabled')->default(true); $t->timestamp('last_run_at')->nullable(); $t->timestamps();
        });
        Schema::create('tagore_automation_runs', function(Blueprint $t){
            $t->id(); $t->foreignId('rule_id')->constrained('tagore_automation_rules')->cascadeOnDelete();
            $t->string('status',30)->default('running'); $t->text('output')->nullable(); $t->text('error')->nullable(); $t->timestamp('started_at'); $t->timestamp('finished_at')->nullable(); $t->timestamps();
        });

        Schema::create('tagore_integration_configs', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->string('name'); $t->string('provider',100); $t->string('type',50); $t->json('config_json')->nullable();
            $t->string('status',30)->default('configured'); $t->timestamp('last_tested_at')->nullable(); $t->text('last_test_result')->nullable(); $t->timestamps();
        });
        Schema::create('tagore_creative_templates', function(Blueprint $t){
            $t->id(); $t->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $t->string('name'); $t->string('template_type',80); $t->json('template_json')->nullable(); $t->json('brand_json')->nullable();
            $t->string('status',30)->default('active'); $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_creative_templates'); Schema::dropIfExists('tagore_integration_configs');
        Schema::dropIfExists('tagore_automation_runs'); Schema::dropIfExists('tagore_automation_rules');
        Schema::dropIfExists('tagore_social_comments'); Schema::dropIfExists('tagore_social_posts');
        Schema::dropIfExists('tagore_cms_pages');
        Schema::dropIfExists('tagore_fee_finance_applications'); Schema::dropIfExists('tagore_fee_finance_plans');
        Schema::dropIfExists('tagore_alumni_donations'); Schema::dropIfExists('tagore_alumni_events'); Schema::dropIfExists('tagore_alumni_profiles');
    }
};
