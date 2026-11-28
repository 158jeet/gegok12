<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreAdmissionActivity;
use App\Models\TagoreAdmissionLead;
use App\Models\TagoreCrmCall;
use App\Models\TagoreCrmTask;
use App\Models\TagoreCrmTelephonyAccount;
use App\Models\TagoreCrmTelephonyAgent;
use App\Services\Tagore\CrmTelephonyService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CrmTelephonyController extends Controller
{
    private const STAFF_ROLES = ['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CRM_MANAGER', 'ADMISSIONS', 'TELECALLER', 'MARKETING'];

    private function roleCodes(int $userId)
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('r.status', 'active')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')->unique()->values();
    }

    private function institutionIds(int $userId, $roles): array
    {
        if ($roles->contains('OWNER')) {
            return DB::table('tagore_institutions')->where('status', 'active')->whereNull('deleted_at')
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'ur.institution_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')->where('r.status', 'active')
            ->where('i.status', 'active')->whereNull('i.deleted_at')->whereNotNull('ur.institution_id')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->distinct()->pluck('ur.institution_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function authorizeStaff(Request $request, bool $ownerOnly = false): array
    {
        $roles = $this->roleCodes((int) $request->user()->id);
        abort_unless(
            $ownerOnly ? $roles->contains('OWNER') : $roles->intersect(self::STAFF_ROLES)->isNotEmpty(),
            403
        );
        $institutionIds = $this->institutionIds((int) $request->user()->id, $roles);
        abort_unless($institutionIds !== [], 403);
        return [$roles, $institutionIds];
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

    public function index(Request $request)
    {
        [$roles, $institutionIds] = $this->authorizeStaff($request);
        $isOwner = $roles->contains('OWNER');
        $canManageAgents = $roles->intersect(['OWNER', 'CRM_MANAGER'])->isNotEmpty();
        $canListenRecordings = $roles->intersect(['OWNER', 'CRM_MANAGER'])->isNotEmpty();

        $accounts = TagoreCrmTelephonyAccount::whereIn('institution_id', $institutionIds)
            ->orderBy('institution_id')->orderByDesc('id')->get();
        $calls = TagoreCrmCall::whereIn('institution_id', $institutionIds)
            ->with('lead')->latest()->limit(100)->get();
        $agents = TagoreCrmTelephonyAgent::whereIn('institution_id', $institutionIds)
            ->with(['account', 'user'])->orderBy('institution_id')->orderBy('user_id')->get();
        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)
            ->orderBy('display_name')->get(['id', 'display_name']);
        $staff = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where(function ($query) use ($institutionIds) {
                $query->whereIn('ur.institution_id', $institutionIds)
                    ->orWhere(fn ($global) => $global->whereNull('ur.institution_id')->where('r.code', 'OWNER'));
            })
            ->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->whereIn('r.code', self::STAFF_ROLES)->whereNull('u.deleted_at')
            ->select('u.id', 'u.name')->distinct()->orderBy('u.name')->get();

        return view('tagore.crm.telephony', compact('accounts', 'calls', 'agents', 'institutions', 'staff', 'isOwner', 'canManageAgents', 'canListenRecordings'));
    }

    public function saveAccount(Request $request)
    {
        [, $institutionIds] = $this->authorizeStaff($request, true);
        $data = $request->validate([
            'institution_id' => ['required', 'integer'],
            'account_sid' => ['required', 'string', 'max:120'],
            'api_key' => ['required', 'string', 'max:255'],
            'api_token' => ['required', 'string', 'max:255'],
            'caller_id' => ['required', 'string', 'max:40'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'record_calls' => ['nullable', 'boolean'],
        ]);
        $institutionId = (int) $data['institution_id'];
        abort_unless(in_array($institutionId, $institutionIds, true), 403);

        $baseUrl = $data['api_base_url'] ?? 'https://api.in.exotel.com';
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        abort_unless(parse_url($baseUrl, PHP_URL_SCHEME) === 'https' && preg_match('/(^|\.)exotel\.com$/', $host), 422, 'Use a valid HTTPS Exotel API endpoint.');

        $token = Str::random(48);
        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => $institutionId,
            'provider' => 'exotel',
            'account_sid' => $data['account_sid'],
            'credentials' => [
                'api_key' => $data['api_key'],
                'api_token' => $data['api_token'],
                'api_base_url' => $baseUrl,
                'webhook_token' => $token,
            ],
            'caller_id' => $data['caller_id'],
            'record_calls' => (bool) ($data['record_calls'] ?? false),
            'webhook_token_hash' => hash('sha256', $token),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $webhookUrl = route('tagore.crm.telephony.webhook', ['accountId' => $account->id, 'token' => $token], true);
        return back()->with('success', 'Exotel account saved. API credentials are encrypted at rest.')
            ->with('webhook_url_once', $webhookUrl);
    }

    public function saveAgent(Request $request)
    {
        [$roles, $institutionIds] = $this->authorizeStaff($request);
        abort_unless($roles->intersect(['OWNER', 'CRM_MANAGER'])->isNotEmpty(), 403);
        $data = $request->validate([
            'telephony_account_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'agent_number' => ['required', 'string', 'max:40', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account = TagoreCrmTelephonyAccount::whereKey((int) $data['telephony_account_id'])
            ->whereIn('institution_id', $institutionIds)->where('is_active', true)->firstOrFail();
        abort_unless($this->staffBelongsToInstitution((int) $data['user_id'], (int) $account->institution_id), 422, 'Select an active CRM team member from the account institution.');

        $digits = preg_replace('/\D+/', '', $data['agent_number']);
        $normalizedAgentNumber = strlen($digits) === 10 ? '+91'.$digits : ((strlen($digits) === 12 && str_starts_with($digits, '91')) ? '+'.$digits : '+'.$digits);

        $agent = TagoreCrmTelephonyAgent::updateOrCreate(
            ['telephony_account_id' => $account->id, 'user_id' => (int) $data['user_id']],
            [
                'institution_id' => $account->institution_id,
                'agent_number' => $normalizedAgentNumber,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'created_by' => $request->user()->id,
            ],
        );
        DB::table('tagore_audit_events')->insert([
            'user_id' => $request->user()->id,
            'institution_id' => $account->institution_id,
            'action' => 'CRM_TELEPHONY_AGENT_CONFIGURED',
            'entity_type' => 'tagore_crm_telephony_agents',
            'entity_id' => $agent->id,
            'old_values_json' => null,
            'new_values_json' => json_encode(['user_id' => $agent->user_id, 'agent_number' => $agent->agent_number, 'is_active' => $agent->is_active]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Calling agent number securely assigned to the selected CRM user.');
    }

    public function clickToCall(Request $request, int $leadId, CrmTelephonyService $service)
    {
        [, $institutionIds] = $this->authorizeStaff($request);
        $lead = TagoreAdmissionLead::findOrFail($leadId);
        abort_unless(in_array((int) $lead->institution_id, $institutionIds, true), 403);
        abort_unless($lead->mobile, 422, 'This lead has no mobile number.');

        $agent = TagoreCrmTelephonyAgent::where('institution_id', $lead->institution_id)
            ->where('user_id', $request->user()->id)->where('is_active', true)
            ->with('account')->orderByDesc('id')->first();
        $account = $agent?->account;
        if (!$agent || !$account || !$account->is_active || $account->provider !== 'exotel') {
            return back()->withErrors(['telephony' => 'Your calling agent number is not configured on an active Exotel account. Ask the Owner or CRM Manager to register it in Cloud calling.']);
        }

        try {
            $call = $service->initiateExotel($account, $lead, $agent->agent_number, (int) $request->user()->id);
        } catch (RuntimeException|ConnectionException $exception) {
            return back()->withErrors(['telephony' => $exception->getMessage()]);
        }

        TagoreAdmissionActivity::create([
            'lead_id' => $lead->id, 'user_id' => $request->user()->id,
            'type' => 'call', 'outcome' => 'call_initiated',
            'notes' => 'Outbound cloud call initiated. Call reference #'.$call->id,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Call request accepted by the telephony provider.');
    }

    public function webhook(Request $request, int $accountId, string $token)
    {
        $account = TagoreCrmTelephonyAccount::where('is_active', true)->findOrFail($accountId);
        abort_unless($account->provider === 'exotel', 404);
        abort_unless(hash_equals((string) $account->webhook_token_hash, hash('sha256', $token)), 403);
        abort_unless($account->is_active, 403, 'Telephony account is inactive.');

        $providerId = trim((string) ($request->input('CallSid') ?? $request->input('Sid') ?? $request->input('call_sid') ?? ''));
        abort_unless($providerId !== '', 422, 'The provider call reference is required for idempotent call logging.');
        $status = strtolower((string) ($request->input('Status') ?? $request->input('CallStatus') ?? $request->input('status') ?? 'unknown'));
        $status = str_replace('_', '-', $status);
        $duration = max(0, (int) ($request->input('ConversationDuration') ?? $request->input('Duration') ?? $request->input('duration') ?? 0));
        $rawRecordingUrl = $request->input('RecordingUrl') ?? $request->input('recording_url');
        $recordingUrl = null;
        if ($account->record_calls && is_string($rawRecordingUrl) && filter_var($rawRecordingUrl, FILTER_VALIDATE_URL)) {
            $parts = parse_url($rawRecordingUrl);
            if (($parts['scheme'] ?? '') === 'https') $recordingUrl = $rawRecordingUrl;
        }

        $existing = TagoreCrmCall::where('telephony_account_id', $account->id)->where('provider_call_id', $providerId)->first();
        $from = (string) ($request->input('From') ?? $request->input('from') ?? '');
        $to = (string) ($request->input('To') ?? $request->input('to') ?? '');
        $lead = $existing?->lead;
        if (!$existing && $from !== '') {
            $digits = preg_replace('/\D+/', '', $from);
            if (strlen($digits) >= 10) {
                $lastTen = substr($digits, -10);
                $lead = TagoreAdmissionLead::where('institution_id', $account->institution_id)
                    ->whereRaw("substr(replace(replace(replace(replace(replace(mobile, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), -10) = ?", [$lastTen])
                    ->latest('id')->first();
            }
        }

        $providerPayload = $request->only(['CallSid', 'Sid', 'Status', 'CallStatus', 'ConversationDuration', 'Duration', 'From', 'To', 'Direction']);
        if ($account->record_calls && $recordingUrl) $providerPayload['RecordingUrl'] = $recordingUrl;

        $call = TagoreCrmCall::updateOrCreate(
            ['telephony_account_id' => $account->id, 'provider_call_id' => $providerId],
            [
                'institution_id' => $account->institution_id,
                'lead_id' => $existing?->lead_id ?: $lead?->id,
                'direction' => $existing?->direction ?: 'inbound',
                'from_number' => $from ?: $existing?->from_number,
                'to_number' => $to ?: $existing?->to_number,
                'status' => $status,
                'duration_seconds' => $duration ?: ($existing?->duration_seconds ?? 0),
                'started_at' => $existing?->started_at ?: now(),
                'answered_at' => in_array($status, ['in-progress', 'answered'], true) ? ($existing?->answered_at ?: now()) : $existing?->answered_at,
                'ended_at' => in_array($status, ['completed', 'failed', 'busy', 'no-answer', 'canceled', 'cancelled', 'missed'], true) ? ($existing?->ended_at ?: now()) : $existing?->ended_at,
                'recording_url' => $account->record_calls ? ($recordingUrl ?: $existing?->recording_url) : null,
                'provider_payload' => $providerPayload,
            ],
        );

        $terminal = in_array($status, ['completed', 'failed', 'busy', 'no-answer', 'canceled', 'cancelled', 'missed'], true);
        if ($call->lead_id && $terminal) {
            $lead = TagoreAdmissionLead::find($call->lead_id);
            if ($lead) {
                $lead->update(['last_contacted_at' => now()]);
                TagoreAdmissionActivity::firstOrCreate(
                    [
                        'lead_id' => $lead->id,
                        'type' => 'call',
                        'outcome' => 'provider_'.Str::slug($status),
                        'notes' => 'Cloud call status: '.$status.'; provider reference '.$providerId,
                    ],
                    ['completed_at' => now()],
                );
            }
        }

        $missed = in_array($status, ['failed', 'busy', 'no-answer', 'canceled', 'cancelled', 'missed'], true)
            || ($status === 'completed' && $duration === 0);
        if ($missed && $call->lead_id) {
            $lead = TagoreAdmissionLead::find($call->lead_id);
            TagoreCrmTask::firstOrCreate(
                ['call_id' => $call->id],
                [
                    'institution_id' => $account->institution_id,
                    'lead_id' => $call->lead_id,
                    'assigned_to' => $call->initiated_by ?: $lead?->assigned_to,
                    'created_by' => null,
                    'title' => ($call->direction === 'inbound' ? 'Return missed call: ' : 'Retry unanswered call: ').($lead?->student_name ?: $call->from_number),
                    'task_type' => 'call',
                    'priority' => 'high',
                    'status' => 'open',
                    'due_at' => now()->addHour(),
                    'description' => 'Automatic callback task from provider call reference '.$providerId.'.',
                ],
            );
        }

        return response()->json(['ok' => true]);
    }

    public function disposition(Request $request, int $callId)
    {
        [, $institutionIds] = $this->authorizeStaff($request);
        $data = $request->validate([
            'disposition' => ['required', 'in:connected,callback,interested,not_interested,wrong_number,admission_confirmed'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
        ]);
        $call = TagoreCrmCall::findOrFail($callId);
        abort_unless(in_array((int) $call->institution_id, $institutionIds, true), 403);
        $call->update(['disposition' => $data['disposition']]);
        if ($data['disposition'] === 'callback' && empty($data['next_follow_up_at'])) {
            $data['next_follow_up_at'] = now()->addDay()->toDateTimeString();
        }

        if ($call->lead_id) {
            $lead = TagoreAdmissionLead::findOrFail($call->lead_id);
            if (!empty($data['next_follow_up_at'])) {
                $lead->update(['next_follow_up_at' => $data['next_follow_up_at'], 'status' => 'follow_up']);
                TagoreCrmTask::updateOrCreate(
                    ['call_id' => $call->id],
                    [
                        'institution_id' => $call->institution_id,
                        'lead_id' => $lead->id,
                        'assigned_to' => $lead->assigned_to ?: $request->user()->id,
                        'created_by' => $request->user()->id,
                        'title' => 'Call back: '.$lead->student_name,
                        'task_type' => 'call',
                        'priority' => $data['disposition'] === 'callback' ? 'high' : 'normal',
                        'status' => 'open',
                        'due_at' => $data['next_follow_up_at'],
                        'description' => $data['notes'] ?? 'Follow-up generated from call disposition.',
                    ],
                );
            }
            TagoreAdmissionActivity::create([
                'lead_id' => $lead->id, 'user_id' => $request->user()->id,
                'type' => 'call', 'outcome' => $data['disposition'],
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $data['next_follow_up_at'] ?? null,
                'completed_at' => now(),
            ]);
        }

        return back()->with('success', 'Call disposition saved and any requested callback added to the CRM task queue.');
    }
}
