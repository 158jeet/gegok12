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
        $order = DB::transaction(function () use ($studentId, $parentUserId, $amount, $requestedAllocations) {
            $student = DB::table('users')->where('id', $studentId)->first(['id', 'name', 'school_id']);
            if (!$student) throw ValidationException::withMessages(['student' => 'Student not found.']);
            $institutionId = DB::table('tagore_institutions')->where('school_id', $student->school_id)->where('status', 'active')->value('id');
            if (!$institutionId) throw ValidationException::withMessages(['student' => 'Student institution is not configured.']);
            $amount = round($amount, 2);
            $obligations = DB::table('tagore_fee_obligations')
                ->where('student_id', $studentId)->where('institution_id', $institutionId)
                ->whereIn('status', ['pending', 'partial', 'overdue'])->where('outstanding_amount', '>', 0)
                ->orderBy('due_date')->orderBy('id')->lockForUpdate()->get();
            $reserved = DB::table('tagore_payment_order_allocations as a')
                ->join('tagore_payment_orders as o', 'o.id', '=', 'a.payment_order_id')
                ->whereIn('o.status', ['created', 'payment_initiated'])->where('o.expires_at', '>', now())
                ->whereIn('a.fee_obligation_id', $obligations->pluck('id'))
                ->groupBy('a.fee_obligation_id')
                ->select('a.fee_obligation_id', DB::raw('SUM(a.amount) as reserved_amount'))
                ->pluck('reserved_amount', 'a.fee_obligation_id');
            $available = [];
            foreach ($obligations as $obligation) {
                $available[(int) $obligation->id] = max(0, round((float) $obligation->outstanding_amount - (float) ($reserved[$obligation->id] ?? 0), 2));
            }
            if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);

            $allocations = [];
            if ($requestedAllocations) {
                $seen = [];
                foreach ($requestedAllocations as $row) {
                    $id = (int) ($row['fee_obligation_id'] ?? 0);
                    $value = round((float) ($row['amount'] ?? 0), 2);
                    if ($id <= 0 || isset($seen[$id]) || !array_key_exists($id, $available) || $value <= 0 || $value > $available[$id] + 0.009) {
                        throw ValidationException::withMessages(['allocations' => 'Select valid outstanding fee amounts. Refresh the statement if another checkout is active.']);
                    }
                    $seen[$id] = true;
                    $allocations[] = ['fee_obligation_id' => $id, 'amount' => $value];
                }
                if (abs(round((float) collect($allocations)->sum('amount'), 2) - $amount) > 0.009) {
                    throw ValidationException::withMessages(['amount' => 'Payment amount must equal the sum of selected fee allocations.']);
                }
            } else {
                if ($amount > round(array_sum($available), 2) + 0.009) {
                    throw ValidationException::withMessages(['amount' => 'Payment amount exceeds the fee balance available after other active checkouts. Please refresh the fee statement and try again.']);
                }
                $remaining = $amount;
                foreach ($obligations as $obligation) {
                    if ($remaining <= 0.009) break;
                    $value = min($remaining, $available[(int) $obligation->id]);
                    if ($value <= 0) continue;
                    $allocations[] = ['fee_obligation_id' => (int) $obligation->id, 'amount' => round($value, 2)];
                    $remaining = round($remaining - $value, 2);
                }
                if ($remaining > 0.009) throw ValidationException::withMessages(['amount' => 'Unable to allocate the requested payment.']);
            }

            $orderId = DB::table('tagore_payment_orders')->insertGetId([
                'student_id' => $studentId, 'parent_user_id' => $parentUserId, 'institution_id' => $institutionId,
                'amount' => $amount, 'currency' => 'INR', 'purpose' => 'School fee payment',
                'status' => 'created', 'gateway' => config('tagore.payment_gateway'),
                'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($allocations as $allocation) {
                DB::table('tagore_payment_order_allocations')->insert([
                    'payment_order_id' => $orderId, 'fee_obligation_id' => $allocation['fee_obligation_id'],
                    'amount' => $allocation['amount'], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return ['order_id' => $orderId, 'amount' => $amount, 'student_name' => $student->name];
        });

        try {
            $gateway = Http::timeout(15)->withBasicAuth((string) config('services.razorpay.key'), (string) config('services.razorpay.secret'))
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round($order['amount'] * 100), 'currency' => 'INR',
                    'receipt' => 'TAG-PAY-' . $order['order_id'],
                    'notes' => ['tagore_payment_order_id' => (string) $order['order_id'], 'student_id' => (string) $studentId],
                ]);
        } catch (\Throwable $exception) {
            DB::table('tagore_payment_orders')->where('id', $order['order_id'])->where('status', 'created')
                ->update(['status' => 'failed', 'updated_at' => now()]);
            throw ValidationException::withMessages(['payment' => 'Payment gateway order could not be created.']);
        }
        $gatewayOrderId = $gateway->json('id');
        if (!$gateway->successful() || !$gatewayOrderId) {
            DB::table('tagore_payment_orders')->where('id', $order['order_id'])->where('status', 'created')
                ->update(['status' => 'failed', 'updated_at' => now()]);
            throw ValidationException::withMessages(['payment' => 'Payment gateway order could not be created.']);
        }
        $updated = DB::table('tagore_payment_orders')->where('id', $order['order_id'])->where('status', 'created')
            ->update(['gateway_order_id' => (string) $gatewayOrderId, 'status' => 'payment_initiated', 'updated_at' => now()]);
        if ($updated !== 1) throw ValidationException::withMessages(['payment' => 'Payment order could not be finalized. Please refresh the fee statement before trying again.']);
        return [
            'order_id' => $order['order_id'], 'gateway_order_id' => (string) $gatewayOrderId,
            'amount' => $order['amount'], 'currency' => 'INR', 'key' => config('services.razorpay.key'),
            'student_name' => $order['student_name'],
        ];
    }

    public function confirm(array $data): int
    {
        $this->assertGatewayConfigured();
        $order=DB::table('tagore_payment_orders')->where('gateway_order_id',$data['razorpay_order_id'])->lockForUpdate()->first();
        if(!$order) throw ValidationException::withMessages(['payment'=>'Payment order not found.']);
        $expected=hash_hmac('sha256',$order->gateway_order_id.'|'.$data['razorpay_payment_id'],(string)config('services.razorpay.secret'));
        if(!hash_equals($expected,$data['razorpay_signature'])) throw ValidationException::withMessages(['payment'=>'Payment signature verification failed.']);
        return $this->settle($order->id,$data['razorpay_payment_id']);
    }

    public function webhook(string $signature, string $payload, array $event, ?string $eventId=null): void
    {
        $this->assertGatewayConfigured();
        $expected=hash_hmac('sha256',$payload,(string)config('services.razorpay.webhook_secret'));
        if(!hash_equals($expected,$signature)) throw ValidationException::withMessages(['webhook'=>'Invalid webhook signature.']);
        $eventId=$eventId ?: hash('sha256',$payload);
        $paymentId = DB::transaction(function() use($event,$eventId){
            if(DB::table('tagore_payment_events')->where('gateway','razorpay')->where('event_id',$eventId)->lockForUpdate()->exists()) return 0;
            DB::table('tagore_payment_events')->insert(['gateway'=>'razorpay','event_id'=>$eventId,'event_type'=>(string)($event['event']??'unknown'),'payload_json'=>json_encode($event),'received_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            if(($event['event']??'')==='payment.captured'){
                $entity=$event['payload']['payment']['entity']??[];
                if(!empty($entity['order_id'])&&!empty($entity['id'])) return $this->settleByGatewayOrder((string)$entity['order_id'],(string)$entity['id']);
            }
            return 0;
        });
        if ($paymentId && DB::table('tagore_payments')->where('id',$paymentId)->where('status','success')->exists()) {
            app(FeeReceiptMailer::class)->send((int)$paymentId);
        }
    }

    private function assertGatewayConfigured(): void
    {
        $gateway = config('tagore.payment_gateway');
        if (!$gateway) throw ValidationException::withMessages(['payment' => 'Online payments are disabled until a payment gateway is configured.']);
        if ($gateway !== 'razorpay') throw ValidationException::withMessages(['payment' => 'Configured payment gateway is not supported yet: '.$gateway]);
        if (!config('services.razorpay.key') || !config('services.razorpay.secret')) throw ValidationException::withMessages(['payment' => 'Payment gateway credentials are not configured.']);
    }

    private function settleByGatewayOrder(string $gatewayOrderId,string $gatewayPaymentId): int
    {
        $order=DB::table('tagore_payment_orders')->where('gateway_order_id',$gatewayOrderId)->lockForUpdate()->first();
        return $order ? $this->settle($order->id,$gatewayPaymentId) : 0;
    }

    private function settle(int $orderId,string $gatewayPaymentId): int
    {
        return DB::transaction(function() use($orderId,$gatewayPaymentId){
            $order=DB::table('tagore_payment_orders')->where('id',$orderId)->lockForUpdate()->first();
            if(!$order) throw ValidationException::withMessages(['payment'=>'Payment order not found.']);
            $existing=DB::table('tagore_payments')->where('payment_order_id',$orderId)->whereIn('status',['success','review_required'])->first();
            if($existing) return (int)$existing->id;
            if (!in_array($order->status, ['payment_initiated', 'created'], true)) {
                throw ValidationException::withMessages(['payment' => 'This payment order is no longer eligible for settlement.']);
            }

            $now=now();
            $allocations=DB::table('tagore_payment_order_allocations')
                ->where('payment_order_id',$orderId)
                ->orderBy('fee_obligation_id')
                ->get();
            $obligationIds=$allocations->pluck('fee_obligation_id')->unique()->sort()->values();
            $lockedObligations=DB::table('tagore_fee_obligations')
                ->whereIn('id',$obligationIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $allocatedTotal=round((float)$allocations->sum('amount'),2);
            $allocationIsCurrent=abs($allocatedTotal-(float)$order->amount)<=0.009;
            foreach($allocations as $allocation){
                $obligation=$lockedObligations->get($allocation->fee_obligation_id);
                if(!$obligation
                    || (int)$obligation->student_id !== (int)$order->student_id
                    || (int)$obligation->institution_id !== (int)$order->institution_id
                    || (float)$allocation->amount > (float)$obligation->outstanding_amount + 0.009){
                    $allocationIsCurrent=false;
                    break;
                }
            }

            if (!$allocationIsCurrent) {
                // The gateway has captured money, but the reserved fee balance changed
                // after checkout. Record it for reconciliation; never over-allocate it.
                $paymentId=DB::table('tagore_payments')->insertGetId([
                    'payment_order_id'=>$orderId,'student_id'=>$order->student_id,
                    'parent_user_id'=>$order->parent_user_id,'institution_id'=>$order->institution_id,
                    'amount'=>$order->amount,'currency'=>'INR','gateway'=>'razorpay',
                    'gateway_order_id'=>$order->gateway_order_id,'gateway_payment_id'=>$gatewayPaymentId,
                    'status'=>'review_required','paid_at'=>$now,'notes'=>'Gateway captured payment; fee balances changed after checkout. Manual reconciliation or refund is required.',
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
                DB::table('tagore_payment_reconciliations')->insert([
                    'payment_id'=>$paymentId,'gateway'=>'razorpay','settlement_amount'=>$order->amount,
                    'status'=>'pending','notes'=>'Captured payment could not be allocated because one or more fee obligations changed after checkout. Review allocation and refund if necessary.',
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
                DB::table('tagore_payment_orders')->where('id',$orderId)->update(['status'=>'review_required','updated_at'=>$now]);
                return $paymentId;
            }

            $receiptNo=$this->nextReceiptNo((int)$order->institution_id);
            $paymentId=DB::table('tagore_payments')->insertGetId(['payment_order_id'=>$orderId,'receipt_no'=>$receiptNo,'student_id'=>$order->student_id,'parent_user_id'=>$order->parent_user_id,'institution_id'=>$order->institution_id,'amount'=>$order->amount,'currency'=>'INR','gateway'=>'razorpay','gateway_order_id'=>$order->gateway_order_id,'gateway_payment_id'=>$gatewayPaymentId,'status'=>'success','paid_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
            foreach($allocations as $allocation){
                $obligation=$lockedObligations->get($allocation->fee_obligation_id);
                DB::table('tagore_payment_allocations')->insert(['payment_id'=>$paymentId,'fee_obligation_id'=>$allocation->fee_obligation_id,'fee_installment_id'=>$allocation->fee_installment_id,'amount'=>$allocation->amount,'created_at'=>$now,'updated_at'=>$now]);
                $paid=round((float)$obligation->paid_amount+(float)$allocation->amount,2);
                $outstanding=max(0,round((float)$obligation->net_amount-$paid,2));
                DB::table('tagore_fee_obligations')->where('id',$obligation->id)->update(['paid_amount'=>$paid,'outstanding_amount'=>$outstanding,'status'=>$outstanding<=0.009?'paid':'partial','updated_at'=>$now]);
            }
            DB::table('tagore_financial_transactions')->insert(['institution_id'=>$order->institution_id,'student_id'=>$order->student_id,'parent_user_id'=>$order->parent_user_id,'transaction_type'=>'PAYMENT','reference_type'=>'tagore_payment','reference_id'=>$paymentId,'debit'=>0,'credit'=>$order->amount,'description'=>'Online fee payment','transaction_date'=>$now,'created_at'=>$now,'updated_at'=>$now]);
            DB::table('tagore_payment_orders')->where('id',$orderId)->update(['status'=>'paid','updated_at'=>$now]);
            return $paymentId;
        });
    }

    private function nextReceiptNo(int $institutionId): string
    {
        $last=DB::table('tagore_payments')->where('institution_id',$institutionId)->whereNotNull('receipt_no')->lockForUpdate()->orderByDesc('id')->value('receipt_no');
        $sequence=$last&&preg_match('/(\d+)$/',$last,$m)?((int)$m[1])+1:1;
        return sprintf('TAG-%d-%06d',$institutionId,$sequence);
    }
}
