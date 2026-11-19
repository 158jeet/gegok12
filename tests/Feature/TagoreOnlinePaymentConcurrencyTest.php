<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tagore\FeeService;
use App\Services\Tagore\OnlinePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TagoreOnlinePaymentConcurrencyTest extends TestCase
{
    private User $owner;
    private User $parent;
    private User $student;
    private int $institutionId;
    private int $obligationId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $this->parent = User::query()->where('email', 'parent@tagore-demo.local')->firstOrFail();
        $this->student = User::query()->where('email', 'student@tagore-demo.local')->firstOrFail();
        $this->institutionId = (int) DB::table('tagore_institutions')
            ->where('school_id', $this->student->school_id)->where('status', 'active')->value('id');

        config([
            'tagore.payment_gateway' => 'razorpay',
            'services.razorpay.key' => 'rzp_test_tagore',
            'services.razorpay.secret' => 'test_secret_for_acceptance',
        ]);
        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::sequence()
                ->push(['id' => 'order_test_tagore_1'], 200)
                ->push(['id' => 'order_test_tagore_2'], 200)
                ->push(['id' => 'order_test_tagore_3'], 200),
        ]);

        $this->obligationId = app(FeeService::class)->createObligation(
            (int) $this->student->id,
            $this->institutionId,
            [
                'due_date' => now()->addDay()->toDateString(),
                'frequency' => 'one_time',
                'gross_amount' => 100.00,
                'items' => [[
                    'fee_head' => 'Online payment concurrency QA',
                    'code' => 'QA-ONLINE-CONCURRENCY',
                    'gross_amount' => 100.00,
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

    public function test_active_checkout_reserves_balance_and_prevents_a_second_order_from_reusing_it(): void
    {
        $service = app(OnlinePaymentService::class);
        $first = $service->createOrder((int) $this->student->id, (int) $this->parent->id, 80.00);

        try {
            $service->createOrder((int) $this->student->id, (int) $this->parent->id, 30.00);
            $this->fail('A second checkout must not reserve amounts already held by an active checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertDatabaseHas('tagore_payment_order_allocations', [
            'payment_order_id' => $first['order_id'],
            'fee_obligation_id' => $this->obligationId,
            'amount' => '80.00',
        ]);

        DB::table('tagore_payment_orders')->where('id', $first['order_id'])
            ->update(['expires_at' => now()->subMinute(), 'updated_at' => now()]);

        $second = $service->createOrder((int) $this->student->id, (int) $this->parent->id, 30.00);
        $this->assertNotSame($first['order_id'], $second['order_id']);
        $this->assertDatabaseHas('tagore_payment_order_allocations', [
            'payment_order_id' => $second['order_id'],
            'fee_obligation_id' => $this->obligationId,
            'amount' => '30.00',
        ]);
    }

    public function test_gateway_failure_releases_the_checkout_reservation(): void
    {
        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['error' => 'gateway unavailable'], 503),
        ]);

        try {
            app(OnlinePaymentService::class)->createOrder((int) $this->student->id, (int) $this->parent->id, 80.00);
            $this->fail('A failed gateway order must not be returned as a usable checkout.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $failedOrder = DB::table('tagore_payment_orders')
            ->where('student_id', $this->student->id)
            ->where('amount', 80.00)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($failedOrder);
        $this->assertSame('failed', $failedOrder->status);

        // A failed gateway attempt must no longer reserve any fee balance.
        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response(['id' => 'order_after_failure'], 200),
        ]);
        $retry = app(OnlinePaymentService::class)->createOrder((int) $this->student->id, (int) $this->parent->id, 80.00);
        $this->assertSame('order_after_failure', $retry['gateway_order_id']);
    }

    public function test_captured_payment_is_quarantined_if_fee_balance_changed_after_checkout(): void
    {
        $service = app(OnlinePaymentService::class);
        $order = $service->createOrder((int) $this->student->id, (int) $this->parent->id, 80.00);

        // Simulate an independent cash/office payment recorded after checkout but
        // before the gateway capture webhook is processed.
        DB::table('tagore_fee_obligations')->where('id', $this->obligationId)->update([
            'paid_amount' => 40.00,
            'outstanding_amount' => 60.00,
            'status' => 'partial',
            'updated_at' => now(),
        ]);

        $paymentId = 'pay_test_tagore_stale';
        $signature = hash_hmac('sha256', $order['gateway_order_id'].'|'.$paymentId, 'test_secret_for_acceptance');
        $settledId = $service->confirm([
            'razorpay_order_id' => $order['gateway_order_id'],
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);

        $this->assertSame($settledId, (int) DB::table('tagore_payments')->where('payment_order_id', $order['order_id'])->value('id'));
        $this->assertDatabaseHas('tagore_payments', [
            'id' => $settledId,
            'status' => 'review_required',
            'gateway_payment_id' => $paymentId,
            'receipt_no' => null,
        ]);
        $this->assertDatabaseHas('tagore_payment_orders', ['id' => $order['order_id'], 'status' => 'review_required']);
        $this->assertDatabaseHas('tagore_payment_reconciliations', [
            'payment_id' => $settledId,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->obligationId,
            'paid_amount' => '40.00',
            'outstanding_amount' => '60.00',
            'status' => 'partial',
        ]);
        $this->assertDatabaseMissing('tagore_payment_allocations', ['payment_id' => $settledId]);
        $this->assertDatabaseMissing('tagore_financial_transactions', [
            'reference_type' => 'tagore_payment',
            'reference_id' => $settledId,
        ]);
    }

    public function test_valid_gateway_capture_settles_once_and_updates_fee_balance(): void
    {
        $service = app(OnlinePaymentService::class);
        $order = $service->createOrder((int) $this->student->id, (int) $this->parent->id, 60.00);
        $paymentId = 'pay_test_tagore_valid';
        $signature = hash_hmac('sha256', $order['gateway_order_id'].'|'.$paymentId, 'test_secret_for_acceptance');
        $data = [
            'razorpay_order_id' => $order['gateway_order_id'],
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ];

        $first = $service->confirm($data);
        $second = $service->confirm($data);

        $this->assertSame($first, $second);
        $this->assertDatabaseHas('tagore_payments', [
            'id' => $first,
            'status' => 'success',
            'amount' => '60.00',
        ]);
        $this->assertDatabaseHas('tagore_fee_obligations', [
            'id' => $this->obligationId,
            'paid_amount' => '60.00',
            'outstanding_amount' => '40.00',
            'status' => 'partial',
        ]);
        $this->assertSame(1, DB::table('tagore_payments')->where('payment_order_id', $order['order_id'])->count());
    }
}
