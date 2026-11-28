<?php

namespace Tests\Feature;

use App\Models\TagoreCrmTelephonyAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagoreCrmTelephonyTest extends TestCase
{
    public function test_owner_can_save_exotel_account_with_encrypted_credentials(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
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
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
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

        Http::fake([
            'https://api.exotel.com/*' => Http::response(['Call' => ['Sid' => 'EXOTEL-CALL-001']], 200),
        ]);

        $this->actingAs($owner)->post(route('tagore.crm.telephony.call', $leadId), [
            'agent_number' => '+919999999999',
        ])->assertRedirect()->assertSessionHas('success');

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
        ]);
    }

    public function test_telephony_webhook_rejects_an_invalid_account_token(): void
    {
        $owner = User::query()->where('email', 'demoschool@mailinator.com')->firstOrFail();
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
            'token' => 'wrong-token',
        ]), ['CallSid' => 'BAD', 'Status' => 'completed'])->assertForbidden();
    }
}
