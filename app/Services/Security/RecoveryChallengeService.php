<?php

namespace App\Services\Security;

use App\Models\RecoveryChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RecoveryChallengeService
{
    public const API_PASSWORD_RESET = 'api_password_reset';
    public const PARENT_DEVICE_RECOVERY = 'parent_device_recovery';
    public const TEACHER_DEVICE_RECOVERY = 'teacher_device_recovery';

    private const ALLOWED_PURPOSES = [
        self::API_PASSWORD_RESET,
        self::PARENT_DEVICE_RECOVERY,
        self::TEACHER_DEVICE_RECOVERY,
    ];

    private const ISSUE_LIMIT = 3;
    private const ISSUE_WINDOW_MINUTES = 15;
    private const VERIFY_ATTEMPT_LIMIT = 5;
    private const CODE_TTL_MINUTES = 5;

    /**
     * Create a short-lived challenge. The plaintext code is returned only to
     * the caller for delivery and is never persisted.
     *
     * @return array{challenge: RecoveryChallenge, code: string}
     */
    public function issue(User $user, string $purpose, ?string $ipAddress, string $channel = 'sms'): array
    {
        $this->assertPurpose($purpose);

        return DB::transaction(function () use ($user, $purpose, $ipAddress, $channel) {
            $lockedUser = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $windowStart = now()->subMinutes(self::ISSUE_WINDOW_MINUTES);

            $recentIssues = RecoveryChallenge::where('user_id', $lockedUser->id)
                ->where('usergroup_id', $lockedUser->usergroup_id)
                ->where('purpose', $purpose)
                ->where('created_at', '>=', $windowStart)
                ->count();

            if ($recentIssues >= self::ISSUE_LIMIT) {
                throw ValidationException::withMessages([
                    'recovery' => ['Please wait before requesting another verification code.'],
                ]);
            }

            RecoveryChallenge::where('user_id', $lockedUser->id)
                ->where('usergroup_id', $lockedUser->usergroup_id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $code = (string) random_int(100000, 999999);

            $challenge = RecoveryChallenge::create([
                'user_id' => $lockedUser->id,
                'usergroup_id' => $lockedUser->usergroup_id,
                'purpose' => $purpose,
                'channel' => $channel,
                'code_hash' => Hash::make($code),
                'ip_address' => $ipAddress,
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'attempt_count' => 0,
            ]);

            return ['challenge' => $challenge, 'code' => $code];
        });
    }

    /**
     * Verify the first recovery step. A successful challenge remains available
     * for the final password-change step, but expires at the original deadline.
     */
    public function verify(User $user, string $purpose, string $code, ?string $ipAddress = null): bool
    {
        $this->assertPurpose($purpose);

        return DB::transaction(function () use ($user, $purpose, $code, $ipAddress) {
            $challenge = $this->latestChallengeForUpdate($user, $purpose);

            if (!$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()) {
                return false;
            }

            if ($challenge->ip_address !== null && $challenge->ip_address !== $ipAddress) {
                return false;
            }

            if ($challenge->attempt_count >= self::VERIFY_ATTEMPT_LIMIT) {
                return false;
            }

            $challenge->attempt_count++;

            if (!Hash::check($code, $challenge->code_hash)) {
                $challenge->save();
                return false;
            }

            $challenge->verified_at = $challenge->verified_at ?: now();
            $challenge->save();

            return true;
        });
    }

    /**
     * Consume an already-verified challenge. Call inside the same database
     * transaction as the protected password/session update.
     */
    public function consumeVerified(User $user, string $purpose, string $code): bool
    {
        $this->assertPurpose($purpose);

        $challenge = $this->latestChallengeForUpdate($user, $purpose);

        if (
            !$challenge ||
            $challenge->consumed_at ||
            !$challenge->verified_at ||
            $challenge->expires_at->isPast() ||
            !Hash::check($code, $challenge->code_hash)
        ) {
            return false;
        }

        $challenge->consumed_at = now();
        $challenge->save();

        return true;
    }

    private function latestChallengeForUpdate(User $user, string $purpose): ?RecoveryChallenge
    {
        return RecoveryChallenge::where('user_id', $user->id)
            ->where('usergroup_id', $user->usergroup_id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function assertPurpose(string $purpose): void
    {
        if (!in_array($purpose, self::ALLOWED_PURPOSES, true)) {
            throw new InvalidArgumentException('Unsupported recovery challenge purpose.');
        }
    }
}
