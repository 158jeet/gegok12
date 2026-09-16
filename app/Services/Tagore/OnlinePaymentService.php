<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OnlinePaymentService
{
    public function createOrder(int $studentId, int $parentUserId, float $amount): array
    {
        return DB::transaction(function () use ($studentId, $parentUserId, $amount) {
            $student = DB::table('users')->where('id', $studentId)->first(['id', 'name', 'school_id']);
            if (!$student) throw ValidationException::withMessages(['student' => 'Student not found.']);
            $institutionId = DB::table('tagore_institutions')->where('school_id', $student->school_id)->where('status', 'active')->value('id');
            if (!$institutionId) throw ValidationException::withMessages(['student' => 'Student institution is not configured.']);

            $amount = round($amount, 2);
            $outstanding = (float) DB::table('tagore_fee_obligations')->where('student_id', $studentId)->where('institution_id', $institutionId)->whereIn('status', ['pending','partial','overdue'])->sum('outstanding_amount');
            if ($amount <= 0 || $amount > round($outstanding, 2) + 0.009) throw ValidationException::withMessages(['amount' => 'Payment amount must be positive and cannot exceed outstanding fees.']);

            $orderId = DB::table('tagore_payment_orders')->insertGetId([
                'student_id' => $studentId, 'parent_user_id' => $parentUserId, 'institution_id' => $institutionId,
                'amount' => $amount, 'currency' => 'INR', 'purpose' => 'School fee payment', 'status' => 'created',
                'gateway' => 'razorpay', 'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now(),
            ]);

            $remaining = $amount;
            $open = DB::table('tagore_fee_obligations')->where('student_id', $studentId)->where('institution_id', $institutionId)->where('outstanding_amount', '>', 0)->orderBy('due_date')->orderBy('id')->lockForUpdate()->get();
            foreach ($open as $obligation) {
                if ($remaining <= 0.009) break;
                $allocate = min($remaining, (float) $obligation->outstanding_amount);
                DB::table('tagore_payment_order_allocations')->insert(['payment_order_id' => $orderId, 'fee_obligation_id' => $obligation->id, 'amount' => round($allocate, 2), 'created_at' => now(), 'updated_at' => now()]);
                $remaining = round($remaining - $allocate, 2);
            }
            if ($remaining > 0.009) throw ValidationException::withMessages(['amount' => 'Unable to allocate the requested payment.']);

            $gateway = Http::withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))->post('https://api.razorpay.com/v1/orders', ['amount' => (int) round($amount * 100), 'currency' => 'INR', 'receipt' => 'TAG-PAY-'.$orderId, 'notes' => ['tagore_payment_order_id' => (string) $orderId, 'student_id' => (string) $studentId]]);
            if (!$gateway->successful() || !$gateway->json('id')) throw ValidationException::withMessages(['payment' => 'Payment gateway order could not be created.']);
            $gatewayOrderId = (string) $gateway->json('id');
            DB::table('tagore_payment_orders')->where('id', $orderId)->update(['gateway_order_id' => $gatewayOrderId, 'status' => 'payment_initiated', 'updated_at' => now()]);

            return ['order_id' => $orderId, 'gateway_order_id' => $gatewayOrderId, 'amount' => $amount, 'currency' => 'INR', 'key' => config('services.razorpay.key'), 'student_name' => $student->name];
        });
    }

    public function confirm(array $data): int
    {
        $order = DB::table('tagore_payment_orders')->where('gateway_order_id', $data['razorpay_order_id'])->lockForUpdate()->first();
        if (!$order) throw ValidationException::withMessages(['payment' => 'Payment order not found.']);
        $expected = hash_hmac('sha256', $order->gateway_order_id.'|'.$data['razorpay_payment_id'], (string) config('services.razorpay.secret'));
        if (!hash_equals($expected, $data['razorpay_signature'])) throw ValidationException::withMessages(['payment' => 'Payment signature verification failed.']);
        return $this->settle($order->id, $data['razorpay_payment_id']);
    }

    public function webhook(string $signature, string $payload, array $event): void
    {
        $expected = hash_hmac('sha256', $payload, (string) config('services.razorpay.webhook_secret'));
        if (!hash_equals($expected, $signature)) throw ValidationException::withMessages(['webhook' => 'Invalid webhook signature.']);
        $eventId = (string) ($event['account_id'] ?? '').':'.(string) ($event['entity']['id'] ?? uniqid('evt_', true));
        DB::transaction(function () use ($event, $eventId) {
            $existing = DB::table('tagore_payment_events')->where('gateway', 'razorpay')->where('event_id', $eventId)->lockForUpdate()->exists();
            if ($existing) return;
            DB::table('tagore_payment_events')->insert(['gateway' => 'razorpay', 'event_id' => $eventId, 'event_type' => (string) ($event['event'] ?? 'unknown'), 'payload_json' => json_encode($event), 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            if (($event['event'] ?? '') === 'payment.captured') {
                $entity = $event['payload']['payment']['entity'] ?? [];
                if (!empty($entity['order_id']) && !empty($entity['id'])) $this->settleByGatewayOrder((string) $entity['order_id'], (string) $entity['id']);
            }
        });
    }

    private function settleByGatewayOrder(string $gatewayOrderId, string $gatewayPaymentId): int
    {
        $order = DB::table('tagore_payment_orders')->where('gateway_order_id', $gatewayOrderId)->lockForUpdate()->first();
        if (!$order) return 0;
        return $this->settle($order->id, $gatewayPaymentId);
    }

    private function settle(int $orderId, string $gatewayPaymentId): int
    {
        return DB::transaction(function () use ($orderId, $gatewayPaymentId) {
            $order = DB::table('tagore_payment_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw ValidationException::withMessages(['payment' => 'Payment order not found.']);
            $existing = DB::table('tagore_payments')->where('payment_order_id', $orderId)->where('status', 'success')->first();
            if ($existing) return (int) $existing->id;
            $now = now();
            $paymentId = DB::table('tagore_payments')->insertGetId(['payment_order_id' => $orderId, 'student_id' => $order->student_id, 'parent_user_id' => $order->parent_user_id, 'institution_id' => $order->institution_id, 'amount' => $order->amount, 'currency' => 'INR', 'gateway' => 'razorpay', 'gateway_order_id' => $order->gateway_order_id, 'gateway_payment_id' => $gatewayPaymentId, 'status' => 'success', 'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            foreach (DB::table('tagore_payment_order_allocations')->where('payment_order_id', $orderId)->get() as $allocation) {
                DB::table('tagore_payment_allocations')->insert(['payment_id' => $paymentId, 'fee_obligation_id' => $allocation->fee_obligation_id, 'fee_installment_id' => $allocation->fee_installment_id, 'amount' => $allocation->amount, 'created_at' => $now, 'updated_at' => $now]);
                $obligation = DB::table('tagore_fee_obligations')->where('id', $allocation->fee_obligation_id)->lockForUpdate()->first();
                $paid = round((float) $obligation->paid_amount + (float) $allocation->amount, 2);
                $outstanding = max(0, round((float) $obligation->net_amount - $paid, 2));
                DB::table('tagore_fee_obligations')->where('id', $obligation->id)->update(['paid_amount' => $paid, 'outstanding_amount' => $outstanding, 'status' => $outstanding <= 0.009 ? 'paid' : 'partial', 'updated_at' => $now]);
            }
            DB::table('tagore_financial_transactions')->insert(['institution_id' => $order->institution_id, 'student_id' => $order->student_id, 'parent_user_id' => $order->parent_user_id, 'transaction_type' => 'PAYMENT', 'reference_type' => 'tagore_payment', 'reference_id' => $paymentId, 'debit' => 0, 'credit' => $order->amount, 'description' => 'Online fee payment', 'transaction_date' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('tagore_payment_orders')->where('id', $orderId)->update(['status' => 'paid', 'updated_at' => $now]);
            return $paymentId;
        });
    }
}
