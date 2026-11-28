<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\FamilyOnlinePaymentService;
use App\Services\Tagore\FeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TagoreFamilyOnlinePaymentTest extends TestCase
{
    private User $owner;
    private User $parent;
    private User $firstStudent;
    private User $secondStudent;
    private int $firstInstitutionId;
    private int $secondInstitutionId;
    private int $firstObligationId;
    private int $secondObligationId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $this->parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $this->firstStudent = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $this->firstInstitutionId = (int) DB::table('tagore_institutions')
            ->where('school_id', $this->firstStudent->school_id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        $otherInstitution = DB::table('tagore_institutions')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('school_id', '<>', $this->firstStudent->school_id)
            ->orderBy('id')
            ->first(['id', 'school_id']);

        if (!$otherInstitution) {
            $suffix = bin2hex(random_bytes(5));
            $schoolId = DB::table('schools')->insertGetId([
                'name' => 'Family Checkout QA School ' . $suffix,
                'email' => 'family-checkout-' . $suffix . '@tagore-demo.local',
                'status' => 1,
                'slug' => 'family-checkout-' . $suffix,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->secondInstitutionId = (int) DB::table('tagore_institutions')->insertGetId([
                'tagore_group_id' => DB::table('tagore_institutions')->where('id', $this->firstInstitutionId)->value('tagore_group_id'),
                'school_id' => $schoolId,
                'code' => 'FAMILY-QA-' . strtoupper($suffix),
                'display_name' => 'Family Checkout QA Institution',
                'type' => 'school',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $secondSchoolId = $schoolId;
        } else {
            $this->secondInstitutionId = (int) $otherInstitution->id;
            $secondSchoolId = (int) $otherInstitution->school_id;
        }

        $this->secondStudent = User::factory()->create([
            'school_id' => $secondSchoolId,
            'usergroup_id' => 6,
            'status' => 'active',
            'name' => 'Family Checkout Second Child',
            'email' => 'family-child-' . bin2hex(random_bytes(5)) . '@tagore-demo.local',
        ]);

        $this->linkChild($this->firstStudent);
        $this->linkChild($this->secondStudent);

        config([
            'tagore.payment_gateway' => 'razorpay',
            'services.razorpay.key' => 'rzp_test_tagore',
            'services.razorpay.secret' => 'family_checkout_test_secret',
        ]);
        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['id' => 'order_family_checkout_test'], 201),
            'https://api.razorpay.com/v1/payments/pay_family_checkout_test' => Http::response([
                'id' => 'pay_family_checkout_test',
                'order_id' => 'order_family_checkout_test',
                'status' => 'captured',
                'amount' => 9000,
                'currency' => 'INR',
            ], 200),
            'https://api.razorpay.com/v1/payments/pay_family_checkout_stale' => Http::response([
                'id' => 'pay_family_checkout_stale',
                'order_id' => 'order_family_checkout_test',
                'status' => 'captured',
                'amount' => 13000,
                'currency' => 'INR',
            ], 200),
        ]);

        $feeService = app(FeeService::class);
        $this->firstObligationId = $feeService->createObligation(
            (int) $this->firstStudent->id,
            $this->firstInstitutionId,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 100.00,
                'items' => [[
                    'fee_head' => 'Family checkout first child QA',
                    'code' => 'QA-FAMILY-FIRST-' . bin2hex(random_bytes(3)),
                    'gross_amount' => 100.00,
                    'discount_amount' => 0,
                    'concession_amount' => 0,
                ]],
            ],
            (int) $this->owner->id,
        );
        $this->secondObligationId = $feeService->createObligation(
            (int) $this->secondStudent->id,
            $this->secondInstitutionId,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 150.00,
                'items' => [[
                    'fee_head' => 'Family checkout second child QA',
                    'code' => 'QA-FAMILY-SECOND-' . bin2hex(random_bytes(3)),
                    'gross_amount' => 150.00,
                    'discount_amount' => 0,
                    'concession_amount' => 0,
                ]],
            ],
            (int) $this->owner->id,
        );
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_one_family_checkout_can_partially_pay_multiple_children_across_institutions(): void
    {
        $service = app(FamilyOnlinePaymentService::class);
        $order = $service->createOrder((int) $this->parent->id, [
            ['fee_obligation_id' => $this->firstObligationId, 'amount' => 40.00],
            ['fee_obligation_id' => $this->secondObligationId, 'amount' => 50.00],
        ]);

        $this->assertSame(90.0, (float) $order['amount']);
        $this->assertCount(2, $order['children']);
        $this->assertSame('order_family_checkout_test', $order['gateway_order_id']);

        $paymentIds = $service->confirm([
            'razorpay_order_id' => $order['gateway_order_id'],
            'razorpay_payment_id' => 'pay_family_checkout_test',
            'razorpay_signature' => hash_hmac(
                'sha256',
                $order['gateway_order_id'] . '|pay_family_checkout_test',
                'family_checkout_test_secret',
            ),
        ], (int) $this->parent->id);

        $this->assertCount(2, $paymentIds);
        $this->assertDatabaseHas('tagore_family_payment_groups', [
            'id' => $order['group_id'],
            'status' => 'paid',
            'gateway_payment_id' => 'pay_family_checkout_test',
            'gateway_captured_amount' => '90.00',
            'gateway_captured_currency' => 'INR',
        ]);
        $this->assertSame(2, DB::table('tagore_payments')->whereIn('id', $paymentIds)->where('status', 'success')->count());
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->firstObligationId,
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
        ]);
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->secondObligationId,
            'paid_amount' => '50.00',
            'outstanding_amount' => '100.00',
        ]);
        $this->assertSame(2, DB::table('tagore_financial_transactions')->whereIn('reference_id', $paymentIds)->count());
    }

    public function test_family_checkout_reservations_prevent_duplicate_child_balance_checkout(): void
    {
        $service = app(FamilyOnlinePaymentService::class);
        $service->createOrder((int) $this->parent->id, [
            ['fee_obligation_id' => $this->firstObligationId, 'amount' => 80.00],
            ['fee_obligation_id' => $this->secondObligationId, 'amount' => 50.00],
        ]);

        try {
            $service->createOrder((int) $this->parent->id, [
                ['fee_obligation_id' => $this->firstObligationId, 'amount' => 30.00],
            ]);
            $this->fail('A second family checkout must not reuse a fee balance reserved by the first checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }

        $this->assertSame(1, DB::table('tagore_payment_order_allocations as a')
            ->join('tagore_payment_orders as o', 'o.id', '=', 'a.payment_order_id')
            ->where('o.family_payment_group_id', '>', 0)
            ->where('a.fee_obligation_id', $this->firstObligationId)
            ->count());
    }

    public function test_captured_family_payment_is_quarantined_if_one_child_balance_changes(): void
    {
        $service = app(FamilyOnlinePaymentService::class);
        $order = $service->createOrder((int) $this->parent->id, [
            ['fee_obligation_id' => $this->firstObligationId, 'amount' => 80.00],
            ['fee_obligation_id' => $this->secondObligationId, 'amount' => 50.00],
        ]);

        DB::table('tagore_fee_obligations')->where('id', $this->firstObligationId)->update([
            'paid_amount' => 40.00,
            'outstanding_amount' => 60.00,
            'status' => 'partial',
            'updated_at' => now(),
        ]);

        $paymentIds = $service->confirm([
            'razorpay_order_id' => $order['gateway_order_id'],
            'razorpay_payment_id' => 'pay_family_checkout_stale',
            'razorpay_signature' => hash_hmac(
                'sha256',
                $order['gateway_order_id'] . '|pay_family_checkout_stale',
                'family_checkout_test_secret',
            ),
        ], (int) $this->parent->id);

        $this->assertCount(2, $paymentIds);
        $this->assertDatabaseHas('tagore_family_payment_groups', [
            'id' => $order['group_id'],
            'status' => 'review_required',
            'gateway_captured_amount' => '130.00',
        ]);
        $this->assertSame(2, DB::table('tagore_payments')->whereIn('id', $paymentIds)->where('status', 'review_required')->count());
        $this->assertSame(0, DB::table('tagore_payment_allocations')->whereIn('payment_id', $paymentIds)->count());
        $this->assertSame(0, DB::table('tagore_financial_transactions')->whereIn('reference_id', $paymentIds)->count());
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->firstObligationId,
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
        ]);
        $this->actingAs($this->owner)->post(route('tagore.fees.refund', $paymentIds[0]), [
            'refund_reference' => 'rfnd_family_acceptance_1',
            'refund_date' => now()->toDateString(),
            'notes' => 'Synthetic acceptance: the complete family gateway payment was refunded.',
        ])->assertRedirect();

        $this->assertDatabaseHas('tagore_family_payment_groups', [
            'id' => $order['group_id'],
            'status' => 'refunded',
        ]);
        $this->assertSame(2, DB::table('tagore_payments')->whereIn('id', $paymentIds)->where('status', 'refunded')->count());
        $this->assertSame(2, DB::table('tagore_payment_orders')->where('family_payment_group_id', $order['group_id'])->where('status', 'refunded')->count());
    }

    public function test_parent_cannot_create_family_checkout_for_an_unlinked_child(): void
    {
        $unlinkedStudent = User::factory()->create([
            'school_id' => $this->firstStudent->school_id,
            'usergroup_id' => 6,
            'status' => 'active',
            'name' => 'Unlinked Family QA Student',
            'email' => 'unlinked-family-' . bin2hex(random_bytes(5)) . '@tagore-demo.local',
        ]);
        $unlinkedObligationId = app(FeeService::class)->createObligation(
            (int) $unlinkedStudent->id,
            $this->firstInstitutionId,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 75.00,
                'items' => [[
                    'fee_head' => 'Unlinked family child QA',
                    'code' => 'QA-FAMILY-UNLINKED-' . bin2hex(random_bytes(3)),
                    'gross_amount' => 75.00,
                    'discount_amount' => 0,
                    'concession_amount' => 0,
                ]],
            ],
            (int) $this->owner->id,
        );

        try {
            app(FamilyOnlinePaymentService::class)->createOrder((int) $this->parent->id, [
                ['fee_obligation_id' => $unlinkedObligationId, 'amount' => 25.00],
            ]);
            $this->fail('A parent must not pay fee obligations for an unlinked child.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }
    }

    private function linkChild(User $student): void
    {
        DB::table('tagore_parent_students')->updateOrInsert(
            ['parent_user_id' => $this->parent->id, 'student_id' => $student->id],
            [
                'relationship' => 'Parent',
                'is_primary' => true,
                'is_guardian' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
