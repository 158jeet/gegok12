<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class FeeVaultArchiveNotifier
{
    /**
     * Email the encrypted archive to the configured Owner. The attachment is the
     * encrypted ciphertext, never the plaintext financial payload.
     */
    public function send(int $closureId): bool
    {
        $closure = DB::table('tagore_fee_year_closures as c')
            ->join('tagore_institutions as i', 'i.id', '=', 'c.institution_id')
            ->where('c.id', $closureId)
            ->first([
                'c.*',
                'i.display_name as institution_name',
                'i.settings_json as institution_settings_json',
            ]);

        if (!$closure) {
            return false;
        }

        $ownerId = (int) config('tagore.fee_vault_owner_user_id', 0);
        $owner = $ownerId > 0
            ? DB::table('users as u')
                ->join('tagore_user_roles as ur', 'ur.user_id', '=', 'u.id')
                ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
                ->where('u.id', $ownerId)
                ->whereNull('u.deleted_at')
                ->where('ur.status', 'active')
                ->where('r.code', 'OWNER')
                ->where('r.status', 'active')
                ->first(['u.id', 'u.name', 'u.email'])
            : null;

        if (!$owner || !filter_var((string) $owner->email, FILTER_VALIDATE_EMAIL)) {
            $this->recordDelivery(
                $closureId,
                $owner ? (int) $owner->id : null,
                null,
                'failed',
                'Configured fee-vault owner is missing, is not an active Owner, or has no valid email address.'
            );
            return false;
        }

        $existing = DB::table('tagore_fee_archive_deliveries')->where('closure_id', $closureId)->first();
        if ($existing && $existing->status === 'sent') {
            return true;
        }

        $path = (string) $closure->archive_path;
        if ($path === '' || !Storage::disk('local')->exists($path)) {
            $this->recordDelivery($closureId, (int) $owner->id, (string) $owner->email, 'failed', 'Encrypted archive file is missing.');
            return false;
        }

        $ciphertext = Storage::disk('local')->get($path);
        if (!hash_equals((string) $closure->archive_sha256, hash('sha256', $ciphertext))) {
            $this->recordDelivery($closureId, (int) $owner->id, (string) $owner->email, 'failed', 'Encrypted archive integrity check failed; email was not sent.');
            return false;
        }

        $settings = json_decode((string) $closure->institution_settings_json, true) ?: [];
        $mailConfig = $this->mailConfig((int) $closure->institution_id, $settings);

        $filename = sprintf('tagore-fee-archive-institution-%d-year-%d.json.enc', (int) $closure->institution_id, (int) $closure->academic_year_id);
        $body = implode("\n", [
            'Tagore ERP encrypted fee archive',
            '',
            'Institution: ' . $closure->institution_name,
            'Academic year ID: ' . $closure->academic_year_id,
            'Archive closed at: ' . $closure->closed_at,
            'Students: ' . $closure->student_count,
            'Payments: ' . $closure->payment_count,
            'Financial transactions: ' . $closure->transaction_count,
            'SHA-256: ' . $closure->archive_sha256,
            '',
            'The attached file contains encrypted data, not a readable financial export. Keep it private and retain access to the Tagore ERP application encryption key. This email is an archive delivery, not a substitute for a full database/uploads backup and restore plan.',
        ]);

        $this->recordDelivery($closureId, (int) $owner->id, (string) $owner->email, 'queued', null);

        try {
            $mailer = $mailConfig ? Mail::build($mailConfig) : Mail::mailer(config('mail.default'));
            $mailer->raw($body, function ($message) use ($owner, $closure, $ciphertext, $filename, $mailConfig) {
                if (!empty($mailConfig['from']['address'])) {
                    $message->from($mailConfig['from']['address'], $mailConfig['from']['name'] ?? config('mail.from.name'));
                }
                $message->to((string) $owner->email)
                    ->subject(sprintf('Encrypted fee archive — %s — academic year %d', $closure->institution_name, (int) $closure->academic_year_id))
                    ->attachData($ciphertext, $filename, ['mime' => 'application/octet-stream']);
            });

            $this->recordDelivery($closureId, (int) $owner->id, (string) $owner->email, 'sent', null, now());
            return true;
        } catch (\Throwable $e) {
            Log::error('Tagore encrypted fee archive email failed', [
                'closure_id' => $closureId,
                'institution_id' => $closure->institution_id,
                'error' => $e->getMessage(),
            ]);
            $this->recordDelivery($closureId, (int) $owner->id, (string) $owner->email, 'failed', substr($e->getMessage(), 0, 2000));
            return false;
        }
    }

    private function recordDelivery(
        int $closureId,
        ?int $recipientUserId,
        ?string $recipientEmail,
        string $status,
        ?string $error,
        $sentAt = null,
    ): void {
        $exists = DB::table('tagore_fee_archive_deliveries')->where('closure_id', $closureId)->exists();
        $values = [
            'recipient_user_id' => $recipientUserId,
            'recipient_email' => $recipientEmail,
            'status' => $status,
            'error' => $error,
            'sent_at' => $sentAt,
            'updated_at' => now(),
        ];
        if (!$exists) {
            $values['created_at'] = now();
        }

        DB::table('tagore_fee_archive_deliveries')->updateOrInsert(
            ['closure_id' => $closureId],
            $values
        );
    }

    private function mailConfig(int $institutionId, array $settings): ?array
    {
        $profiles = [];
        $raw = trim((string) config('tagore.mail_profiles_json', ''));
        if ($raw !== '') {
            try {
                $profiles = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                Log::warning('Invalid TAGORE_MAIL_PROFILES_JSON while preparing fee archive delivery', ['error' => $e->getMessage()]);
            }
        }

        $profile = is_array($profiles) ? ($profiles[(string) $institutionId] ?? $profiles[$institutionId] ?? null) : null;
        $profile = is_array($profile) ? $profile : [];
        $from = is_array($settings['mail'] ?? null) ? $settings['mail'] : [];

        if (!empty($profile['username'])) {
            return [
                'transport' => 'smtp',
                'host' => $profile['host'] ?? 'smtp.gmail.com',
                'port' => (int) ($profile['port'] ?? 587),
                'encryption' => $profile['encryption'] ?? 'tls',
                'username' => $profile['username'],
                'password' => $profile['password'] ?? '',
                'timeout' => (int) ($profile['timeout'] ?? 30),
                'from' => [
                    'address' => $profile['from_address'] ?? $from['from_address'] ?? $profile['username'],
                    'name' => $profile['from_name'] ?? $from['from_name'] ?? config('mail.from.name'),
                ],
            ];
        }

        if (!empty($from['from_address'])) {
            return [
                'transport' => 'smtp',
                'host' => config('mail.host'),
                'port' => (int) config('mail.port'),
                'encryption' => config('mail.encryption'),
                'username' => config('mail.username'),
                'password' => config('mail.password'),
                'timeout' => (int) config('mail.timeout', 30),
                'from' => [
                    'address' => $from['from_address'],
                    'name' => $from['from_name'] ?? config('mail.from.name'),
                ],
            ];
        }

        return null;
    }
}
