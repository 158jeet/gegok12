<?php

namespace App\Services\Tagore;

use App\Models\TagoreAdmissionLead;
use App\Models\TagoreCrmCall;
use App\Models\TagoreCrmTelephonyAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrmTelephonyService
{
    private function normalizePhoneNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+'.$digits;
        }
        if (strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        throw new RuntimeException('A valid phone number in international format is required for cloud calling.');
    }

    public function initiateExotel(
        TagoreCrmTelephonyAccount $account,
        TagoreAdmissionLead $lead,
        string $agentNumber,
        int $userId
    ): TagoreCrmCall {
        if (!$account->is_active || $account->provider !== 'exotel') {
            throw new RuntimeException('The institution cloud telephony account is not active or supported.');
        }
        if ((int) $account->institution_id !== (int) $lead->institution_id) {
            throw new RuntimeException('The calling account and lead belong to different institutions.');
        }
        if (!$lead->mobile) {
            throw new RuntimeException('This lead has no mobile number.');
        }

        $agentNumber = $this->normalizePhoneNumber($agentNumber);
        $leadNumber = $this->normalizePhoneNumber((string) $lead->mobile);
        $callerId = $this->normalizePhoneNumber((string) $account->caller_id);

        $credentials = $account->credentials ?: [];
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $apiToken = (string) ($credentials['api_token'] ?? '');
        $sid = (string) $account->account_sid;

        if ($apiKey === '' || $apiToken === '' || $sid === '') {
            throw new RuntimeException('Telephony credentials are incomplete. Ask the Owner to finish Exotel setup.');
        }

        $baseUrl = (string) ($credentials['api_base_url'] ?? 'https://api.in.exotel.com');
        $parts = parse_url($baseUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || !preg_match('/(^|\.)exotel\.com$/', $host)) {
            throw new RuntimeException('Only an HTTPS Exotel API endpoint is permitted.');
        }

        $url = rtrim($baseUrl, '/').'/v1/Accounts/'.rawurlencode($sid).'/Calls/connect';
        $webhookToken = (string) ($credentials['webhook_token'] ?? '');
        if ($webhookToken === '' || !hash_equals((string) $account->webhook_token_hash, hash('sha256', $webhookToken))) {
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
            'to_number' => $leadNumber,
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
                    'To' => $leadNumber,
                    'CallerId' => $callerId,
                    'Record' => $account->record_calls ? 'true' : 'false',
                    'CallType' => 'trans',
                    'TimeLimit' => 1800,
                    'TimeOut' => 30,
                    'StatusCallback' => $webhookUrl,
                    'StatusCallbackEvents[0]' => 'answered',
                    'StatusCallbackEvents[1]' => 'terminal',
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('Telephony provider rejected the call request (HTTP '.$response->status().').');
            }

            $payload = $response->json() ?: [];
            $providerCallId = data_get($payload, 'Call.Sid')
                ?? data_get($payload, 'Call.Details.Sid')
                ?? data_get($payload, 'call_sid');

            if (!is_string($providerCallId) && !is_numeric($providerCallId)) {
                throw new RuntimeException('The telephony provider accepted no trackable call reference. The call was not marked successful.');
            }

            $call->update([
                'provider_call_id' => (string) $providerCallId,
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
