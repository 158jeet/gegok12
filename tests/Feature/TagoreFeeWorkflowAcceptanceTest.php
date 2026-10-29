<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\FeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TagoreFeeWorkflowAcceptanceTest extends TestCase
{
    private ?int $obligationId = null;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_offline_payment_updates_obligation_ledger_and_receipt_atomically(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $institution = DB::table('tagore_institutions')
            ->where('school_id', $owner->school_id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($institution, 'Owner must have an active institution fixture.');

        $student = User::query()
            ->where('usergroup_id', 6)
            ->where('school_id', $institution->school_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $service = app(FeeService::class);
        $this->obligationId = $service->createObligation(
            (int) $student->id,
            (int) $institution->id,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 100.00,
                'items' => [[
                    'fee_head' => 'Acceptance tuition',
                    'code' => 'QA-TUITION',
                    'gross_amount' => 100.00,
                    'discount_amount' => 0,
                    'concession_amount' => 0,
                ]],
            ],
            (int) $owner->id,
        );

        $reference = 'QA-FEE-' . bin2hex(random_bytes(5));
        $paymentId = $service->recordOfflinePayment(
            (int) $student->id,
            (int) $institution->id,
            40.00,
            null,
            (int) $owner->id,
            $reference,
            [['fee_obligation_id' => $this->obligationId, 'amount' => 40.00]],
            'bank_transfer',
        );

        $this->assertDatabaseHas('tagore_payments', [
            'id' => $paymentId,
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'amount' => '40.00',
            'payment_mode' => 'bank_transfer',
            'reference_number' => $reference,
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('tagore_payment_allocations', [
            'payment_id' => $paymentId,
            'fee_obligation_id' => $this->obligationId,
            'amount' => '40.00',
        ]);
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->obligationId,
            'gross_amount' => '100.00',
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
            'status' => 'partial',
        ]);
        $this->assertDatabaseHas('tagore_financial_transactions', [
            'reference_type' => 'tagore_payment',
            'reference_id' => $paymentId,
            'transaction_type' => 'PAYMENT',
            'credit' => '40.00',
        ]);
        $this->assertDatabaseHas('tagore_audit_events', [
            'user_id' => $owner->id,
            'action' => 'FEE_PAYMENT_RECORDED',
            'entity_type' => 'tagore_payments',
            'entity_id' => $paymentId,
        ]);

        $paymentCount = DB::table('tagore_payments')->count();
        try {
            $service->recordOfflinePayment(
                (int) $student->id,
                (int) $institution->id,
                61.00,
                null,
                (int) $owner->id,
                'QA-OVERPAY-' . bin2hex(random_bytes(4)),
                [['fee_obligation_id' => $this->obligationId, 'amount' => 61.00]],
                'cash',
            );
            $this->fail('An allocation greater than the outstanding balance must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }

        $this->assertSame($paymentCount, DB::table('tagore_payments')->count(), 'Rejected overpayment must roll back its payment row.');
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->obligationId,
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
            'status' => 'partial',
        ]);
    }

    public function test_fee_editor_and_coordinator_cannot_record_offline_payments(): void
    {
        $institution = DB::table('tagore_institutions')->where('status', 'active')->first();
        $student = User::query()
            ->where('usergroup_id', 6)
            ->where('school_id', $institution->school_id)
            ->whereNull('deleted_at')
            ->firstOrFail();

        foreach (['fees@tagore-demo.local', 'coordinator@tagore-demo.local'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->actingAs($user)
                ->post(route('tagore.fees.payment', $student->id), [
                    'amount' => 10,
                    'payment_mode' => 'cash',
                    'reference_number' => 'QA-UNAUTHORIZED',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('tagore_payments', ['reference_number' => 'QA-UNAUTHORIZED']);
    }
}
