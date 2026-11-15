<?php

namespace Tests\Feature\Security;

use App\Models\School;
use App\Models\User;
use App\Services\Security\RecoveryCodeDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDelivery(?string &$code): void
    {
        $delivery = \Mockery::mock(RecoveryCodeDeliveryService::class);
        $delivery->shouldReceive('send')->once()->andReturnUsing(function ($sentCode) use (&$code) {
            $code = $sentCode;
        });
        $this->app->instance(RecoveryCodeDeliveryService::class, $delivery);
    }

    private function makeUser(int $group): User
    {
        $school = School::factory()->create();
        $factory = User::factory()->for($school);
        $factory = $group === User::PARENT_USERGROUP_ID ? $factory->parent() : $factory->teacher();

        return $factory->create([
            'mobile_no' => '9876543210',
            'status' => 'active',
            'platform_token' => 'push-token',
            'device_id' => 'registered-device',
        ]);
    }

    public function test_parent_device_logout_requires_verified_single_use_sms_challenge(): void
    {
        $this->assertDeviceRecoveryWorks(User::PARENT_USERGROUP_ID, '/api/logout/devices', '/api/logout/devices/confirm');
    }

    public function test_teacher_device_logout_requires_verified_single_use_sms_challenge(): void
    {
        $this->assertDeviceRecoveryWorks(User::TEACHER_USERGROUP_ID, '/api/teacher/logout/devices', '/api/teacher/logout/devices/confirm');
    }

    private function assertDeviceRecoveryWorks(int $group, string $requestUrl, string $confirmUrl): void
    {
        $user = $this->makeUser($group);
        $user->createToken('device-one');
        $user->createToken('device-two');
        $this->assertSame(2, $user->tokens()->count());

        $code = null;
        $this->fakeDelivery($code);

        $this->postJson($requestUrl, ['mobile_no' => $user->mobile_no])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($code);
        $this->assertSame(2, $user->fresh()->tokens()->count(), 'No session may be revoked before OTP proof.');
        $challenge = DB::table('recovery_challenges')->where('user_id', $user->id)->first();
        $this->assertNotNull($challenge);
        $this->assertNotSame($code, $challenge->code_hash);

        $this->postJson($confirmUrl, [
            'mobile_no' => $user->mobile_no,
            'code' => '000000' === $code ? '000001' : '000000',
        ])->assertStatus(422);

        $this->assertSame(2, $user->fresh()->tokens()->count());

        $this->postJson($confirmUrl, [
            'mobile_no' => $user->mobile_no,
            'code' => $code,
        ])->assertOk()->assertJson(['success' => true]);

        $user->refresh();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull($user->platform_token);
        $this->assertNull($user->device_id);
        $this->assertNotNull(DB::table('recovery_challenges')->where('user_id', $user->id)->value('consumed_at'));

        $this->postJson($confirmUrl, [
            'mobile_no' => $user->mobile_no,
            'code' => $code,
        ])->assertStatus(422);
    }

    public function test_parent_recovery_code_cannot_be_used_on_teacher_endpoint(): void
    {
        $user = $this->makeUser(User::PARENT_USERGROUP_ID);
        $user->createToken('keep-me');

        $code = null;
        $this->fakeDelivery($code);

        $this->postJson('/api/logout/devices', ['mobile_no' => $user->mobile_no])->assertOk();

        $this->postJson('/api/teacher/logout/devices/confirm', [
            'mobile_no' => $user->mobile_no,
            'code' => $code,
        ])->assertStatus(422);

        $this->assertSame(1, $user->fresh()->tokens()->count());
    }
}
