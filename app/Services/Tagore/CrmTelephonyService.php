<?php

namespace App\Services\Tagore;

use App\Models\TagoreAdmissionLead;
use App\Models\TagoreCrmCall;
use App\Models\TagoreCrmTelephonyAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CrmTelephonyService
{
    public function initiateExotel(
        TagoreCrmTelephonyAccount $account,
        TagoreAdmissionLead $lead,
        string $agentNumber,
        int $userId
    ): TagoreCrmCall {
        $credentials = $account->credentials ?: [];
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $apiToken = (string) ($credentials['api_token'] ?? '');
        $sid = (string) $account->account_sid;

        if ($apiKey === '' || $apiToken === '' || $sid === '' || $account->caller_id === '') {
            throw new RuntimeException('Telephony credentials are incomplete. Ask the Owner to finish Exotel setup.');
        }

        $baseUrl = (string) ($credentials['api_base_url'] ?? 'https://api.in.exotel.com');
        $parts = parse_url($baseUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || !preg_match('/(^|\\.)exotel\\.com$/', $host)) {
            throw new RuntimeException('Only an HTTPS Exotel API endpoint is permitted.');
        }

        $url = rtrim($baseUrl, '/') . '/v1/Accounts/' . rawurlencode($sid) . '/Calls/connect';
        $webhookToken = (string) ($credentials['webhook_token'] ?? '');
        if ($webhookToken === '' || !hash_equals($account->webhook_token_hash, hash('sha256', $webhookToken))) {
            throw new RuntimeException('Telephony webhook secret is missing or invalid. Re-save the provider connection.');
        }
        $webhookUrl = route('tagore.crm.telephony.webhook', [
            'accountId' => $account->id,
            'token' => $webhookToken,
        ], true);

        $call = TagoreCrmCall::create([
            'institution_id' => $lead->institution_id,
            'telephony_account_id' => $account->id,
            'lead_id' => $lead->id,
            'direction' => 'outbound',
            'from_number' => $agentNumber,
            'to_number' => $lead->mobile,
            'status' => 'queued',
            'started_at' => now(),
            'initiated_by' => $userId,
        ]);

        try {
            $response = Http::withBasicAuth($apiKey, $apiToken)
                ->asForm()
                ->acceptJson()
                ->timeout(20)
                ->post($url, [
                    'From' => $agentNumber,
                    'To' => $lead->mobile,
                    'CallerId' => $account->caller_id,
                    'Record' => $account->record_calls ? 'true' : 'false',
                    'CallType' => 'trans',
                    'TimeLimit' => 1800,
                    'TimeOut' => 30,
                    'StatusCallback' => $webhookUrl,
                    'StatusCallbackEvents[0]' => 'answered',
                    'StatusCallbackEvents[1]' => 'terminal',
                ]);

            if (!$response->successful()) {
                $call->update(['status' => 'failed', 'ended_at' => now()]);
                throw new RuntimeException('Telephony provider rejected the call request (HTTP ' . $response->status() . ').');
            }

            $payload = $response->json() ?: [];
            $providerCallId = data_get($payload, 'Call.Sid')
                ?? data_get($payload, 'Call.Details.Sid')
                ?? data_get($payload, 'call_sid');

            $call->update([
                'provider_call_id' => $providerCallId ? (string) $providerCallId : null,
                'status' => 'initiated',
                'provider_payload' => ['accepted' => true, 'provider' => 'exotel'],
            ]);

            return $call->fresh();
        } catch (\Throwable $exception) {
            $call->update(['status' => 'failed', 'ended_at' => now()]);
            throw $exception;
        }
    }
}
