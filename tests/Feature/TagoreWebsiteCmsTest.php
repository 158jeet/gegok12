<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TagoreWebsiteCmsTest extends TestCase
{
    public function test_published_pages_render_but_draft_pages_require_a_signed_preview(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('status', 'active')->whereNull('deleted_at')->first();
        $this->assertNotNull($institution);

        $this->actingAs($owner)->post(route('tagore.website.site.store'), ['institution_id' => $institution->id])
            ->assertRedirect(route('tagore.website.index'));
        $site = DB::table('tagore_website_sites')->where('institution_id', $institution->id)->first();
        $this->assertNotNull($site);

        $this->actingAs($owner)->post(route('tagore.website.pages.store'), [
            'site_id' => $site->id, 'title' => 'CMS acceptance page', 'slug' => 'cms-acceptance',
            'locale' => 'en', 'body' => 'A published test page.',
            'blocks_json' => json_encode([['type' => 'hero', 'heading' => 'Welcome', 'body' => 'Safe text content']]),
        ])->assertRedirect(route('tagore.website.index'));

        $page = DB::table('tagore_cms_pages')->where('institution_id', $institution->id)->where('slug', 'cms-acceptance')->first();
        $this->assertNotNull($page);
        $this->get(route('tagore.website.public', ['siteKey' => $site->site_key, 'slug' => 'cms-acceptance']))->assertNotFound();

        $previewUrl = URL::temporarySignedRoute('tagore.website.preview', now()->addMinutes(5), ['pageId' => $page->id]);
        $this->get($previewUrl)->assertOk()->assertSee('PRIVATE DRAFT PREVIEW')->assertSee('Safe text content');

        $this->actingAs($owner)->patch(route('tagore.website.pages.publish', $page->id))
            ->assertRedirect(route('tagore.website.index'));
        $this->assertDatabaseHas('tagore_cms_page_revisions', ['page_id' => $page->id, 'version' => 1, 'event' => 'publish']);
        $this->get(route('tagore.website.public', ['siteKey' => $site->site_key, 'slug' => 'cms-acceptance']))
            ->assertOk()->assertSee('Safe text content');
    }

    public function test_unknown_section_types_are_rejected_instead_of_rendered_as_html(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->whereNull('deleted_at')->firstOrFail();
        $institution = DB::table('tagore_institutions')->where('status', 'active')->whereNull('deleted_at')->firstOrFail();
        DB::table('tagore_website_sites')->insert([
            'institution_id' => $institution->id, 'site_key' => 'cms-security-' . $institution->id,
            'site_name' => $institution->display_name, 'status' => 'draft', 'locale' => 'en',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $siteId = DB::table('tagore_website_sites')->where('institution_id', $institution->id)->value('id');

        $this->actingAs($owner)->from(route('tagore.website.index'))->post(route('tagore.website.pages.store'), [
            'site_id' => $siteId, 'title' => 'Unsafe block', 'slug' => 'unsafe-block', 'locale' => 'en',
            'blocks_json' => json_encode([['type' => 'raw_html', 'body' => '<script>alert(1)</script>']]),
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tagore_cms_pages', ['institution_id' => $institution->id, 'slug' => 'unsafe-block']);
    }
}
