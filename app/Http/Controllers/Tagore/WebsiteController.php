<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    private const EDIT_ROLES = ['OWNER', 'PRINCIPAL', 'WEBSITE_EDITOR'];
    private const PUBLISH_ROLES = ['OWNER', 'PRINCIPAL'];

    public function index(Request $request): View
    {
        [$roles, $ids] = $this->context($request, self::EDIT_ROLES);
        $institutions = DB::table('tagore_institutions')->whereIn('id', $ids)
            ->where('status', 'active')->whereNull('deleted_at')->orderBy('display_name')
            ->get(['id', 'code', 'display_name']);
        $sites = DB::table('tagore_website_sites as s')
            ->join('tagore_institutions as i', 'i.id', '=', 's.institution_id')
            ->whereIn('s.institution_id', $ids)->orderBy('i.display_name')
            ->get(['s.*', 'i.display_name']);
        $pages = DB::table('tagore_cms_pages as p')
            ->join('tagore_institutions as i', 'i.id', '=', 'p.institution_id')
            ->whereIn('p.institution_id', $ids)->orderByDesc('p.updated_at')->limit(200)
            ->get(['p.*', 'i.display_name']);
        return view('tagore.website.index', compact('roles', 'institutions', 'sites', 'pages'));
    }

    public function storeSite(Request $request)
    {
        [, $ids] = $this->context($request, self::EDIT_ROLES);
        $data = $request->validate(['institution_id' => 'required|integer']);
        $institution = DB::table('tagore_institutions')->where('id', $data['institution_id'])
            ->whereIn('id', $ids)->where('status', 'active')->whereNull('deleted_at')->first();
        abort_unless($institution, 404);
        $key = Str::slug($institution->code) . '-' . $institution->id;
        DB::table('tagore_website_sites')->updateOrInsert(
            ['institution_id' => $institution->id],
            ['site_key' => $key, 'site_name' => $institution->display_name, 'status' => 'draft',
             'locale' => 'en', 'created_at' => now(), 'updated_at' => now()]
        );
        return redirect()->route('tagore.website.index')->with('success', 'Website workspace created. Add and review pages before publishing.');
    }

    public function storePage(Request $request)
    {
        [, $ids] = $this->context($request, self::EDIT_ROLES);
        $data = $request->validate([
            'site_id' => 'required|integer', 'page_id' => 'nullable|integer',
            'title' => 'required|string|max:190',
            'slug' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'locale' => 'required|in:en,hi', 'body' => 'nullable|string|max:20000',
            'meta_title' => 'nullable|string|max:190', 'meta_description' => 'nullable|string|max:500',
            'blocks_json' => 'nullable|string|max:30000',
        ]);
        $site = DB::table('tagore_website_sites')->where('id', $data['site_id'])
            ->whereIn('institution_id', $ids)->first();
        abort_unless($site, 404);

        $blocks = [];
        if (!empty($data['blocks_json'])) {
            $decoded = json_decode($data['blocks_json'], true);
            abort_unless(json_last_error() === JSON_ERROR_NONE && is_array($decoded), 422, 'Sections must be valid JSON.');
            abort_if(count($decoded) > 30, 422, 'A page can contain at most 30 sections.');
            foreach ($decoded as $block) {
                abort_unless(is_array($block) && in_array($block['type'] ?? null, ['hero', 'text', 'stats', 'cta', 'faq'], true), 422, 'Unsupported page section type.');
                $clean = ['type' => $block['type']];
                foreach (['heading', 'body', 'label', 'value', 'button_label', 'button_url', 'question', 'answer'] as $field) {
                    if (!isset($block[$field])) continue;
                    abort_unless(is_string($block[$field]) && mb_strlen($block[$field]) <= 3000, 422, 'Invalid section field.');
                    if ($field === 'button_url') {
                        $url = parse_url($block[$field]);
                        abort_unless($url && ($url['scheme'] ?? '') === 'https', 422, 'Buttons must use HTTPS links.');
                    }
                    $clean[$field] = $block[$field];
                }
                $blocks[] = $clean;
            }
        }

        $values = [
            'slug' => $data['slug'], 'title' => $data['title'], 'body' => $data['body'] ?? null,
            'meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null,
            'content_json' => json_encode($blocks, JSON_UNESCAPED_UNICODE), 'locale' => $data['locale'],
            'status' => 'draft', 'published_at' => null, 'updated_by' => $request->user()->id, 'updated_at' => now(),
        ];
        if (!empty($data['page_id'])) {
            $page = DB::table('tagore_cms_pages')->where('id', $data['page_id'])
                ->where('institution_id', $site->institution_id)->first();
            abort_unless($page, 404);
            DB::table('tagore_cms_pages')->where('id', $page->id)->update($values);
        } else {
            DB::table('tagore_cms_pages')->insert($values + [
                'institution_id' => $site->institution_id, 'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);
        }
        return redirect()->route('tagore.website.index')->with('success', 'Draft saved. Preview it before publishing.');
    }

    public function publish(Request $request, int $pageId)
    {
        [, $ids] = $this->context($request, self::PUBLISH_ROLES);
        $page = DB::table('tagore_cms_pages')->where('id', $pageId)->whereIn('institution_id', $ids)->first();
        abort_unless($page, 404);
        DB::transaction(function () use ($page, $request) {
            $locked = DB::table('tagore_cms_pages')->where('id', $page->id)->lockForUpdate()->first();
            $version = (int) DB::table('tagore_cms_page_revisions')->where('page_id', $page->id)->max('version') + 1;
            $snapshot = collect($locked)->only(['slug', 'title', 'body', 'meta_title', 'meta_description', 'content_json', 'locale'])->all();
            DB::table('tagore_cms_page_revisions')->insert([
                'page_id' => $locked->id, 'institution_id' => $locked->institution_id,
                'version' => $version, 'event' => 'publish',
                'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'actor_id' => $request->user()->id, 'created_at' => now(),
            ]);
            DB::table('tagore_cms_pages')->where('id', $locked->id)->update([
                'status' => 'published', 'published_at' => now(),
                'updated_by' => $request->user()->id, 'updated_at' => now(),
            ]);
            DB::table('tagore_website_sites')->where('institution_id', $locked->institution_id)
                ->update(['status' => 'published', 'updated_at' => now()]);
        });
        return redirect()->route('tagore.website.index')->with('success', 'Page published and revision archived.');
    }

    public function preview(int $pageId): View
    {
        $page = DB::table('tagore_cms_pages')->where('id', $pageId)->first();
        abort_unless($page, 404);
        return view('tagore.website.public', ['page' => $page, 'site' => null, 'preview' => true]);
    }

    public function publicPage(string $siteKey, ?string $slug = 'home'): View
    {
        $site = DB::table('tagore_website_sites')->where('site_key', $siteKey)->where('status', 'published')->first();
        abort_unless($site, 404);
        $page = DB::table('tagore_cms_pages')->where('institution_id', $site->institution_id)
            ->where('slug', $slug ?: 'home')->where('status', 'published')->first();
        abort_unless($page, 404);
        return view('tagore.website.public', ['page' => $page, 'site' => $site, 'preview' => false]);
    }

    private function context(Request $request, array $allowed): array
    {
        $today = today();
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $request->user()->id)->where('ur.status', 'active')->where('r.status', 'active')
            ->where(fn ($q) => $q->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', $today))
            ->pluck('r.code')->unique()->values();
        abort_unless($roles->contains('OWNER') || $roles->intersect($allowed)->isNotEmpty(), 403);
        $ids = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $request->user()->id)->where('status', 'active')
                ->whereNotNull('institution_id')->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        return [$roles, $ids];
    }
}
