<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Models\TagoreAdmissionActivity;
use App\Models\TagoreAdmissionLead;
use App\Models\TagoreCrmCall;
use App\Models\TagoreCrmTelephonyAccount;
use App\Services\Tagore\CrmTelephonyService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CrmTelephonyController extends Controller
{
    private function roleCodes(int $userId)
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->pluck('r.code');
    }

    private function institutionIds(int $userId, $roles): array
    {
        if ($roles->contains('OWNER')) {
            return DB::table('tagore_institutions')->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')
            ->pluck('institution_id')->map(fn ($id) => (int) $id)->unique()->all();
    }

    private function authorizeStaff(Request $request, bool $ownerOnly = false): array
    {
        $roles = $this->roleCodes((int) $request->user()->id);
        abort_unless($ownerOnly ? $roles->contains('OWNER') : $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'CASHIER'])->isNotEmpty(), 403);
        return [$roles, $this->institutionIds((int) $request->user()->id, $roles)];
    }

    public function index(Request $request)
    {
        [$roles, $institutionIds] = $this->authorizeStaff($request);
        $isOwner = $roles->contains('OWNER');
        $accounts = TagoreCrmTelephonyAccount::whereIn('institution_id', $institutionIds)
            ->orderBy('institution_id')->orderByDesc('id')->get();
        $calls = TagoreCrmCall::whereIn('institution_id', $institutionIds)
            ->with('lead')->latest()->limit(100)->get();
        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)->orderBy('display_name')->get(['id', 'display_name']);

        return view('tagore.crm.telephony', compact('accounts', 'calls', 'institutions', 'isOwner'));
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
        ]);
        abort_unless(in_array((int) $data['institution_id'], $institutionIds, true), 403);

        $baseUrl = $data['api_base_url'] ?? 'https://api.exotel.com';
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        abort_unless(parse_url($baseUrl, PHP_URL_SCHEME) === 'https' && preg_match('/(^|\\.)exotel\\.com$/', $host), 422, 'Use a valid HTTPS Exotel API endpoint.');

        $token = Str::random(48);
        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => (int) $data['institution_id'],
            'provider' => 'exotel',
            'account_sid' => $data['account_sid'],
            'credentials' => [
                'api_key' => $data['api_key'],
                'api_token' => $data['api_token'],
                'api_base_url' => $baseUrl,
                'webhook_token' => $token,
            ],
            'caller_id' => $data['caller_id'],
            'webhook_token_hash' => hash('sha256', $token),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $webhookUrl = route('tagore.crm.telephony.webhook', ['accountId' => $account->id, 'token' => $token], true);
        return back()->with('success', 'Exotel account saved. API credentials are encrypted at rest.')
            ->with('webhook_url_once', $webhookUrl);
    }

    public function clickToCall(Request $request, int $leadId, CrmTelephonyService $service)
    {
        [, $institutionIds] = $this->authorizeStaff($request);
        $lead = TagoreAdmissionLead::findOrFail($leadId);
        abort_unless(in_array((int) $lead->institution_id, $institutionIds, true), 403);
        abort_unless($lead->mobile, 422, 'This lead has no mobile number.');

        $data = $request->validate([
            'agent_number' => ['required', 'regex:/^\\+?[1-9]\\d{7,14}$/'],
        ]);
        $account = TagoreCrmTelephonyAccount::where('institution_id', $lead->institution_id)
            ->where('is_active', true)->latest()->firstOrFail();

        try {
            $call = $service->initiateExotel($account, $lead, $data['agent_number'], (int) $request->user()->id);
        } catch (RuntimeException|ConnectionException $exception) {
            return back()->withErrors(['telephony' => $exception->getMessage()]);
        }

        TagoreAdmissionActivity::create([
            'lead_id' => $lead->id, 'user_id' => $request->user()->id,
            'type' => 'call', 'outcome' => 'call_initiated',
            'notes' => 'Outbound cloud call initiated. Call reference #' . $call->id,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Call request accepted by the telephony provider.');
    }

    public function webhook(Request $request, int $accountId, string $token)
    {
        $account = TagoreCrmTelephonyAccount::findOrFail($accountId);
        abort_unless(hash_equals($account->webhook_token_hash, hash('sha256', $token)), 403);

        $providerId = (string) ($request->input('CallSid') ?? $request->input('Sid') ?? $request->input('call_sid') ?? '');
        $status = strtolower((string) ($request->input('Status') ?? $request->input('CallStatus') ?? $request->input('status') ?? 'unknown'));
        $duration = max(0, (int) ($request->input('ConversationDuration') ?? $request->input('Duration') ?? $request->input('duration') ?? 0));
        $recordingUrl = $request->input('RecordingUrl') ?? $request->input('recording_url');

        $call = $providerId !== ''
            ? TagoreCrmCall::where('telephony_account_id', $account->id)->where('provider_call_id', $providerId)->first()
            : null;

        if (!$call) {
            $from = (string) ($request->input('From') ?? $request->input('from') ?? '');
            $to = (string) ($request->input('To') ?? $request->input('to') ?? '');
            $mobile = preg_replace('/\\D+/', '', $from);
            $lead = null;
            if ($mobile !== '') {
                $lastTen = substr($mobile, -10);
                $lead = TagoreAdmissionLead::where('institution_id', $account->institution_id)
                    ->get()->first(function ($candidate) use ($lastTen) {
                        $candidateMobile = preg_replace('/\\D+/', '', (string) $candidate->mobile);
                        return substr($candidateMobile, -10) === $lastTen;
                    });
            }

            $call = TagoreCrmCall::create([
                'institution_id' => $account->institution_id,
                'telephony_account_id' => $account->id,
                'lead_id' => $lead?->id,
                'provider_call_id' => $providerId ?: null,
                'direction' => 'inbound',
                'from_number' => $from,
                'to_number' => $to,
                'status' => $status,
                'duration_seconds' => $duration,
                'started_at' => now(),
                'ended_at' => in_array($status, ['completed', 'failed', 'busy', 'no-answer'], true) ? now() : null,
                'recording_url' => $recordingUrl,
                'provider_payload' => $request->except(['token']),
            ]);
        } else {
            $call->update([
                'status' => $status,
                'duration_seconds' => $duration ?: $call->duration_seconds,
                'answered_at' => in_array($status, ['in-progress', 'answered'], true) ? ($call->answered_at ?: now()) : $call->answered_at,
                'ended_at' => in_array($status, ['completed', 'failed', 'busy', 'no-answer', 'canceled'], true) ? now() : $call->ended_at,
                'recording_url' => $recordingUrl ?: $call->recording_url,
                'provider_payload' => $request->except(['token']),
            ]);
        }

        if ($call->lead_id && in_array($status, ['completed', 'failed', 'busy', 'no-answer', 'canceled'], true)) {
            $lead = TagoreAdmissionLead::find($call->lead_id);
            if ($lead) {
                $lead->update(['last_contacted_at' => now()]);
                TagoreAdmissionActivity::firstOrCreate([
                    'lead_id' => $lead->id, 'type' => 'call',
                    'outcome' => 'provider_' . Str::slug($status),
                    'notes' => 'Cloud call status: ' . $status,
                ], ['completed_at' => now()]);
            }
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

        if ($call->lead_id) {
            $lead = TagoreAdmissionLead::findOrFail($call->lead_id);
            if (!empty($data['next_follow_up_at'])) {
                $lead->update(['next_follow_up_at' => $data['next_follow_up_at'], 'status' => 'follow_up']);
            }
            TagoreAdmissionActivity::create([
                'lead_id' => $lead->id, 'user_id' => $request->user()->id,
                'type' => 'call', 'outcome' => $data['disposition'],
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $data['next_follow_up_at'] ?? null,
                'completed_at' => now(),
            ]);
        }

        return back()->with('success', 'Call disposition saved to the lead timeline.');
    }
}
