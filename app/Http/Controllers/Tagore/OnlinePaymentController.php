<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\OnlinePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnlinePaymentController extends Controller
{
    public function initiate(Request $request, int $studentId, OnlinePaymentService $service)
    {
        $userId = (int) $request->user()->id;
        abort_unless(DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists(), 403);
        $data = $request->validate(['amount' => ['required','numeric','min:1']]);
        abort_unless(config('services.razorpay.key') && config('services.razorpay.secret'), 503, 'Online payments are not configured.');
        $order = $service->createOrder($studentId, $userId, (float) $data['amount']);
        return view('tagore.payments.checkout', compact('order'));
    }

    public function confirm(Request $request, OnlinePaymentService $service)
    {
        $data = $request->validate(['razorpay_order_id' => ['required','string'], 'razorpay_payment_id' => ['required','string'], 'razorpay_signature' => ['required','string']]);
        $paymentId = $service->confirm($data);
        $payment = DB::table('tagore_payments')->where('id', $paymentId)->first();
        return redirect()->route('tagore.fees.student', ['studentId' => $payment->student_id])->with('success', 'Payment received successfully. Receipt '.$payment->id.'.');
    }

    public function webhook(Request $request, string $gateway, OnlinePaymentService $service)
    {
        abort_unless($gateway === 'razorpay', 404);
        $signature = (string) $request->header('X-Razorpay-Signature');
        abort_unless($signature !== '', 400);
        $service->webhook($signature, $request->getContent(), $request->json()->all());
        return response()->json(['ok' => true]);
    }
}
