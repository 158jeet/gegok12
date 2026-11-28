<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_website_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained('tagore_institutions')->cascadeOnDelete();
            $table->string('site_key', 120)->unique();
            $table->string('site_name', 190);
            $table->string('primary_host', 255)->nullable()->unique();
            $table->string('locale', 10)->default('en');
            $table->json('theme_json')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::table('tagore_cms_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('tagore_cms_pages', 'content_json')) $table->json('content_json')->nullable();
            if (!Schema::hasColumn('tagore_cms_pages', 'locale')) $table->string('locale', 10)->default('en');
            if (!Schema::hasColumn('tagore_cms_pages', 'created_by')) $table->unsignedInteger('created_by')->nullable();
            if (!Schema::hasColumn('tagore_cms_pages', 'updated_by')) $table->unsignedInteger('updated_by')->nullable();
        });

        Schema::create('tagore_cms_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('tagore_cms_pages')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('event', 30)->default('publish');
            $table->longText('snapshot_json');
            $table->unsignedInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['page_id', 'version']);
            $table->index(['institution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_cms_page_revisions');
        Schema::table('tagore_cms_pages', function (Blueprint $table) {
            foreach (['content_json', 'locale', 'created_by', 'updated_by'] as $column) {
                if (Schema::hasColumn('tagore_cms_pages', $column)) $table->dropColumn($column);
            }
        });
        Schema::dropIfExists('tagore_website_sites');
    }
};
