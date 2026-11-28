<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\OnlinePaymentService;
use App\Services\Tagore\FeeReceiptMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnlinePaymentController extends Controller
{
    public function initiate(Request $request, int $studentId, OnlinePaymentService $service)
    {
        $userId=(int)$request->user()->id;
        abort_unless($this->roles($userId)->contains('PARENT'),403);
        abort_unless(DB::table('tagore_parent_students')->where('parent_user_id',$userId)->where('student_id',$studentId)->where('status','active')->exists(),403);
        $data=$request->validate(['amount'=>['required','numeric','min:1'],'allocations'=>['required','array','min:1'],'allocations.*.fee_obligation_id'=>['required','integer'],'allocations.*.amount'=>['required','numeric','gt:0']]);
        if (config('tagore.payment_gateway') !== 'razorpay' || !config('services.razorpay.key') || !config('services.razorpay.secret')) {
            return back()->with('error', 'Online payments are not configured yet. Please contact the school office to arrange payment.');
        }
        $order=$service->createOrder($studentId,$userId,(float)$data['amount'],$data['allocations']);
        return view('tagore.payments.checkout',compact('order'));
    }

    public function confirm(Request $request,OnlinePaymentService $service)
    {
        $data=$request->validate(['razorpay_order_id'=>['required','string'],'razorpay_payment_id'=>['required','string'],'razorpay_signature'=>['required','string']]);
        $order=DB::table('tagore_payment_orders')->where('gateway_order_id',$data['razorpay_order_id'])->first(['id','parent_user_id','student_id','status','family_payment_group_id']);
        abort_unless($order && !$order->family_payment_group_id,404);
        $userId=(int)$request->user()->id;
        $roles=$this->roles($userId);
        $authorizedParent=(int)$order->parent_user_id===$userId && DB::table('tagore_parent_students')->where('parent_user_id',$userId)->where('student_id',$order->student_id)->where('status','active')->exists();
        $authorizedStaff=$roles->intersect(['OWNER','PRINCIPAL','ACCOUNTS'])->isNotEmpty();
        abort_unless($authorizedParent || $authorizedStaff,403);
        $paymentId=$service->confirm($data);
        $payment=DB::table('tagore_payments')->where('id',$paymentId)->firstOrFail();
        if ($payment->status === 'review_required') {
            return redirect()->route('tagore.fees.student',['studentId'=>$payment->student_id])
                ->with('error','Your payment was captured, but the fee balance changed before settlement. The amount is safely flagged for office reconciliation. Please do not pay again; contact the accounts office.');
        }
        app(FeeReceiptMailer::class)->send($paymentId);
        return redirect()->route('tagore.fees.student',['studentId'=>$payment->student_id])->with('success','Payment received successfully. Receipt '.$payment->receipt_no.'.');
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')
            ->where('ur.user_id',$userId)->where('ur.status','active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')->unique()->values();
    }

    public function webhook(Request $request,string $gateway,OnlinePaymentService $service)
    {
        abort_unless($gateway==='razorpay',404);
        $signature=(string)$request->header('X-Razorpay-Signature');
        abort_unless($signature!=='',400);
        $service->webhook($signature,$request->getContent(),$request->json()->all(),(string)$request->header('X-Razorpay-Event-Id'));
        return response()->json(['ok'=>true]);
    }
}
