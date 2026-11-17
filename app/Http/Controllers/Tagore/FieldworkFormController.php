<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FieldworkFormController extends Controller
{
    private const MANAGERS = ['OWNER', 'PRINCIPAL', 'COORDINATOR'];

    public function index(Request $request)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);

        $staff = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->join('users as u', 'u.id', '=', 'ur.user_id')
            ->whereIn('ur.institution_id', $institutions)->where('ur.status', 'active')
            ->whereIn('r.code', ['OWNER','PRINCIPAL','COORDINATOR','TEACHER'])->whereNull('u.deleted_at')
            ->select('u.id', 'u.name', 'ur.institution_id')->distinct()->orderBy('u.name')->get();
        return response()->json([
            'staff' => $staff,
            'institutions' => DB::table('tagore_institutions')->whereIn('id', $institutions)->where('status', 'active')->orderBy('display_name')->get(['id','display_name']),
            'versions' => DB::table('tagore_form_versions as v')->join('tagore_form_templates as t', 't.id', '=', 'v.template_id')->whereIn('t.institution_id', $institutions)->orderByDesc('v.id')->get(['v.id','v.template_id','v.version']),
            'templates' => DB::table('tagore_form_templates')->whereIn('institution_id', $institutions)->orderByDesc('id')->limit(100)->get(),
            'campaigns' => DB::table('tagore_form_campaigns as c')
                ->join('tagore_form_versions as v', 'v.id', '=', 'c.form_version_id')
                ->whereIn('c.institution_id', $institutions)->orderByDesc('c.id')->limit(100)
                ->get(['c.*', 'v.version as form_version']),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.type' => ['required', 'in:text,textarea,number,email,date,select,checkbox'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.options' => ['sometimes', 'array', 'max:100'],
            'fields.*.visible_if' => ['sometimes', 'array'],
        ]);
        abort_unless(in_array((int) $data['institution_id'], $institutions, true), 403);
        $this->validateFieldDefinitions($data['fields']);

        $id = DB::table('tagore_form_templates')->insertGetId([
            'institution_id' => $data['institution_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'draft_fields' => json_encode($data['fields'], JSON_THROW_ON_ERROR),
            'status' => 'draft',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['id' => $id, 'status' => 'draft'], 201);
    }

    public function publish(Request $request, int $templateId)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,59}$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.type' => ['required', 'in:text,textarea,number,email,date,select,checkbox'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.options' => ['sometimes', 'array', 'max:100'],
            'fields.*.visible_if' => ['sometimes', 'array'],
        ]);
        $this->validateFieldDefinitions($data['fields']);

        $versionId = DB::transaction(function () use ($templateId, $userId, $institutions, $data) {
            $template = DB::table('tagore_form_templates')->where('id', $templateId)->lockForUpdate()->first();
            abort_unless($template && in_array((int) $template->institution_id, $institutions, true), 404);
            $version = (int) DB::table('tagore_form_versions')->where('template_id', $templateId)->max('version') + 1;
            $id = DB::table('tagore_form_versions')->insertGetId([
                'template_id' => $templateId, 'version' => $version,
                'fields' => json_encode($data['fields'], JSON_THROW_ON_ERROR),
                'published_by' => $userId, 'published_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('tagore_form_templates')->where('id', $templateId)->update(['status' => 'published', 'updated_at' => now()]);
            return $id;
        });

        return response()->json(['form_version_id' => $versionId, 'immutable' => true], 201);
    }

    public function createCampaign(Request $request)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        abort_unless($roles->intersect(self::MANAGERS)->isNotEmpty(), 403);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'template_id' => ['required', 'integer'],
            'form_version_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'assigned_user_ids' => ['required', 'array', 'min:1', 'max:250'],
            'assigned_user_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutions, true), 403);
        $version = DB::table('tagore_form_versions as v')->join('tagore_form_templates as t', 't.id', '=', 'v.template_id')
            ->where('v.id', $data['form_version_id'])->where('t.id', $data['template_id'])
            ->where('t.institution_id', $institutionId)->first();
        abort_unless($version, 422, 'Select a published form version belonging to this institution.');
        $assigned = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.institution_id', $institutionId)->where('ur.status', 'active')
            ->whereIn('ur.user_id', $data['assigned_user_ids'])->whereIn('r.code', ['OWNER','PRINCIPAL','COORDINATOR','TEACHER'])
            ->distinct()->pluck('ur.user_id')->map(fn ($id) => (int) $id)->all();
        abort_unless(count($assigned) === count($data['assigned_user_ids']), 422, 'Every assignee must have an active staff role in this institution.');

        $campaignId = DB::transaction(function () use ($data, $institutionId, $userId, $assigned) {
            $id = DB::table('tagore_form_campaigns')->insertGetId([
                'institution_id' => $institutionId, 'template_id' => $data['template_id'],
                'form_version_id' => $data['form_version_id'], 'name' => $data['name'],
                'status' => 'active', 'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null, 'created_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($assigned as $assigneeId) {
                DB::table('tagore_form_campaign_assignments')->insert([
                    'campaign_id' => $id, 'user_id' => $assigneeId, 'created_at' => now(),
                ]);
            }
            return $id;
        });
        return response()->json(['campaign_id' => $campaignId, 'assigned_count' => count($assigned)], 201);
    }

    public function submit(Request $request, int $campaignId)
    {
        [$userId, $roles, $institutions] = $this->context($request);
        $campaign = DB::table('tagore_form_campaigns')->where('id', $campaignId)->first();
        abort_unless($campaign && in_array((int) $campaign->institution_id, $institutions, true), 404);
        abort_unless($campaign->status === 'active'
            && (!$campaign->starts_at || now()->gte($campaign->starts_at))
            && (!$campaign->ends_at || now()->lte($campaign->ends_at)), 422, 'This campaign is not accepting submissions.');
        abort_unless(DB::table('tagore_form_campaign_assignments')->where('campaign_id', $campaignId)->where('user_id', $userId)->exists(), 403);
        $data = $request->validate([
            'payload' => ['required', 'array'],
            'finalize' => ['sometimes', 'boolean'],
            'admission_lead_id' => ['nullable', 'integer'],
        ]);
        $version = DB::table('tagore_form_versions')->where('id', $campaign->form_version_id)->first();
        $fields = json_decode($version->fields, true, 512, JSON_THROW_ON_ERROR);
        $this->validatePayload($data['payload'], $fields, (bool) ($data['finalize'] ?? false));
        if (!empty($data['admission_lead_id'])) {
            abort_unless(DB::table('tagore_admission_leads')->where('id', $data['admission_lead_id'])->where('institution_id', $campaign->institution_id)->exists(), 422);
        }

        $id = DB::transaction(function () use ($data, $campaign, $campaignId, $userId) {
            $status = ($data['finalize'] ?? false) ? 'submitted' : 'draft';
            $id = DB::table('tagore_form_submissions')->insertGetId([
                'campaign_id' => $campaignId, 'institution_id' => $campaign->institution_id,
                'form_version_id' => $campaign->form_version_id, 'submitted_by' => $userId,
                'admission_lead_id' => $data['admission_lead_id'] ?? null,
                'status' => $status, 'payload' => json_encode($data['payload'], JSON_THROW_ON_ERROR),
                'submitted_at' => $status === 'submitted' ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('tagore_form_submission_events')->insert([
                'submission_id' => $id, 'actor_id' => $userId,
                'event_type' => $status === 'submitted' ? 'submitted' : 'draft_saved',
                'metadata' => json_encode(['form_version_id' => $campaign->form_version_id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            return $id;
        });
        return response()->json(['submission_id' => $id, 'status' => ($data['finalize'] ?? false) ? 'submitted' : 'draft'], 201);
    }

    public function studio(Request $request)
    {
        [, $roles] = $this->context($request);
        $allowed = $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER'])->isNotEmpty();
        abort_unless($allowed, 403);
        $canManage = $roles->intersect(self::MANAGERS)->isNotEmpty();
        return view('tagore.fieldwork.studio', compact('canManage'));
    }

    public function myCampaigns(Request $request)
    {
        $userId = (int) $request->user()->id;
        $campaigns = DB::table('tagore_form_campaigns as c')
            ->join('tagore_form_campaign_assignments as a', 'a.campaign_id', '=', 'c.id')
            ->join('tagore_form_versions as v', 'v.id', '=', 'c.form_version_id')
            ->where('a.user_id', $userId)->where('c.status', 'active')
            ->where(function ($q) { $q->whereNull('c.starts_at')->orWhere('c.starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('c.ends_at')->orWhere('c.ends_at', '>=', now()); })
            ->orderBy('c.name')->get(['c.id', 'c.name', 'c.institution_id', 'c.form_version_id', 'v.fields']);
        return response()->json(['campaigns' => $campaigns]);
    }

    private function validatePayload(array $payload, array $fields, bool $finalize): void
    {
        $rules = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $required = $finalize && !empty($field['required']) && $this->isVisible($field, $payload);
            $rules[$key] = [$required ? 'required' : 'nullable'];
            if (isset($field['type'])) {
                $rules[$key][] = match ($field['type']) {
                    'number' => 'numeric', 'email' => 'email', 'date' => 'date',
                    'checkbox' => 'boolean', 'text', 'textarea', 'select' => 'string',
                    default => 'string',
                };
            }
            if (($field['type'] ?? null) === 'select') {
                $rules[$key][] = 'in:' . implode(',', array_map('strval', $field['options'] ?? []));
            }
        }
        Validator::make($payload, $rules)->validate();
        $allowed = array_column($fields, 'key');
        abort_if(array_diff(array_keys($payload), $allowed), 422, 'Submission contains unknown fields.');
    }

    private function isVisible(array $field, array $payload): bool
    {
        $condition = $field['visible_if'] ?? null;
        if (!$condition) return true;
        $key = $condition['field'] ?? '';
        return array_key_exists($key, $payload) && (string) $payload[$key] === (string) ($condition['equals'] ?? '');
    }

    private function validateFieldDefinitions(array $fields): void
    {
        $keys = array_column($fields, 'key');
        abort_if(count($keys) !== count(array_unique($keys)), 422, 'Field keys must be unique.');
        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'select') {
                abort_if(empty($field['options']) || count(array_unique($field['options'])) !== count($field['options']), 422, 'Select fields need unique options.');
            }
            if (isset($field['visible_if'])) {
                $condition = $field['visible_if'];
                abort_unless(isset($condition['field'], $condition['equals']) && in_array($condition['field'], $keys, true), 422, 'Conditional visibility must reference a field in this form.');
            }
        }
    }

    private function context(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
        $institutions = $roles->contains('OWNER')
            ? DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')
                ->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        return [$userId, $roles, $institutions];
    }
}
