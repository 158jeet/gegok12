<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FamilyOnlinePaymentService;
use App\Services\Tagore\FeeReceiptMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyOnlinePaymentController extends Controller
{
    public function initiate(Request $request, FamilyOnlinePaymentService $service)
    {
        abort_unless($this->roles((int) $request->user()->id)->contains('PARENT'), 403);
        $data = $request->validate([
            'allocations' => 'required|array|min:1|max:100',
            'allocations.*.fee_obligation_id' => 'required|integer|min:1',
            'allocations.*.amount' => 'required|numeric|min:0.01|max:10000000',
        ]);

        if (config('tagore.payment_gateway') !== 'razorpay'
            || !config('services.razorpay.key')
            || !config('services.razorpay.secret')) {
            return redirect()->route('tagore.parent.dashboard')
                ->with('error', 'Online family payments are not configured yet. Please contact the accounts office.');
        }

        $order = $service->createOrder((int) $request->user()->id, $data['allocations']);
        return view('tagore.payments.family-checkout', compact('order'));
    }

    public function confirm(Request $request, FamilyOnlinePaymentService $service)
    {
        abort_unless($this->roles((int) $request->user()->id)->contains('PARENT'), 403);
        $data = $request->validate([
            'razorpay_order_id' => 'required|string|max:120',
            'razorpay_payment_id' => 'required|string|max:120',
            'razorpay_signature' => 'required|string|max:255',
        ]);

        $paymentIds = $service->confirm($data, (int) $request->user()->id);
        $payments = DB::table('tagore_payments')->whereIn('id', $paymentIds)->get();
        if ($payments->contains(fn ($payment) => $payment->status === 'review_required')) {
            return redirect()->route('tagore.parent.dashboard')->with('error',
                'The gateway captured your family payment, but at least one fee balance changed during checkout. The full family payment is safely held for accounts reconciliation. Please do not pay again.');
        }

        foreach ($paymentIds as $paymentId) {
            if (DB::table('tagore_payments')->where('id', $paymentId)->where('status', 'success')->exists()) {
                app(FeeReceiptMailer::class)->send((int) $paymentId);
            }
        }

        return redirect()->route('tagore.parent.dashboard')
            ->with('success', 'Family payment received successfully across '.count($paymentIds).' child / institution account(s). Receipts have been generated.');
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('ur.status', 'active')->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))->pluck('r.code')->unique()->values();
    }
}
