<?php

namespace Tests\Feature\Security;

use App\Mail\ApiPasswordRecoveryCode;
use App\Models\School;
use App\Models\User;
use App\Services\Security\RecoveryCodeDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecoveryCodeEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_code_is_queued_to_the_accounts_verified_email(): void
    {
        Mail::fake();
        $school = School::factory()->create();
        $user = User::factory()->parent()->for($school)->create([
            'email' => 'verified-parent@example.test',
            'email_verified' => true,
        ]);

        app(RecoveryCodeDeliveryService::class)->send('123456', $user);

        Mail::assertQueued(ApiPasswordRecoveryCode::class);
        Mail::assertQueued(ApiPasswordRecoveryCode::class, function ($mail) {
            return $mail->hasTo('verified-parent@example.test');
        });
    }

    public function test_recovery_code_delivery_rejects_unverified_or_missing_email(): void
    {
        Mail::fake();
        $school = School::factory()->create();
        $user = User::factory()->parent()->for($school)->create([
            'email' => 'unverified-parent@example.test',
            'email_verified' => false,
            'email_verified_at' => null,
        ]);

        try {
            app(RecoveryCodeDeliveryService::class)->send('123456', $user);
            $this->fail('Unverified email must not receive a recovery code.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('A verified account email is required for recovery.', $exception->getMessage());
        }

        Mail::assertNothingOutgoing();
    }
}
