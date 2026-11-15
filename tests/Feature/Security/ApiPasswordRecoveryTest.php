<?php

namespace Tests\Feature\Security;

use App\Models\School;
use App\Models\User;
use App\Models\Userprofile;
use App\Services\Security\RecoveryChallengeService;
use App\Services\Security\RecoveryCodeDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveParent(): User
    {
        $school = School::factory()->create();
        $user = User::factory()->parent()->for($school)->create([
            'mobile_no' => '9876543210',
            'email' => 'parent@example.test',
            'email_verified' => true,
            'status' => 'active',
        ]);

        Userprofile::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'usergroup_id' => $user->usergroup_id,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    private function fakeRecoveryDelivery(?string &$capturedCode): void
    {
        $delivery = \Mockery::mock(RecoveryCodeDeliveryService::class);
        $delivery->shouldReceive('send')
            ->once()
            ->andReturnUsing(function ($code, $user) use (&$capturedCode) {
                $capturedCode = $code;
            });

        $this->app->instance(RecoveryCodeDeliveryService::class, $delivery);
    }

    public function test_api_recovery_verifies_then_atomically_changes_password_and_revokes_tokens(): void
    {
        $user = $this->makeActiveParent();
        $token = $user->createToken('before-recovery')->plainTextToken;
        $this->assertSame(1, $user->tokens()->count());

        $code = null;
        $this->fakeRecoveryDelivery($code);

        $this->postJson('/api/password/reset', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull($code);
        $this->assertSame(1, $user->fresh()->tokens()->count(), 'Tokens must not be revoked before OTP proof.');
        $this->assertDatabaseCount('authentications', 0);

        $challenge = DB::table('recovery_challenges')->where('user_id', $user->id)->first();
        $this->assertNotNull($challenge);
        $this->assertSame('email', $challenge->channel);
        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertTrue(Hash::check($code, $challenge->code_hash));

        $this->postJson('/api/password/store', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
            'password' => $code,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, (int) $user->fresh()->is_reset);
        $this->assertNotNull(DB::table('recovery_challenges')->where('user_id', $user->id)->value('verified_at'));

        $this->postJson('/api/reset/change/password', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
            'oldpassword' => $code,
            'newpassword' => 'NewSecurePass123',
            'confirmpassword' => 'NewSecurePass123',
        ])->assertOk()->assertJson(['success' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass123', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull($user->platform_token);
        $this->assertNull($user->device_id);
        $this->assertSame(0, (int) $user->is_reset);
        $this->assertNotNull(DB::table('recovery_challenges')->where('user_id', $user->id)->value('consumed_at'));

        $this->postJson('/api/reset/change/password', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
            'oldpassword' => $code,
            'newpassword' => 'AnotherSecurePass123',
            'confirmpassword' => 'AnotherSecurePass123',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('NewSecurePass123', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->assertNotSame('', $token);
    }

    public function test_api_recovery_cannot_complete_after_challenge_expiry(): void
    {
        $user = $this->makeActiveParent();
        $code = null;
        $this->fakeRecoveryDelivery($code);

        $this->postJson('/api/password/reset', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
        ])->assertOk();

        $this->postJson('/api/password/store', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
            'password' => $code,
        ])->assertOk();

        DB::table('recovery_challenges')->where('user_id', $user->id)->update([
            'expires_at' => now()->subSecond(),
        ]);

        $this->postJson('/api/reset/change/password', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
            'oldpassword' => $code,
            'newpassword' => 'NewSecurePass123',
            'confirmpassword' => 'NewSecurePass123',
        ])->assertStatus(422);

        $this->assertFalse(Hash::check('NewSecurePass123', $user->fresh()->password));
    }


    public function test_unverified_or_missing_email_fails_closed_without_creating_a_challenge(): void
    {
        $user = $this->makeActiveParent();
        $user->email = null;
        $user->email_verified = false;
        $user->email_verified_at = null;
        $user->save();

        $this->postJson('/api/password/reset', [
            'mobile_no' => $user->mobile_no,
            'usergroup' => User::PARENT_USERGROUP_ID,
        ])->assertOk()->assertJson([
            'success' => true,
            'message' => 'If the account is eligible, a recovery code will be sent to its registered email address.',
        ]);

        $this->assertDatabaseCount('recovery_challenges', 0);
    }

    public function test_unknown_account_receives_same_recovery_initiation_response_without_sms(): void
    {
        $this->postJson('/api/password/reset', [
            'mobile_no' => '9999999999',
            'usergroup' => User::PARENT_USERGROUP_ID,
        ])->assertOk()->assertJson([
            'success' => true,
            'message' => 'If the account is eligible, a recovery code will be sent to its registered email address.',
        ]);

        $this->assertDatabaseCount('recovery_challenges', 0);
    }
}
