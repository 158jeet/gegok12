<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FamilyOnlinePaymentService
{
    public function createOrder(int $parentUserId, array $requestedAllocations): array
    {
        $this->assertGatewayConfigured();

        return DB::transaction(function () use ($parentUserId, $requestedAllocations) {
            if (!$requestedAllocations) {
                throw ValidationException::withMessages(['allocations' => 'Select at least one fee balance to pay.']);
            }

            $normalized = [];
            foreach ($requestedAllocations as $item) {
                $obligationId = (int) ($item['fee_obligation_id'] ?? 0);
                $amount = round((float) ($item['amount'] ?? 0), 2);
                if ($obligationId <= 0 || $amount <= 0) {
                    throw ValidationException::withMessages(['allocations' => 'Each selected fee balance must have a valid positive amount.']);
                }
                if (isset($normalized[$obligationId])) {
                    throw ValidationException::withMessages(['allocations' => 'A fee balance may only be selected once.']);
                }
                $normalized[$obligationId] = $amount;
            }

            $obligations = DB::table('tagore_fee_obligations')
                ->whereIn('id', array_keys($normalized))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($obligations->count() !== count($normalized)) {
                throw ValidationException::withMessages(['allocations' => 'One or more selected fee balances could not be found. Refresh the page and try again.']);
            }

            $reserved = DB::table('tagore_payment_order_allocations as a')
                ->join('tagore_payment_orders as o', 'o.id', '=', 'a.payment_order_id')
                ->whereIn('o.status', ['created', 'payment_initiated'])
                ->where('o.expires_at', '>', now())
                ->whereIn('a.fee_obligation_id', array_keys($normalized))
                ->groupBy('a.fee_obligation_id')
                ->select('a.fee_obligation_id', DB::raw('SUM(a.amount) as reserved_amount'))
                ->pluck('reserved_amount', 'a.fee_obligation_id');

            $groups = [];
            $total = 0.0;
            foreach ($normalized as $obligationId => $amount) {
                $obligation = $obligations->get($obligationId);
                $student = DB::table('users')->where('id', $obligation->student_id)->where('usergroup_id', 6)->whereNull('deleted_at')->first(['id', 'name', 'school_id']);
                $institution = DB::table('tagore_institutions')->where('id', $obligation->institution_id)->where('status', 'active')->first(['id', 'school_id', 'display_name']);
                $linked = DB::table('tagore_parent_students')->where('parent_user_id', $parentUserId)->where('student_id', $obligation->student_id)->where('status', 'active')->exists();

                if (!$student || !$institution || (int) $student->school_id !== (int) $institution->school_id || !$linked) {
                    throw ValidationException::withMessages(['allocations' => 'You can only pay fee balances belonging to children linked to this parent account.']);
                }

                $available = max(0, round((float) $obligation->outstanding_amount - (float) ($reserved[$obligationId] ?? 0), 2));
                if ($amount > $available + 0.009) {
                    throw ValidationException::withMessages(['allocations' => 'A selected fee balance has changed or is already reserved by another checkout. Refresh the family fee statement.']);
                }

                $key = $student->id.':'.$institution->id;
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'student' => $student,
                        'institution' => $institution,
                        'amount' => 0.0,
                        'allocations' => [],
                    ];
                }
                $groups[$key]['amount'] = round($groups[$key]['amount'] + $amount, 2);
                $groups[$key]['allocations'][] = ['fee_obligation_id' => $obligationId, 'amount' => $amount];
                $total = round($total + $amount, 2);
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['amount' => 'Family payment amount must be greater than zero.']);
            }

            $expiresAt = now()->addMinutes(30);
            $groupId = DB::table('tagore_family_payment_groups')->insertGetId([
                'parent_user_id' => $parentUserId,
                'amount' => $total,
                'currency' => 'INR',
                'gateway' => 'razorpay',
                'status' => 'created',
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $childOrderIds = [];
            foreach ($groups as $allocationGroup) {
                $orderId = DB::table('tagore_payment_orders')->insertGetId([
                    'student_id' => $allocationGroup['student']->id,
                    'parent_user_id' => $parentUserId,
                    'family_payment_group_id' => $groupId,
                    'institution_id' => $allocationGroup['institution']->id,
                    'amount' => $allocationGroup['amount'],
                    'currency' => 'INR',
                    'purpose' => 'Family fee payment',
                    'status' => 'created',
                    'gateway' => 'razorpay',
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $childOrderIds[] = $orderId;

                foreach ($allocationGroup['allocations'] as $allocation) {
                    DB::table('tagore_payment_order_allocations')->insert([
                        'payment_order_id' => $orderId,
                        'fee_obligation_id' => $allocation['fee_obligation_id'],
                        'amount' => $allocation['amount'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $gateway = Http::timeout(15)
                ->withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round($total * 100),
                    'currency' => 'INR',
                    'receipt' => 'TAG-FAM-'.$groupId,
                    'notes' => [
                        'tagore_family_payment_group_id' => (string) $groupId,
                        'parent_user_id' => (string) $parentUserId,
                    ],
                ]);

            if (!$gateway->successful() || !$gateway->json('id')) {
                throw ValidationException::withMessages(['payment' => 'Payment gateway order could not be created. No fee balance has been changed.']);
            }

            $gatewayOrderId = (string) $gateway->json('id');
            DB::table('tagore_family_payment_groups')->where('id', $groupId)->update([
                'gateway_order_id' => $gatewayOrderId,
                'status' => 'payment_initiated',
                'updated_at' => now(),
            ]);
            DB::table('tagore_payment_orders')->whereIn('id', $childOrderIds)->update([
                'gateway_order_id' => $gatewayOrderId,
                'status' => 'payment_initiated',
                'updated_at' => now(),
            ]);

            return [
                'group_id' => (int) $groupId,
                'gateway_order_id' => $gatewayOrderId,
                'amount' => $total,
                'currency' => 'INR',
                'key' => config('services.razorpay.key'),
                'parent_name' => DB::table('users')->where('id', $parentUserId)->value('name'),
                'children' => collect($groups)->map(fn ($group) => [
                    'name' => $group['student']->name,
                    'institution' => $group['institution']->display_name,
                    'amount' => $group['amount'],
                ])->values()->all(),
            ];
        });
    }

    public function confirm(array $data, int $parentUserId): array
    {
        $this->assertGatewayConfigured();
        $group = DB::table('tagore_family_payment_groups')->where('gateway_order_id', $data['razorpay_order_id'])->first();
        abort_unless($group && (int) $group->parent_user_id === $parentUserId, 404);

        $expected = hash_hmac('sha256', $group->gateway_order_id.'|'.$data['razorpay_payment_id'], (string) config('services.razorpay.secret'));
        if (!hash_equals($expected, $data['razorpay_signature'])) {
            throw ValidationException::withMessages(['payment' => 'Payment signature verification failed.']);
        }

        return $this->settleGroup((int) $group->id, (string) $data['razorpay_payment_id']);
    }

    public function settleByGatewayOrder(string $gatewayOrderId, string $gatewayPaymentId, array $gatewayEntity = []): array
    {
        $group = DB::table('tagore_family_payment_groups')->where('gateway_order_id', $gatewayOrderId)->first();
        if (!$group) {
            return [];
        }
        return $this->settleGroup((int) $group->id, $gatewayPaymentId, $gatewayEntity);
    }

    private function settleGroup(int $groupId, string $gatewayPaymentId, array $gatewayEntity = []): array
    {
        return DB::transaction(function () use ($groupId, $gatewayPaymentId, $gatewayEntity) {
            $group = DB::table('tagore_family_payment_groups')->where('id', $groupId)->lockForUpdate()->first();
            abort_unless($group, 404);

            $existingIds = DB::table('tagore_payments')->where('family_payment_group_id', $groupId)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (in_array($group->status, ['paid', 'review_required'], true) && $existingIds) {
                return $existingIds;
            }

            $orders = DB::table('tagore_payment_orders')->where('family_payment_group_id', $groupId)->orderBy('id')->lockForUpdate()->get();
            abort_if($orders->isEmpty(), 409, 'Family checkout contains no child payment orders.');

            $paymentData = $this->capturedPaymentData($group, $gatewayPaymentId, $gatewayEntity);
            if (!$paymentData['captured']) {
                throw ValidationException::withMessages(['payment' => 'Gateway has not confirmed a captured payment. No family fee ledger entries were changed.']);
            }
            $usedPaymentId = DB::table('tagore_payments')
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->where(function ($query) use ($groupId) {
                    $query->whereNull('family_payment_group_id')->orWhere('family_payment_group_id', '<>', $groupId);
                })->exists();
            abort_if($usedPaymentId, 409, 'This gateway payment is already linked to another checkout.');
            $capturedIsValid = $paymentData['valid'];
            $allocations = DB::table('tagore_payment_order_allocations as a')
                ->join('tagore_payment_orders as o', 'o.id', '=', 'a.payment_order_id')
                ->where('o.family_payment_group_id', $groupId)
                ->orderBy('a.fee_obligation_id')
                ->get(['a.*', 'o.student_id', 'o.institution_id', 'o.amount as order_amount', 'o.id as child_order_id']);
            $obligationIds = $allocations->pluck('fee_obligation_id')->unique()->sort()->values();
            $obligations = DB::table('tagore_fee_obligations')->whereIn('id', $obligationIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $orderAllocationTotals = $allocations->groupBy('child_order_id')->map(fn ($rows) => round((float) $rows->sum('amount'), 2));
            $allocationIsCurrent = $capturedIsValid
                && abs(round((float) $orders->sum('amount'), 2) - (float) $group->amount) <= 0.009
                && now()->lte($group->expires_at);

            foreach ($orders as $order) {
                if (!in_array($order->status, ['created', 'payment_initiated'], true)
                    || abs((float) ($orderAllocationTotals->get($order->id, 0)) - (float) $order->amount) > 0.009) {
                    $allocationIsCurrent = false;
                    break;
                }
            }

            foreach ($allocations as $allocation) {
                $obligation = $obligations->get($allocation->fee_obligation_id);
                if (!$obligation
                    || (int) $obligation->student_id !== (int) $allocation->student_id
                    || (int) $obligation->institution_id !== (int) $allocation->institution_id
                    || (float) $allocation->amount > (float) $obligation->outstanding_amount + 0.009) {
                    $allocationIsCurrent = false;
                    break;
                }
            }

            $now = now();
            $paymentIds = [];
            if (!$allocationIsCurrent) {
                foreach ($orders as $order) {
                    $paymentIds[] = (int) DB::table('tagore_payments')->insertGetId([
                        'payment_order_id' => $order->id,
                        'family_payment_group_id' => $groupId,
                        'student_id' => $order->student_id,
                        'parent_user_id' => $order->parent_user_id,
                        'institution_id' => $order->institution_id,
                        'amount' => $order->amount,
                        'currency' => 'INR',
                        'gateway' => 'razorpay',
                        'gateway_order_id' => $group->gateway_order_id,
                        'gateway_payment_id' => $gatewayPaymentId,
                        'status' => 'review_required',
                        'paid_at' => $now,
                        'notes' => 'Family gateway capture requires manual reconciliation; at least one fee balance or capture detail changed after checkout.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('tagore_payment_reconciliations')->insert([
                        'payment_id' => end($paymentIds),
                        'gateway' => 'razorpay',
                        'settlement_id' => $gatewayPaymentId,
                        'settlement_amount' => $order->amount,
                        'status' => 'pending',
                        'notes' => 'Family payment was captured but was not automatically allocated. Review each child balance and refund if necessary.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('tagore_payment_orders')->where('id', $order->id)->update(['status' => 'review_required', 'updated_at' => $now]);
                }
                DB::table('tagore_family_payment_groups')->where('id', $groupId)->update([
                    'status' => 'review_required',
                    'paid_at' => $now,
                    'notes' => 'Captured payment requires manual allocation or refund review.',
                    'updated_at' => $now,
                ]);
                return $paymentIds;
            }

            foreach ($orders as $order) {
                $receiptNo = $this->nextReceiptNo((int) $order->institution_id);
                $paymentId = (int) DB::table('tagore_payments')->insertGetId([
                    'payment_order_id' => $order->id,
                    'family_payment_group_id' => $groupId,
                    'receipt_no' => $receiptNo,
                    'student_id' => $order->student_id,
                    'parent_user_id' => $order->parent_user_id,
                    'institution_id' => $order->institution_id,
                    'amount' => $order->amount,
                    'currency' => 'INR',
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $group->gateway_order_id,
                    'gateway_payment_id' => $gatewayPaymentId,
                    'status' => 'success',
                    'paid_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $paymentIds[] = $paymentId;

                foreach ($allocations->where('child_order_id', $order->id) as $allocation) {
                    $obligation = $obligations->get($allocation->fee_obligation_id);
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
                    'description' => 'Family online fee payment',
                    'transaction_date' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('tagore_payment_orders')->where('id', $order->id)->update(['status' => 'paid', 'updated_at' => $now]);
            }

            DB::table('tagore_family_payment_groups')->where('id', $groupId)->update([
                'status' => 'paid',
                'paid_at' => $now,
                'updated_at' => $now,
            ]);

            return $paymentIds;
        });
    }

    private function capturedPaymentData(object $group, string $paymentId, array $entity): array
    {
        if (!$entity) {
            $response = Http::timeout(15)
                ->withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
                ->get('https://api.razorpay.com/v1/payments/'.$paymentId);
            if (!$response->successful()) {
                throw ValidationException::withMessages(['payment' => 'Could not verify the captured payment with Razorpay. Please retry confirmation.']);
            }
            $entity = $response->json() ?: [];
        }

        $captured = (string) ($entity['status'] ?? '') === 'captured';
        return [
            'captured' => $captured,
            'valid' => $captured
                && (string) ($entity['id'] ?? '') === $paymentId
                && (string) ($entity['order_id'] ?? '') === (string) $group->gateway_order_id
                && (int) ($entity['amount'] ?? 0) === (int) round((float) $group->amount * 100)
                && strtoupper((string) ($entity['currency'] ?? '')) === 'INR',
        ];
    }

    private function nextReceiptNo(int $institutionId): string
    {
        $last = DB::table('tagore_payments')->where('institution_id', $institutionId)
            ->whereNotNull('receipt_no')->lockForUpdate()->orderByDesc('id')->value('receipt_no');
        $sequence = $last && preg_match('/(\d+)$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;
        return sprintf('TAG-%d-%06d', $institutionId, $sequence);
    }

    private function assertGatewayConfigured(): void
    {
        if (config('tagore.payment_gateway') !== 'razorpay'
            || !config('services.razorpay.key')
            || !config('services.razorpay.secret')) {
            throw ValidationException::withMessages(['payment' => 'Online family payments are disabled until Razorpay is configured.']);
        }
    }
}
