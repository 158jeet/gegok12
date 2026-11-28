<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use App\Services\Tagore\FeeReceiptMailer;

class OnlinePaymentService
{
    public function createOrder(int $studentId, int $parentUserId, float $amount, array $requestedAllocations = []): array
    {
        $this->assertGatewayConfigured();

        // Reserve the fee balance and persist a local order in a short transaction.
        // Never hold fee-row locks while waiting for the external payment gateway.
        $order = DB::transaction(function () use ($studentId, $parentUserId, $amount, $requestedAllocations) {
            $student = DB::table('users')->where('id', $studentId)->first(['id', 'name', 'school_id']);
            if (!$student) throw ValidationException::withMessages(['student' => 'Student not found.']);
            $institutionId = DB::table('tagore_institutions')->where('school_id', $student->school_id)->where('status', 'active')->value('id');
            if (!$institutionId) throw ValidationException::withMessages(['student' => 'Student institution is not configured.']);
            $amount = round($amount, 2);

            $obligations = DB::table('tagore_fee_obligations')
                ->where('student_id', $studentId)
                ->where('institution_id', $institutionId)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where('outstanding_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $reserved = DB::table('tagore_payment_order_allocations as a')
                ->join('tagore_payment_orders as o', 'o.id', '=', 'a.payment_order_id')
                ->whereIn('o.status', ['created', 'payment_initiated', 'review_required'])
                ->where(function ($query) { $query->where('o.status', 'review_required')->orWhere('o.expires_at', '>', now()); })
                ->whereIn('a.fee_obligation_id', $obligations->pluck('id'))
                ->groupBy('a.fee_obligation_id')
                ->select('a.fee_obligation_id', DB::raw('SUM(a.amount) as reserved_amount'))
                ->pluck('reserved_amount', 'a.fee_obligation_id');

            $availableByObligation = [];
            foreach ($obligations as $obligation) {
                $availableByObligation[(int) $obligation->id] = max(
                    0,
                    round((float) $obligation->outstanding_amount - (float) ($reserved[$obligation->id] ?? 0), 2)
                );
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            $allocations = [];
            if ($requestedAllocations) {
                $seen = [];
                foreach ($requestedAllocations as $row) {
                    $obligationId = (int) ($row['fee_obligation_id'] ?? 0);
                    $allocationAmount = round((float) ($row['amount'] ?? 0), 2);
                    if (
                        $obligationId <= 0
                        || isset($seen[$obligationId])
                        || !array_key_exists($obligationId, $availableByObligation)
                        || $allocationAmount <= 0
                        || $allocationAmount > $availableByObligation[$obligationId] + 0.009
                    ) {
                        throw ValidationException::withMessages([
                            'allocations' => 'Select valid outstanding fee amounts. Refresh the statement if another checkout is active.',
                        ]);
                    }
                    $seen[$obligationId] = true;
                    $allocations[] = [
                        'fee_obligation_id' => $obligationId,
                        'amount' => $allocationAmount,
                    ];
                }

                $selectedTotal = round((float) collect($allocations)->sum('amount'), 2);
                if (abs($selectedTotal - $amount) > 0.009) {
                    throw ValidationException::withMessages([
                        'amount' => 'Payment amount must equal the sum of selected fee allocations.',
                    ]);
                }
            } else {
                $availableTotal = round(array_sum($availableByObligation), 2);
                if ($amount > $availableTotal + 0.009) {
                    throw ValidationException::withMessages([
                        'amount' => 'Payment amount exceeds the fee balance available after other active checkouts. Please refresh the fee statement and try again.',
                    ]);
                }

                $remaining = $amount;
                foreach ($obligations as $obligation) {
                    if ($remaining <= 0.009) break;
                    $available = $availableByObligation[(int) $obligation->id] ?? 0;
                    $allocate = min($remaining, $available);
                    if ($allocate <= 0) continue;
                    $allocations[] = [
                        'fee_obligation_id' => (int) $obligation->id,
                        'amount' => round($allocate, 2),
                    ];
                    $remaining = round($remaining - $allocate, 2);
                }
                if ($remaining > 0.009) {
                    throw ValidationException::withMessages(['amount' => 'Unable to allocate the requested payment.']);
                }
            }

            $orderId = DB::table('tagore_payment_orders')->insertGetId([
                'student_id' => $studentId, 'parent_user_id' => $parentUserId,
                'institution_id' => $institutionId, 'amount' => $amount,
                'currency' => 'INR', 'purpose' => 'School fee payment',
                'status' => 'created', 'gateway' => config('tagore.payment_gateway'),
                'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($allocations as $allocation) {
                DB::table('tagore_payment_order_allocations')->insert([
                    'payment_order_id' => $orderId,
                    'fee_obligation_id' => $allocation['fee_obligation_id'],
                    'amount' => $allocation['amount'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'order_id' => $orderId, 'amount' => $amount,
                'student_name' => $student->name,
            ];
        });

        // Gateway I/O happens after the reservation transaction commits.
        try {
            $gateway = Http::timeout(15)
                ->withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round($order['amount'] * 100),
                    'currency' => 'INR',
                    'receipt' => 'TAG-PAY-' . $order['order_id'],
                    'notes' => [
                        'tagore_payment_order_id' => (string) $order['order_id'],
                        'student_id' => (string) $studentId,
                    ],
                ]);
        } catch (\Throwable $exception) {
            DB::table('tagore_payment_orders')->where('id', $order['order_id'])
                ->where('status', 'created')
                ->update(['status' => 'failed', 'updated_at' => now()]);
            throw ValidationException::withMessages(['payment' => 'Payment gateway order could not be created.']);
        }

        $gatewayOrderId = $gateway->json('id');
        if (!$gateway->successful() || !$gatewayOrderId) {
            DB::table('tagore_payment_orders')->where('id', $order['order_id'])
                ->where('status', 'created')
                ->update(['status' => 'failed', 'updated_at' => now()]);
            throw ValidationException::withMessages(['payment' => 'Payment gateway order could not be created.']);
        }

        $updated = DB::table('tagore_payment_orders')->where('id', $order['order_id'])
            ->where('status', 'created')
            ->update([
                'gateway_order_id' => (string) $gatewayOrderId,
                'status' => 'payment_initiated',
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['payment' => 'Payment order could not be finalized. Please refresh the fee statement before trying again.']);
        }

        return [
            'order_id' => $order['order_id'],
            'gateway_order_id' => (string) $gatewayOrderId,
            'amount' => $order['amount'],
            'currency' => 'INR',
            'key' => config('services.razorpay.key'),
            'student_name' => $order['student_name'],
        ];
    }

    public function confirm(array $data): int
    {
        $this->assertGatewayConfigured();
        $order = DB::table('tagore_payment_orders')->where('gateway_order_id', $data['razorpay_order_id'])->first();
        if (!$order) {
            throw ValidationException::withMessages(['payment' => 'Payment order not found.']);
        }
        if (!empty($order->family_payment_group_id)) {
            throw ValidationException::withMessages(['payment' => 'This order belongs to a family checkout and must be confirmed through the family payment flow.']);
        }

        $expected = hash_hmac('sha256', $order->gateway_order_id.'|'.$data['razorpay_payment_id'], (string) config('services.razorpay.secret'));
        if (!hash_equals($expected, $data['razorpay_signature'])) {
            throw ValidationException::withMessages(['payment' => 'Payment signature verification failed.']);
        }

        $response = Http::timeout(15)
            ->withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
            ->get('https://api.razorpay.com/v1/payments/'.$data['razorpay_payment_id']);
        if (!$response->successful()) {
            throw ValidationException::withMessages(['payment' => 'Could not verify the captured payment with Razorpay. Please retry confirmation.']);
        }

        return $this->settle((int) $order->id, (string) $data['razorpay_payment_id'], $response->json() ?: []);
    }
    public function webhook(string $signature, string $rawBody, array $event, string $eventId): void
    {
        $webhookSecret = (string) config('services.razorpay.webhook_secret');
        if ($webhookSecret === '') {
            throw ValidationException::withMessages(['webhook' => 'Razorpay webhook secret is not configured.']);
        }

        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);
        if (!hash_equals($expected, $signature)) {
            throw ValidationException::withMessages(['webhook' => 'Webhook signature verification failed.']);
        }

        $eventId = $eventId !== '' ? $eventId : (string) ($event['id'] ?? '');
        if ($eventId === '') {
            throw ValidationException::withMessages(['webhook' => 'Webhook event id is required for idempotent processing.']);
        }

        $paymentIds = DB::transaction(function () use ($event, $eventId) {
            if (DB::table('tagore_payment_events')->where('gateway', 'razorpay')->where('event_id', $eventId)->exists()) {
                return [];
            }

            DB::table('tagore_payment_events')->insert([
                'gateway' => 'razorpay',
                'event_id' => $eventId,
                'event_type' => (string) ($event['event'] ?? 'unknown'),
                'payload_json' => json_encode($event, JSON_UNESCAPED_SLASHES),
                'received_at' => now(),
                'processing_status' => 'received',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (($event['event'] ?? '') !== 'payment.captured') {
                return [];
            }

            $entity = $event['payload']['payment']['entity'] ?? [];
            $gatewayOrderId = (string) ($entity['order_id'] ?? '');
            $gatewayPaymentId = (string) ($entity['id'] ?? '');
            if ($gatewayOrderId === '' || $gatewayPaymentId === '') {
                return [];
            }

            $familyGroup = DB::table('tagore_family_payment_groups')->where('gateway_order_id', $gatewayOrderId)->first();
            if ($familyGroup) {
                return app(FamilyOnlinePaymentService::class)->settleByGatewayOrder($gatewayOrderId, $gatewayPaymentId, $entity);
            }

            $paymentId = $this->settleByGatewayOrder($gatewayOrderId, $gatewayPaymentId, $entity);
            return $paymentId ? [(int) $paymentId] : [];
        });

        foreach ($paymentIds as $paymentId) {
            $payment = DB::table('tagore_payments')->where('id', $paymentId)->where('status','success')->first();
            if ($payment) {
                app(FeeReceiptMailer::class)->send($paymentId);
            }
        }
    }
    private function assertGatewayConfigured(): void
    {
        $gateway = config('tagore.payment_gateway');
        if (!$gateway) {
            throw ValidationException::withMessages(['payment' => 'Online payments are disabled until a payment gateway is configured.']);
        }
        if ($gateway !== 'razorpay') {
            throw ValidationException::withMessages(['payment' => 'Configured payment gateway is not supported yet: ' . $gateway]);
        }
        if (!config('services.razorpay.key') || !config('services.razorpay.secret')) {
            throw ValidationException::withMessages(['payment' => 'Payment gateway credentials are not configured.']);
        }
    }
    private function settleByGatewayOrder(string $gatewayOrderId, string $gatewayPaymentId, array $gatewayEntity): int
    {
        $order = DB::table('tagore_payment_orders')
            ->where('gateway_order_id', $gatewayOrderId)
            ->whereNull('family_payment_group_id')
            ->lockForUpdate()
            ->first();

        return $order ? $this->settle((int) $order->id, $gatewayPaymentId, $gatewayEntity) : 0;
    }
    private function settle(int $orderId, string $gatewayPaymentId, array $gatewayEntity): int
    {
        return DB::transaction(function () use ($orderId, $gatewayPaymentId, $gatewayEntity) {
            $order = DB::table('tagore_payment_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                throw ValidationException::withMessages(['payment' => 'Payment order not found.']);
            }
            if (!empty($order->family_payment_group_id)) {
                throw ValidationException::withMessages(['payment' => 'Family payment orders must be settled as one family group.']);
            }

            $existing = DB::table('tagore_payments')->where('payment_order_id', $orderId)
                ->whereIn('status', ['success', 'review_required'])->first();
            if ($existing) {
                return (int) $existing->id;
            }
            if (!in_array($order->status, ['payment_initiated', 'created'], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment order is no longer eligible for settlement.']);
            }

            $paymentData = $this->capturedPaymentData($order, $gatewayPaymentId, $gatewayEntity);
            if (!$paymentData['captured']) {
                throw ValidationException::withMessages(['payment' => 'The gateway has not confirmed a captured payment. No fee balance was changed.']);
            }

            $now = now();
            $allocations = DB::table('tagore_payment_order_allocations')
                ->where('payment_order_id', $orderId)
                ->orderBy('fee_obligation_id')
                ->get();
            $obligationIds = $allocations->pluck('fee_obligation_id')->unique()->sort()->values();
            $lockedObligations = DB::table('tagore_fee_obligations')
                ->whereIn('id', $obligationIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $studentActive = DB::table('users')->where('id', $order->student_id)
                ->where('usergroup_id', 6)->where('status', 'active')->whereNull('deleted_at')->exists();
            $institutionActive = DB::table('tagore_institutions')->where('id', $order->institution_id)
                ->where('status', 'active')->whereNull('deleted_at')->exists();
            $parentLinkActive = DB::table('tagore_parent_students')->where('parent_user_id', $order->parent_user_id)
                ->where('student_id', $order->student_id)->where('status', 'active')->exists();

            $allocatedTotal = round((float) $allocations->sum('amount'), 2);
            $allocationIsCurrent = $paymentData['valid']
                && $studentActive
                && $institutionActive
                && $parentLinkActive
                && (!$order->expires_at || $now->lte($order->expires_at))
                && abs($allocatedTotal - (float) $order->amount) <= 0.009;

            foreach ($allocations as $allocation) {
                $obligation = $lockedObligations->get($allocation->fee_obligation_id);
                if (!$obligation
                    || (int) $obligation->student_id !== (int) $order->student_id
                    || (int) $obligation->institution_id !== (int) $order->institution_id
                    || !in_array($obligation->status, ['pending', 'partial', 'overdue'], true)
                    || (float) $allocation->amount > (float) $obligation->outstanding_amount + 0.009) {
                    $allocationIsCurrent = false;
                    break;
                }
            }

            if (!$allocationIsCurrent) {
                $reason = !$paymentData['valid']
                    ? 'Gateway capture amount, currency, order or payment identity did not match the checkout.'
                    : 'Gateway captured payment, but the fee balance or parent/institution eligibility changed after checkout.';
                $paymentId = DB::table('tagore_payments')->insertGetId([
                    'payment_order_id' => $orderId,
                    'student_id' => $order->student_id,
                    'parent_user_id' => $order->parent_user_id,
                    'institution_id' => $order->institution_id,
                    'amount' => $paymentData['amount'],
                    'currency' => $paymentData['currency'] ?: 'INR',
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $order->gateway_order_id,
                    'gateway_payment_id' => $gatewayPaymentId,
                    'status' => 'review_required',
                    'paid_at' => $now,
                    'notes' => $reason.' Expected checkout amount: '.$order->amount.' INR.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('tagore_payment_reconciliations')->insert([
                    'payment_id' => $paymentId,
                    'gateway' => 'razorpay',
                    'settlement_amount' => $paymentData['amount'],
                    'status' => 'pending',
                    'notes' => $reason.' Actual gateway capture: '.$paymentData['amount'].' '.$paymentData['currency'].'.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('tagore_payment_orders')->where('id', $orderId)->update([
                    'status' => 'review_required',
                    'updated_at' => $now,
                ]);

                return $paymentId;
            }

            $receiptNo = $this->nextReceiptNo((int) $order->institution_id);
            $paymentId = DB::table('tagore_payments')->insertGetId([
                'payment_order_id' => $orderId,
                'receipt_no' => $receiptNo,
                'student_id' => $order->student_id,
                'parent_user_id' => $order->parent_user_id,
                'institution_id' => $order->institution_id,
                'amount' => $order->amount,
                'currency' => 'INR',
                'gateway' => 'razorpay',
                'gateway_order_id' => $order->gateway_order_id,
                'gateway_payment_id' => $gatewayPaymentId,
                'status' => 'success',
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($allocations as $allocation) {
                $obligation = $lockedObligations->get($allocation->fee_obligation_id);
                DB::table('tagore_payment_allocations')->insert([
                    'payment_id' => $paymentId,
                    'fee_obligation_id' => $allocation->fee_obligation_id,
                    'fee_installment_id' => $allocation->fee_installment_id,
                    'amount' => $allocation->amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $paid = round((float) $obligation->paid_amount + (float) $allocation->amount, 2);
                $outstanding = max(0, round((float) $obligation->net_amount - $paid, 2));
                DB::table('tagore_fee_obligations')->where('id', $obligation->id)->update([
                    'paid_amount' => $paid,
                    'outstanding_amount' => $outstanding,
                    'status' => $outstanding <= 0.009 ? 'paid' : 'partial',
                    'updated_at' => $now,
                ]);
            }

            DB::table('tagore_financial_transactions')->insert([
                'institution_id' => $order->institution_id,
                'student_id' => $order->student_id,
                'parent_user_id' => $order->parent_user_id,
                'transaction_type' => 'PAYMENT',
                'reference_type' => 'tagore_payment',
                'reference_id' => $paymentId,
                'debit' => 0,
                'credit' => $order->amount,
                'description' => 'Online fee payment',
                'transaction_date' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('tagore_payment_orders')->where('id', $orderId)->update([
                'status' => 'paid',
                'updated_at' => $now,
            ]);

            return $paymentId;
        });
    }

    private function capturedPaymentData(object $order, string $paymentId, array $entity): array
    {
        $captured = (string) ($entity['status'] ?? '') === 'captured';
        $amountPaise = (int) ($entity['amount'] ?? 0);
        $amount = round($amountPaise / 100, 2);
        $currency = strtoupper((string) ($entity['currency'] ?? ''));

        return [
            'captured' => $captured,
            'amount' => $amount,
            'currency' => $currency,
            'valid' => $captured
                && (string) ($entity['id'] ?? '') === $paymentId
                && (string) ($entity['order_id'] ?? '') === (string) $order->gateway_order_id
                && $amountPaise === (int) round((float) $order->amount * 100)
                && $currency === 'INR',
        ];
    }
    private function nextReceiptNo(int $institutionId): string
    {
        $last=DB::table('tagore_payments')->where('institution_id',$institutionId)->whereNotNull('receipt_no')->lockForUpdate()->orderByDesc('id')->value('receipt_no');
        $sequence=$last&&preg_match('/(\d+)$/',$last,$m)?((int)$m[1])+1:1;
        return sprintf('TAG-%d-%06d',$institutionId,$sequence);
    }
}
