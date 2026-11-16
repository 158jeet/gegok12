<?php

namespace Tests\Feature\Security;

use App\Models\RecoveryChallenge;
use App\Models\School;
use App\Models\User;
use App\Services\Security\RecoveryChallengeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecoveryChallengeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $school = School::factory()->create();

        return User::factory()->for($school)->create([
            'usergroup_id' => User::PARENT_USERGROUP_ID,
            'mobile_no' => '9876543210',
        ]);
    }

    public function test_issue_persists_a_hash_and_binds_challenge_to_account_role_and_purpose(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);

        $issued = $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, '203.0.113.10');

        $this->assertSame($user->id, $issued['challenge']->user_id);
        $this->assertSame(User::PARENT_USERGROUP_ID, $issued['challenge']->usergroup_id);
        $this->assertSame(RecoveryChallengeService::API_PASSWORD_RESET, $issued['challenge']->purpose);
        $this->assertSame('email', $issued['challenge']->channel);
        $this->assertNotSame($issued['code'], $issued['challenge']->code_hash);
        $this->assertTrue(Hash::check($issued['code'], $issued['challenge']->code_hash));
        $this->assertNull($issued['challenge']->verified_at);
        $this->assertNull($issued['challenge']->consumed_at);
    }

    public function test_verification_rejects_wrong_code_and_consumption_is_single_use(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);
        $issued = $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);
        $wrongCode = $issued['code'] === '000000' ? '000001' : '000000';

        $this->assertFalse($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $wrongCode));
        $this->assertTrue($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $issued['code']));

        DB::transaction(function () use ($service, $user, $issued) {
            $this->assertTrue($service->consumeVerified(
                $user,
                RecoveryChallengeService::API_PASSWORD_RESET,
                $issued['code']
            ));
        });

        $this->assertFalse($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $issued['code']));
    }

    public function test_challenge_cannot_be_used_after_the_account_role_changes(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);
        $issued = $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);

        $user->usergroup_id = User::TEACHER_USERGROUP_ID;
        $user->save();

        $this->assertFalse($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $issued['code']));
    }

    public function test_initial_verification_requires_the_issuing_ip_address(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);
        $issued = $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, '203.0.113.10');

        $this->assertFalse($service->verify(
            $user,
            RecoveryChallengeService::API_PASSWORD_RESET,
            $issued['code'],
            '198.51.100.20'
        ));

        $this->assertTrue($service->verify(
            $user,
            RecoveryChallengeService::API_PASSWORD_RESET,
            $issued['code'],
            '203.0.113.10'
        ));
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);
        $issued = $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);

        $issued['challenge']->expires_at = now()->subSecond();
        $issued['challenge']->save();

        $this->assertFalse($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $issued['code']));
    }

    public function test_issue_rate_limit_blocks_a_fourth_request_in_fifteen_minutes(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);

        $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);
        $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);
        $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);

        $this->expectException(ValidationException::class);

        $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);
    }

    public function test_failed_verification_attempts_are_counted_and_limited(): void
    {
        $user = $this->makeUser();
        $service = app(RecoveryChallengeService::class);
        $issued = $service->issue($user, RecoveryChallengeService::API_PASSWORD_RESET, null);
        $wrongCode = $issued['code'] === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertFalse($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $wrongCode));
        }

        $this->assertFalse($service->verify($user, RecoveryChallengeService::API_PASSWORD_RESET, $issued['code']));
        $this->assertSame(5, (int) $issued['challenge']->fresh()->attempt_count);
    }
}
