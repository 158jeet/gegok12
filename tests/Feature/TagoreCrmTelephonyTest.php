<?php

namespace Tests\Feature;

use App\Models\TagoreCrmTelephonyAccount;
use App\Models\TagoreCrmTelephonyAgent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagoreCrmTelephonyTest extends TestCase
{
    private function owner(): User
    {
        $ownerId = DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('r.code', 'OWNER')
            ->where('ur.status', 'active')
            ->value('ur.user_id');

        return User::query()->findOrFail($ownerId);
    }

    public function test_owner_can_save_exotel_account_with_encrypted_credentials(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');

        $response = $this->actingAs($owner)->post(route('tagore.crm.telephony.save'), [
            'institution_id' => $institutionId,
            'account_sid' => 'tagore-test-sid',
            'api_key' => 'test-api-key-secret',
            'api_token' => 'test-api-token-secret',
            'caller_id' => '+911234567890',
            'api_base_url' => 'https://api.exotel.com',
        ]);

        $response->assertRedirect()->assertSessionHas('webhook_url_once');
        $account = TagoreCrmTelephonyAccount::query()->where('institution_id', $institutionId)->latest()->firstOrFail();
        $rawCredentials = (string) DB::table('tagore_crm_telephony_accounts')->where('id', $account->id)->value('credentials');

        $this->assertStringNotContainsString('test-api-key-secret', $rawCredentials);
        $this->assertStringNotContainsString('test-api-token-secret', $rawCredentials);
        $this->assertSame('test-api-key-secret', $account->credentials['api_key']);
        $this->assertNotEmpty($account->credentials['webhook_token']);
        $this->assertSame(hash('sha256', $account->credentials['webhook_token']), $account->webhook_token_hash);
    }

    public function test_staff_can_click_to_call_and_webhook_updates_the_lead_timeline(): void
    {
        $owner = $this->owner();
        $institution = DB::table('tagore_institutions')->where('status', 'active')->first();
        $this->assertNotNull($institution);

        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id,
            'lead_no' => 'QA-CALL-' . uniqid(),
            'student_name' => 'Cloud Call Acceptance Lead',
            'mobile' => '+919876543210',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = Str::random(48);
        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => $institution->id,
            'provider' => 'exotel',
            'account_sid' => 'tagore-test-sid',
            'credentials' => [
                'api_key' => 'test-api-key',
                'api_token' => 'test-api-token',
                'api_base_url' => 'https://api.exotel.com',
                'webhook_token' => $token,
            ],
            'caller_id' => '+911234567890',
            'webhook_token_hash' => hash('sha256', $token),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('tagore.crm.telephony.agents.save'), [
            'telephony_account_id' => $account->id,
            'user_id' => $owner->id,
            'agent_number' => '+919999999999',
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        Http::fake([
            'https://api.exotel.com/*' => Http::response(['Call' => ['Sid' => 'EXOTEL-CALL-001']], 200),
        ]);

        $this->actingAs($owner)->post(route('tagore.crm.telephony.call', $leadId))->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('tagore_crm_calls', [
            'telephony_account_id' => $account->id,
            'lead_id' => $leadId,
            'provider_call_id' => 'EXOTEL-CALL-001',
            'status' => 'initiated',
        ]);

        $this->postJson(route('tagore.crm.telephony.webhook', [
            'accountId' => $account->id,
            'token' => $token,
        ]), [
            'CallSid' => 'EXOTEL-CALL-001',
            'Status' => 'completed',
            'Duration' => 63,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tagore_crm_calls', [
            'telephony_account_id' => $account->id,
            'provider_call_id' => 'EXOTEL-CALL-001',
            'status' => 'completed',
            'duration_seconds' => 63,
        ]);
        $this->assertDatabaseHas('tagore_admission_activities', [
            'lead_id' => $leadId,
            'type' => 'call',
            'outcome' => 'provider_completed',
        ]);        $this->postJson(route('tagore.crm.telephony.webhook', ['accountId' => $account->id, 'token' => $token]), [
            'CallSid' => 'EXOTEL-CALL-MISSED-002', 'Status' => 'no-answer', 'Duration' => 0,
            'From' => '+919876543210',
        ])->assertOk();
        $missedCallId = (int) DB::table('tagore_crm_calls')->where('provider_call_id', 'EXOTEL-CALL-MISSED-002')->value('id');
        $this->assertGreaterThan(0, $missedCallId);
        $this->assertDatabaseHas('tagore_crm_tasks', [
            'call_id' => $missedCallId, 'lead_id' => $leadId, 'status' => 'open', 'priority' => 'high',
        ]);
        $this->postJson(route('tagore.crm.telephony.webhook', ['accountId' => $account->id, 'token' => $token]), [
            'CallSid' => 'EXOTEL-CALL-MISSED-002', 'Status' => 'no-answer', 'Duration' => 0,
            'From' => '+919876543210',
        ])->assertOk();
        $this->assertSame(1, DB::table('tagore_crm_tasks')->where('call_id', $missedCallId)->count());
    }

    public function test_staff_cannot_click_to_call_without_a_registered_agent_number(): void
    {
        $owner = $this->owner();
        $institution = DB::table('tagore_institutions')->where('status', 'active')->first();
        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id,
            'lead_no' => 'QA-CALL-NO-AGENT-' . uniqid(),
            'student_name' => 'No Agent Mapping Lead',
            'mobile' => '+919876543210',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => $institution->id,
            'provider' => 'exotel',
            'account_sid' => 'tagore-no-agent-sid',
            'credentials' => ['api_key' => 'key', 'api_token' => 'token', 'api_base_url' => 'https://api.exotel.com'],
            'caller_id' => '+911234567890',
            'webhook_token_hash' => hash('sha256', 'no-agent-token'),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('tagore.crm.telephony.call', $leadId))
            ->assertRedirect()->assertSessionHasErrors('telephony');
        $this->assertDatabaseMissing('tagore_crm_calls', ['telephony_account_id' => $account->id, 'lead_id' => $leadId]);
    }

    public function test_missed_inbound_call_creates_only_one_callback_task(): void
    {
        $owner = $this->owner();
        $institution = DB::table('tagore_institutions')->where('status', 'active')->first();
        $leadId = DB::table('tagore_admission_leads')->insertGetId([
            'institution_id' => $institution->id,
            'lead_no' => 'QA-CALL-MISSED-' . uniqid(),
            'student_name' => 'Missed Inbound Lead',
            'mobile' => '+919876543210',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = Str::random(48);
        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => $institution->id,
            'provider' => 'exotel',
            'account_sid' => 'tagore-missed-call-sid',
            'credentials' => ['api_key' => 'key', 'api_token' => 'token', 'webhook_token' => $token],
            'caller_id' => '+911234567890',
            'webhook_token_hash' => hash('sha256', $token),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $payload = [
            'CallSid' => 'EXOTEL-MISSED-001',
            'Status' => 'no-answer',
            'Duration' => 0,
            'From' => '+919876543210',
            'To' => '+911234567890',
        ];

        $route = route('tagore.crm.telephony.webhook', ['accountId' => $account->id, 'token' => $token]);
        $this->postJson($route, $payload)->assertOk()->assertJson(['ok' => true]);
        $this->postJson($route, $payload)->assertOk()->assertJson(['ok' => true]);

        $callId = DB::table('tagore_crm_calls')->where('telephony_account_id', $account->id)->where('provider_call_id', 'EXOTEL-MISSED-001')->value('id');
        $this->assertNotNull($callId);
        $this->assertSame(1, DB::table('tagore_crm_tasks')->where('call_id', $callId)->count());
        $this->assertDatabaseHas('tagore_crm_tasks', [
            'call_id' => $callId,
            'lead_id' => $leadId,
            'task_type' => 'call',
            'priority' => 'high',
            'status' => 'open',
        ]);
    }
    public function test_webhook_discards_recording_metadata_when_recording_is_disabled(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');
        $token = Str::random(48);
        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => $institutionId,
            'provider' => 'exotel',
            'account_sid' => 'tagore-recording-disabled',
            'credentials' => ['api_key' => 'key', 'api_token' => 'token', 'webhook_token' => $token],
            'caller_id' => '+911234567890',
            'record_calls' => false,
            'webhook_token_hash' => hash('sha256', $token),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->postJson(route('tagore.crm.telephony.webhook', [
            'accountId' => $account->id,
            'token' => $token,
        ]), [
            'CallSid' => 'EXOTEL-NO-RECORDING-001',
            'Status' => 'completed',
            'Duration' => 35,
            'RecordingUrl' => 'https://recordings.example.test/private-call.mp3',
        ])->assertOk()->assertJson(['ok' => true]);

        $call = DB::table('tagore_crm_calls')->where('provider_call_id', 'EXOTEL-NO-RECORDING-001')->first();
        $this->assertNotNull($call);
        $this->assertNull($call->recording_url);
        $this->assertStringNotContainsString('private-call.mp3', (string) $call->provider_payload);
    }
    public function test_telephony_webhook_rejects_an_invalid_account_token(): void
    {
        $owner = $this->owner();
        $institutionId = (int) DB::table('tagore_institutions')->where('status', 'active')->value('id');

        $account = TagoreCrmTelephonyAccount::create([
            'institution_id' => $institutionId,
            'provider' => 'exotel',
            'account_sid' => 'tagore-test-sid',
            'credentials' => ['api_key' => 'key', 'api_token' => 'token', 'webhook_token' => 'right-token'],
            'caller_id' => '+911234567890',
            'webhook_token_hash' => hash('sha256', 'right-token'),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->postJson(route('tagore.crm.telephony.webhook', [
            'accountId' => $account->id,
            'token' => 'wrongtoken',
        ]), ['CallSid' => 'BAD', 'Status' => 'completed'])->assertForbidden();
    }
}
