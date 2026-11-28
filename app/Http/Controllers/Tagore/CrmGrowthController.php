<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreAdmissionActivity;
use App\Models\TagoreAdmissionLead;
use App\Models\TagoreCrmAccount;
use App\Models\TagoreCrmCampaign;
use App\Models\TagoreCrmContact;
use App\Models\TagoreCrmCase;
use App\Models\TagoreCrmOpportunity;
use App\Models\TagoreCrmTask;
use App\Services\Tagore\CrmLeadScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmGrowthController extends Controller
{
    private const VIEW_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS', 'TELECALLER', 'MARKETING'];
    private const OPPORTUNITY_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS'];
    private const CAMPAIGN_ROLES = ['OWNER', 'PRINCIPAL', 'CRM_MANAGER', 'MARKETING'];
    private const TASK_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS', 'TELECALLER', 'MARKETING'];
    private const CASE_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS'];
    private const STAFF_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'CRM_MANAGER', 'ADMISSIONS', 'TELECALLER', 'MARKETING'];
    private const STAGES = ['qualification', 'needs_analysis', 'proposal', 'negotiation', 'won', 'lost'];
    private const TASK_TYPES = ['call', 'email', 'meeting', 'visit', 'follow_up', 'document', 'other'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const CASE_CATEGORIES = ['general', 'fee', 'transport', 'hostel', 'academic', 'admissions', 'examination', 'technology', 'safety', 'other'];
    private const CASE_STATUSES = ['open', 'in_progress', 'waiting_on_parent', 'resolved', 'closed'];
    private const ACCOUNT_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS', 'MARKETING'];
    private const CONTACT_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS', 'TELECALLER', 'MARKETING'];

    private function context(Request $request, array $allowedRoles = self::VIEW_ROLES): array
    {
        $userId = (int) $request->user()->id;
        $today = today();
        $roles = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('r.status', 'active')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', $today))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', $today))
            ->pluck('r.code')->unique()->values();

        abort_unless($roles->intersect($allowedRoles)->isNotEmpty(), 403);

        if ($roles->contains('OWNER')) {
            $institutionIds = DB::table('tagore_institutions')->where('status', 'active')
                ->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $institutionIds = DB::table('tagore_user_roles as ur')
                ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
                ->join('tagore_institutions as i', 'i.id', '=', 'ur.institution_id')
                ->where('ur.user_id', $userId)->where('ur.status', 'active')->where('r.status', 'active')
                ->where('i.status', 'active')->whereNull('i.deleted_at')->whereNotNull('ur.institution_id')
                ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', $today))
                ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', $today))
                ->distinct()->pluck('ur.institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        abort_unless($institutionIds !== [], 403);
        return [$userId, $roles, $institutionIds];
    }

    public function index(Request $request, CrmLeadScoringService $scoring): View
    {
        [$userId, $roles, $institutionIds] = $this->context($request);
        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)
            ->orderBy('display_name')->get(['id', 'display_name', 'school_id']);

        $staff = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('r.status', 'active')
            ->where(function ($query) use ($institutionIds) { $query->whereIn('ur.institution_id', $institutionIds)->orWhere(fn ($global) => $global->whereNull('ur.institution_id')->where('r.code', 'OWNER')); })->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->whereIn('r.code', self::STAFF_ROLES)
            ->whereNull('u.deleted_at')->select('u.id', 'u.name')->distinct()->orderBy('u.name')->get();

        $schoolIds = $institutions->pluck('school_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $leads = TagoreAdmissionLead::query()
            ->whereIn('institution_id', $institutionIds)->latest()->limit(100)
            ->select(['id', 'lead_no', 'institution_id', 'student_name', 'parent_name', 'mobile', 'email', 'class_name', 'marketing_consent', 'status', 'source', 'campaign', 'assigned_to', 'next_follow_up_at', 'last_contacted_at'])
            ->withCount(['activities', 'calls'])->get();
        $leads->each(function ($lead) use ($scoring) {
            $lead->crm_score = $scoring->score($lead)['score'];
        });
        $topLeads = $leads->sortByDesc('crm_score')->take(10)->values();
        $students = $schoolIds ? DB::table('users')->whereIn('school_id', $schoolIds)->where('usergroup_id', 6)
            ->where('status', 'active')->whereNull('deleted_at')->orderBy('name')->limit(200)->get(['id', 'name', 'school_id']) : collect();
        $parents = $schoolIds ? DB::table('tagore_parent_students as ps')->join('users as parent', 'parent.id', '=', 'ps.parent_user_id')
            ->join('users as child', 'child.id', '=', 'ps.student_id')->whereIn('child.school_id', $schoolIds)
            ->where('ps.status', 'active')->where('parent.status', 'active')->whereNull('parent.deleted_at')
            ->select('parent.id', 'parent.name')->distinct()->orderBy('parent.name')->limit(200)->get() : collect();

        $stageRows = TagoreCrmOpportunity::query()->whereIn('institution_id', $institutionIds)
            ->select('stage', DB::raw('count(*) as total'), DB::raw('coalesce(sum(amount),0) as amount'),
                DB::raw('coalesce(sum(amount * probability / 100),0) as weighted_amount'))
            ->groupBy('stage')->get()->keyBy('stage');

        $pipeline = collect(self::STAGES)->map(fn ($stage) => [
            'stage' => $stage,
            'total' => (int) ($stageRows[$stage]->total ?? 0),
            'amount' => (float) ($stageRows[$stage]->amount ?? 0),
            'weighted_amount' => (float) ($stageRows[$stage]->weighted_amount ?? 0),
        ]);

        $opportunities = TagoreCrmOpportunity::query()->whereIn('institution_id', $institutionIds)
            ->with(['lead', 'account', 'contact'])->latest()->limit(100)->get();
        $campaigns = TagoreCrmCampaign::query()->whereIn('institution_id', $institutionIds)
            ->latest()->limit(100)->get()->map(function ($campaign) {
                $leadQuery = TagoreAdmissionLead::query()
                    ->where('institution_id', $campaign->institution_id)
                    ->where('campaign', $campaign->campaign_code);
                $campaign->attributed_leads = (clone $leadQuery)->count();
                $campaign->admitted_leads = (clone $leadQuery)->where('status', 'admitted')->count();
                $campaign->won_revenue = (float) TagoreCrmOpportunity::query()
                    ->where('institution_id', $campaign->institution_id)
                    ->where('campaign', $campaign->campaign_code)->where('status', 'won')->sum('amount');
                $campaign->cost_per_lead = $campaign->attributed_leads > 0
                    ? round((float) $campaign->spend / $campaign->attributed_leads, 2) : null;
                $campaign->roi_percent = (float) $campaign->spend > 0
                    ? round((($campaign->won_revenue - (float) $campaign->spend) / (float) $campaign->spend) * 100, 1) : null;
                return $campaign;
            });

        $tasks = TagoreCrmTask::query()->whereIn('institution_id', $institutionIds)
            ->with(['lead', 'opportunity', 'account', 'contact', 'assignee'])->orderByRaw("CASE WHEN status IN ('completed','cancelled') THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->orderByDesc('created_at')->limit(100)->get();
        $cases = TagoreCrmCase::query()->whereIn('institution_id', $institutionIds)
            ->with(['lead', 'student', 'parent', 'account', 'contact', 'assignee'])
            ->orderByRaw("CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->orderByDesc('created_at')->limit(100)->get();

        $accounts = TagoreCrmAccount::query()->whereIn('institution_id', $institutionIds)
            ->withCount(['contacts', 'opportunities'])->latest()->limit(100)->get();
        $contacts = TagoreCrmContact::query()->whereIn('institution_id', $institutionIds)
            ->with(['account', 'lead'])->latest()->limit(100)->get();
        $ownerIds = collect()
            ->merge($opportunities->pluck('assigned_to'))
            ->merge($leads->pluck('assigned_to'))
            ->merge($tasks->pluck('assigned_to'))
            ->merge($cases->pluck('assigned_to'))
            ->merge($accounts->pluck('owner_user_id'))
            ->merge($contacts->pluck('owner_user_id'))
            ->filter()->unique()->values()->all();
        $owners = $ownerIds ? DB::table('users')->whereIn('id', $ownerIds)->pluck('name', 'id') : collect();

        $metrics = [
            'open_opportunities' => TagoreCrmOpportunity::whereIn('institution_id', $institutionIds)->where('status', 'open')->count(),
            'weighted_pipeline' => round((float) TagoreCrmOpportunity::whereIn('institution_id', $institutionIds)->where('status', 'open')->selectRaw('coalesce(sum(amount * probability / 100),0) as total')->value('total'), 2),
            'won_value' => round((float) TagoreCrmOpportunity::whereIn('institution_id', $institutionIds)->where('status', 'won')->sum('amount'), 2),
            'active_campaigns' => TagoreCrmCampaign::whereIn('institution_id', $institutionIds)->where('status', 'active')->count(),
            'open_tasks' => TagoreCrmTask::whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_progress'])->count(),
            'overdue_tasks' => TagoreCrmTask::whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'accounts' => TagoreCrmAccount::whereIn('institution_id', $institutionIds)->count(),
            'contacts' => TagoreCrmContact::whereIn('institution_id', $institutionIds)->count(),
            'open_cases' => TagoreCrmCase::whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_progress', 'waiting_on_parent'])->count(),
            'overdue_cases' => TagoreCrmCase::whereIn('institution_id', $institutionIds)->whereIn('status', ['open', 'in_progress', 'waiting_on_parent'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
        ];

        return view('tagore.crm.growth', [
            'institutions' => $institutions,
            'accounts' => $accounts,
            'contacts' => $contacts,
            'staff' => $staff,
            'pipeline' => $pipeline,
            'opportunities' => $opportunities,
            'owners' => $owners,
            'campaigns' => $campaigns,
            'leads' => $leads,
            'topLeads' => $topLeads,
            'students' => $students,
            'parents' => $parents,
            'tasks' => $tasks,
            'cases' => $cases,
            'metrics' => $metrics,
            'canManageAccounts' => $roles->intersect(self::ACCOUNT_ROLES)->isNotEmpty(),
            'canManageContacts' => $roles->intersect(self::CONTACT_ROLES)->isNotEmpty(),
            'canManageOpportunities' => $roles->intersect(self::OPPORTUNITY_ROLES)->isNotEmpty(),
            'canManageCampaigns' => $roles->intersect(self::CAMPAIGN_ROLES)->isNotEmpty(),
            'canManageTasks' => $roles->intersect(self::TASK_ROLES)->isNotEmpty(),
            'canManageCases' => $roles->intersect(self::CASE_ROLES)->isNotEmpty(),
            'canEdit' => $roles->intersect(self::OPPORTUNITY_ROLES)->isNotEmpty(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        [$userId, , $institutionIds] = $this->context($request, self::ACCOUNT_ROLES);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'parent_account_id' => ['nullable', 'integer'],
            'account_type' => ['required', Rule::in(['family', 'organization', 'corporate', 'partner', 'vendor', 'school', 'agency'])],
            'name' => ['required', 'string', 'max:180'],
            'website' => ['nullable', 'url', 'max:500'],
            'industry' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'owner_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        if (!empty($data['parent_account_id'])) {
            $parent = TagoreCrmAccount::findOrFail((int) $data['parent_account_id']);
            abort_unless((int) $parent->institution_id === $institutionId, 422, 'Parent account belongs to another institution.');
        }
        if (!empty($data['owner_user_id'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['owner_user_id'], $institutionId), 422, 'Select an active account owner from this institution.');
        }
        $account = TagoreCrmAccount::create($data + ['created_by' => $userId]);
        $this->audit($request, $userId, $institutionId, 'crm.account.created', 'TagoreCrmAccount', $account->id, null, $account->only(['name', 'account_type', 'phone', 'email', 'owner_user_id']));

        return back()->with('success', 'CRM account created.');
    }

    public function storeContact(Request $request)
    {
        [$userId, , $institutionIds] = $this->context($request, self::CONTACT_ROLES);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'linked_user_id' => ['nullable', 'integer'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['parent', 'student', 'prospect', 'decision_maker', 'partner', 'vendor', 'other'])],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'preferred_channel' => ['required', Rule::in(['call', 'email', 'whatsapp'])],
            'contact_consent' => ['nullable', 'boolean'],
            'owner_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);

        if (!empty($data['account_id'])) {
            $account = TagoreCrmAccount::findOrFail((int) $data['account_id']);
            abort_unless((int) $account->institution_id === $institutionId, 422, 'The selected account belongs to another institution.');
        }
        if (!empty($data['lead_id'])) {
            $lead = TagoreAdmissionLead::findOrFail((int) $data['lead_id']);
            abort_unless((int) $lead->institution_id === $institutionId, 422, 'The selected lead belongs to another institution.');
            $data['email'] = $data['email'] ?: $lead->email;
            $data['phone'] = $data['phone'] ?: $lead->mobile;
        }
        if (!empty($data['linked_user_id'])) {
            $schoolId = (int) DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
            abort_unless(DB::table('users')->where('id', $data['linked_user_id'])->where('school_id', $schoolId)
                ->where('status', 'active')->whereNull('deleted_at')->exists(), 422, 'The linked user is not active in this institution.');
        }
        if (!empty($data['owner_user_id'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['owner_user_id'], $institutionId), 422, 'Select an active contact owner from this institution.');
        }
        $data['full_name'] = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $data['contact_consent'] = (bool) ($data['contact_consent'] ?? false);
        $contact = TagoreCrmContact::create($data + ['created_by' => $userId]);
        $this->audit($request, $userId, $institutionId, 'crm.contact.created', 'TagoreCrmContact', $contact->id, null, $contact->only(['full_name', 'contact_type', 'email', 'phone', 'account_id', 'lead_id', 'contact_consent']));

        return back()->with('success', 'CRM contact created and linked to its account or enquiry.');
    }
    public function updateAccount(Request $request, int $accountId)
    {
        [$userId, , $institutionIds] = $this->context($request, self::ACCOUNT_ROLES);
        $account = TagoreCrmAccount::findOrFail($accountId);
        abort_unless(in_array((int) $account->institution_id, $institutionIds, true), 403);
        $data = $request->validate([
            'parent_account_id' => ['nullable', 'integer'],
            'account_type' => ['required', Rule::in(['family', 'organization', 'corporate', 'partner', 'vendor', 'school', 'agency'])],
            'name' => ['required', 'string', 'max:180'],
            'website' => ['nullable', 'url', 'max:500'],
            'industry' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'owner_user_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (!empty($data['parent_account_id'])) {
            $parent = TagoreCrmAccount::findOrFail((int) $data['parent_account_id']);
            abort_unless((int) $parent->institution_id === (int) $account->institution_id && (int) $parent->id !== (int) $account->id, 422);
        }
        if (!empty($data['owner_user_id'])) abort_unless($this->staffBelongsToInstitution((int) $data['owner_user_id'], (int) $account->institution_id), 422);
        $before = $account->only(['name', 'account_type', 'phone', 'email', 'owner_user_id', 'status']);
        $account->update($data);
        $this->audit($request, $userId, (int) $account->institution_id, 'crm.account.updated', 'TagoreCrmAccount', $account->id, $before, $account->only(array_keys($before)));

        return back()->with('success', 'CRM account updated.');
    }

    public function updateContact(Request $request, int $contactId)
    {
        [$userId, , $institutionIds] = $this->context($request, self::CONTACT_ROLES);
        $contact = TagoreCrmContact::findOrFail($contactId);
        abort_unless(in_array((int) $contact->institution_id, $institutionIds, true), 403);
        $data = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['parent', 'student', 'prospect', 'decision_maker', 'partner', 'vendor', 'other'])],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'preferred_channel' => ['required', Rule::in(['call', 'email', 'whatsapp'])],
            'contact_consent' => ['nullable', 'boolean'],
            'owner_user_id' => ['nullable', 'integer'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (!empty($data['account_id'])) {
            $account = TagoreCrmAccount::findOrFail((int) $data['account_id']);
            abort_unless((int) $account->institution_id === (int) $contact->institution_id, 422);
        }
        if (!empty($data['lead_id'])) {
            $lead = TagoreAdmissionLead::findOrFail((int) $data['lead_id']);
            abort_unless((int) $lead->institution_id === (int) $contact->institution_id, 422);
            $data['email'] = ($data['email'] ?? null) ?: $lead->email;
            $data['phone'] = ($data['phone'] ?? null) ?: $lead->mobile;
        }
        if (!empty($data['owner_user_id'])) abort_unless($this->staffBelongsToInstitution((int) $data['owner_user_id'], (int) $contact->institution_id), 422);
        $data['full_name'] = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $data['contact_consent'] = (bool) ($data['contact_consent'] ?? false);
        $before = $contact->only(['full_name', 'contact_type', 'email', 'phone', 'account_id', 'lead_id', 'contact_consent', 'status']);
        $contact->update($data);
        $this->audit($request, $userId, (int) $contact->institution_id, 'crm.contact.updated', 'TagoreCrmContact', $contact->id, $before, $contact->only(array_keys($before)));

        return back()->with('success', 'CRM contact updated.');
    }
    public function storeOpportunity(Request $request)
    {
        [$userId, , $institutionIds] = $this->context($request, self::OPPORTUNITY_ROLES);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'opportunity_type' => ['required', Rule::in(['admission', 'hostel', 'coaching', 'college', 'partnership', 'corporate'])],
            'stage' => ['required', Rule::in(self::STAGES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:60'],
            'campaign' => ['nullable', 'string', 'max:100'],
            'lost_reason' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        if (!empty($data['lead_id'])) {
            $lead = TagoreAdmissionLead::whereKey((int) $data['lead_id'])->firstOrFail();
            abort_unless((int) $lead->institution_id === $institutionId, 422, 'The selected lead belongs to another institution.');
            $data['source'] = $data['source'] ?: $lead->source;
            $data['campaign'] = $data['campaign'] ?: $lead->campaign;
        }
        if (!empty($data['account_id'])) {
            $account = TagoreCrmAccount::findOrFail((int) $data['account_id']);
            abort_unless((int) $account->institution_id === $institutionId, 422, 'The selected account belongs to another institution.');
        }
        if (!empty($data['contact_id'])) {
            $contact = TagoreCrmContact::findOrFail((int) $data['contact_id']);
            abort_unless((int) $contact->institution_id === $institutionId, 422, 'The selected contact belongs to another institution.');
            if (!empty($data['account_id']) && (int) $contact->account_id !== (int) $data['account_id']) abort(422, 'The selected contact does not belong to this account.');
            if (empty($data['account_id']) && $contact->account_id) $data['account_id'] = $contact->account_id;
        }
        if (!empty($data['assigned_to'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['assigned_to'], $institutionId), 422, 'Select an active CRM team member from this institution.');
        }

        $stage = $data['stage'];
        if ($stage === 'won') $data['probability'] = 100;
        if ($stage === 'lost') $data['probability'] = 0;
        if ($stage === 'lost') $request->validate(['lost_reason' => ['required', 'string', 'max:180']]);
        $opportunity = TagoreCrmOpportunity::create($data + [
            'status' => in_array($stage, ['won', 'lost'], true) ? $stage : 'open',
            'created_by' => $userId,
        ]);
        $this->audit($request, $userId, $institutionId, 'crm.opportunity.created', 'TagoreCrmOpportunity', $opportunity->id, null, $opportunity->only(['name', 'stage', 'amount', 'probability', 'lead_id']));

        return back()->with('success', 'Opportunity added to the group CRM pipeline.');
    }

    public function updateOpportunity(Request $request, int $opportunityId)
    {
        [$userId, , $institutionIds] = $this->context($request, self::OPPORTUNITY_ROLES);
        $opportunity = TagoreCrmOpportunity::findOrFail($opportunityId);
        abort_unless(in_array((int) $opportunity->institution_id, $institutionIds, true), 403);

        $data = $request->validate([
            'stage' => ['required', Rule::in(self::STAGES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'lost_reason' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (!empty($data['account_id'])) {
            $account = TagoreCrmAccount::findOrFail((int) $data['account_id']);
            abort_unless((int) $account->institution_id === $institutionId, 422, 'The selected account belongs to another institution.');
        }
        if (!empty($data['contact_id'])) {
            $contact = TagoreCrmContact::findOrFail((int) $data['contact_id']);
            abort_unless((int) $contact->institution_id === $institutionId, 422, 'The selected contact belongs to another institution.');
            if (!empty($data['account_id']) && (int) $contact->account_id !== (int) $data['account_id']) abort(422, 'The selected contact does not belong to this account.');
            if (empty($data['account_id']) && $contact->account_id) $data['account_id'] = $contact->account_id;
        }
        if (!empty($data['assigned_to'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['assigned_to'], (int) $opportunity->institution_id), 422, 'Select an active CRM team member from this institution.');
        }
        if ($data['stage'] === 'lost') {
            $request->validate(['lost_reason' => ['required', 'string', 'max:180']]);
        }
        $before = $opportunity->only(['stage', 'status', 'amount', 'probability', 'assigned_to', 'expected_close_date']);
        if ($data['stage'] === 'won') $data['probability'] = 100;
        if ($data['stage'] === 'lost') $data['probability'] = 0;
        $data['lost_reason'] = $data['stage'] === 'lost' ? ($data['lost_reason'] ?? null) : null;
        $data['status'] = in_array($data['stage'], ['won', 'lost'], true) ? $data['stage'] : 'open';
        $opportunity->update($data);
        $this->audit($request, $userId, (int) $opportunity->institution_id, 'crm.opportunity.updated', 'TagoreCrmOpportunity', $opportunity->id, $before, $opportunity->only(['stage', 'status', 'amount', 'probability', 'assigned_to', 'expected_close_date']));

        return back()->with('success', 'Opportunity stage and forecast updated.');
    }

    public function storeCampaign(Request $request)
    {
        [$userId, , $institutionIds] = $this->context($request, self::CAMPAIGN_ROLES);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'campaign_code' => ['required', 'alpha_dash', 'max:100'],
            'channel' => ['required', Rule::in(['digital', 'social', 'search', 'referral', 'event', 'print', 'outbound_call', 'partner', 'other'])],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'completed'])],
            'objective' => ['nullable', 'string', 'max:120'],
            'budget' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'spend' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'landing_url' => ['nullable', 'url', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'owner_user_id' => ['nullable', 'integer'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        if (!empty($data['owner_user_id'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['owner_user_id'], $institutionId), 422, 'Select an active campaign owner from this institution.');
        }
        $exists = TagoreCrmCampaign::where('institution_id', $institutionId)->where('campaign_code', $data['campaign_code'])->exists();
        abort_unless(!$exists, 422, 'Campaign code already exists in this institution.');

        $campaign = TagoreCrmCampaign::create($data + ['created_by' => $userId]);
        $this->audit($request, $userId, $institutionId, 'crm.campaign.created', 'TagoreCrmCampaign', $campaign->id, null, $campaign->only(['name', 'campaign_code', 'channel', 'budget', 'spend', 'status']));

        return back()->with('success', 'Campaign created. Use its exact campaign code on enquiries for attribution.');
    }

    public function storeTask(Request $request)
    {
        [$userId, , $institutionIds] = $this->context($request, self::TASK_ROLES);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'task_type' => ['required', Rule::in(self::TASK_TYPES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);

        if (!empty($data['lead_id'])) {
            $lead = TagoreAdmissionLead::findOrFail((int) $data['lead_id']);
            abort_unless((int) $lead->institution_id === $institutionId, 422, 'The selected lead belongs to another institution.');
        }
        if (!empty($data['opportunity_id'])) {
            $opportunity = TagoreCrmOpportunity::findOrFail((int) $data['opportunity_id']);
            abort_unless((int) $opportunity->institution_id === $institutionId, 422, 'The selected opportunity belongs to another institution.');
            if (!empty($data['lead_id']) && (int) $opportunity->lead_id !== (int) $data['lead_id']) {
                abort(422, 'The selected lead and opportunity do not match.');
            }
        }
        if (!empty($data['account_id'])) {
            $account = TagoreCrmAccount::findOrFail((int) $data['account_id']);
            abort_unless((int) $account->institution_id === $institutionId, 422, 'The selected account belongs to another institution.');
        }
        if (!empty($data['contact_id'])) {
            $contact = TagoreCrmContact::findOrFail((int) $data['contact_id']);
            abort_unless((int) $contact->institution_id === $institutionId, 422, 'The selected contact belongs to another institution.');
            if (!empty($data['account_id']) && (int) $contact->account_id !== (int) $data['account_id']) abort(422, 'The selected contact does not belong to this account.');
            if (empty($data['account_id']) && $contact->account_id) $data['account_id'] = $contact->account_id;
        }
        if (!empty($data['assigned_to'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['assigned_to'], $institutionId), 422, 'Select an active CRM team member from this institution.');
        }

        $task = TagoreCrmTask::create($data + ['status' => 'open', 'created_by' => $userId]);
        $this->audit($request, $userId, $institutionId, 'crm.task.created', 'TagoreCrmTask', $task->id, null, $task->only(['title', 'task_type', 'priority', 'due_at', 'lead_id', 'opportunity_id', 'assigned_to']));
        return back()->with('success', 'CRM task created and assigned.');
    }

    public function updateTask(Request $request, int $taskId)
    {
        [$userId, , $institutionIds] = $this->context($request, self::TASK_ROLES);
        $task = TagoreCrmTask::findOrFail($taskId);
        abort_unless(in_array((int) $task->institution_id, $institutionIds, true), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'outcome' => ['nullable', 'string', 'max:60'],
        ]);
        if (!empty($data['assigned_to'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['assigned_to'], (int) $task->institution_id), 422, 'Select an active CRM team member from this institution.');
        }
        $wasCompleted = $task->status === 'completed';
        $before = $task->only(['status', 'priority', 'due_at', 'assigned_to', 'outcome']);
        $data['completed_at'] = $data['status'] === 'completed' ? ($task->completed_at ?: now()) : null;
        $task->update($data);

        if (!$wasCompleted && $data['status'] === 'completed' && $task->lead_id) {
            TagoreAdmissionActivity::create([
                'lead_id' => $task->lead_id,
                'user_id' => $userId,
                'type' => in_array($task->task_type, ['call', 'email', 'meeting', 'visit'], true) ? $task->task_type : 'note',
                'outcome' => $data['outcome'] ?: 'task_completed',
                'notes' => $task->title.($task->description ? ': '.$task->description : ''),
                'scheduled_at' => $task->due_at,
                'completed_at' => now(),
            ]);
        }
        $this->audit($request, $userId, (int) $task->institution_id, 'crm.task.updated', 'TagoreCrmTask', $task->id, $before, $task->only(['status', 'priority', 'due_at', 'assigned_to', 'outcome']));
        return back()->with('success', 'CRM task updated.');
    }

    public function storeCase(Request $request)
    {
        [$userId, , $institutionIds] = $this->context($request, self::CASE_ROLES);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'parent_user_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(self::CASE_CATEGORIES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);
        abort_unless(!empty($data['lead_id']) || !empty($data['student_id']) || !empty($data['parent_user_id']) || !empty($data['account_id']) || !empty($data['contact_id']), 422, 'Link this case to a lead, student, parent, account or contact.');

        if (!empty($data['account_id'])) {
            $account = TagoreCrmAccount::findOrFail((int) $data['account_id']);
            abort_unless((int) $account->institution_id === $institutionId, 422, 'The selected account belongs to another institution.');
        }
        if (!empty($data['contact_id'])) {
            $contact = TagoreCrmContact::findOrFail((int) $data['contact_id']);
            abort_unless((int) $contact->institution_id === $institutionId, 422, 'The selected contact belongs to another institution.');
            if (!empty($data['account_id']) && (int) $contact->account_id !== (int) $data['account_id']) abort(422, 'The selected contact does not belong to this account.');
            if (empty($data['account_id']) && $contact->account_id) $data['account_id'] = $contact->account_id;
        }
        if (!empty($data['lead_id'])) {
            $lead = TagoreAdmissionLead::findOrFail((int) $data['lead_id']);
            abort_unless((int) $lead->institution_id === $institutionId, 422, 'The selected lead belongs to another institution.');
        }

        $schoolId = (int) DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
        if (!empty($data['student_id'])) {
            abort_unless(DB::table('users')->where('id', $data['student_id'])->where('usergroup_id', 6)
                ->where('school_id', $schoolId)->where('status', 'active')->whereNull('deleted_at')->exists(), 422, 'Select an active student from this institution.');
        }
        if (!empty($data['parent_user_id'])) {
            $parentLink = DB::table('tagore_parent_students as ps')->join('users as child', 'child.id', '=', 'ps.student_id')
                ->where('ps.parent_user_id', $data['parent_user_id'])->where('ps.status', 'active')
                ->where('child.school_id', $schoolId)->where('child.status', 'active')->whereNull('child.deleted_at');
            if (!empty($data['student_id'])) $parentLink->where('ps.student_id', $data['student_id']);
            abort_unless($parentLink->exists(), 422, 'The selected parent is not actively linked to a student in this institution.');
        }
        if (!empty($data['assigned_to'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['assigned_to'], $institutionId), 422, 'Select an active CRM team member from this institution.');
        }

        $case = TagoreCrmCase::create($data + ['status' => 'open', 'created_by' => $userId]);
        $this->audit($request, $userId, $institutionId, 'crm.case.created', 'TagoreCrmCase', $case->id, null, $case->only(['subject', 'category', 'priority', 'status', 'lead_id', 'student_id', 'parent_user_id', 'assigned_to']));
        return back()->with('success', 'Support case opened and assigned.');
    }

    public function updateCase(Request $request, int $caseId)
    {
        [$userId, , $institutionIds] = $this->context($request, self::CASE_ROLES);
        $case = TagoreCrmCase::findOrFail($caseId);
        abort_unless(in_array((int) $case->institution_id, $institutionIds, true), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::CASE_STATUSES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
            'resolution' => ['nullable', 'string', 'max:10000'],
        ]);
        if (!empty($data['assigned_to'])) {
            abort_unless($this->staffBelongsToInstitution((int) $data['assigned_to'], (int) $case->institution_id), 422, 'Select an active CRM team member from this institution.');
        }
        if (in_array($data['status'], ['resolved', 'closed'], true)) {
            $request->validate(['resolution' => ['required', 'string', 'max:10000']]);
            $data['resolved_at'] = $case->resolved_at ?: now();
        } else {
            $data['resolved_at'] = null;
        }
        $before = $case->only(['status', 'priority', 'assigned_to', 'due_at', 'resolution']);
        $case->update($data);
        $this->audit($request, $userId, (int) $case->institution_id, 'crm.case.updated', 'TagoreCrmCase', $case->id, $before, $case->only(['status', 'priority', 'assigned_to', 'due_at', 'resolution']));
        return back()->with('success', 'Support case updated.');
    }

    private function staffBelongsToInstitution(int $userId, int $institutionId): bool
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where(function ($query) use ($institutionId) {
                $query->where('ur.institution_id', $institutionId)
                    ->orWhere(fn ($global) => $global->whereNull('ur.institution_id')->where('r.code', 'OWNER'));
            })
            ->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->whereIn('r.code', self::STAFF_ROLES)->exists();
    }

    private function audit(Request $request, int $userId, int $institutionId, string $action, string $entity, int $entityId, ?array $before, array $after): void
    {
        DB::table('tagore_audit_events')->insert([
            'user_id' => $userId,
            'institution_id' => $institutionId,
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'old_values_json' => $before ? json_encode($before) : null,
            'new_values_json' => json_encode($after),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
