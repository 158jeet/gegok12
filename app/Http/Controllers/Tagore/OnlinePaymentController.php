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
        $userId=(int)$request->user()->id;
        abort_unless(DB::table('tagore_parent_students')->where('parent_user_id',$userId)->where('student_id',$studentId)->where('status','active')->exists(),403);
        $data=$request->validate(['amount'=>['required','numeric','min:1']]);
        abort_unless(config('services.razorpay.key')&&config('services.razorpay.secret'),503,'Online payments are not configured.');
        $order=$service->createOrder($studentId,$userId,(float)$data['amount']);
        return view('tagore.payments.checkout',compact('order'));
    }

    public function confirm(Request $request,OnlinePaymentService $service)
    {
        $data=$request->validate(['razorpay_order_id'=>['required','string'],'razorpay_payment_id'=>['required','string'],'razorpay_signature'=>['required','string']]);
        $order=DB::table('tagore_payment_orders')->where('gateway_order_id',$data['razorpay_order_id'])->first(['id','parent_user_id','student_id','status']);
        abort_unless($order,404);
        $userId=(int)$request->user()->id;
        $roles=$this->roles($userId);
        $authorizedParent=(int)$order->parent_user_id===$userId && DB::table('tagore_parent_students')->where('parent_user_id',$userId)->where('student_id',$order->student_id)->where('status','active')->exists();
        $authorizedStaff=$roles->intersect(['OWNER','PRINCIPAL','COORDINATOR','ACCOUNTS'])->isNotEmpty();
        abort_unless($authorizedParent || $authorizedStaff,403);
        $paymentId=$service->confirm($data);
        $payment=DB::table('tagore_payments')->where('id',$paymentId)->first();
        return redirect()->route('tagore.fees.student',['studentId'=>$payment->student_id])->with('success','Payment received successfully. Receipt '.$payment->receipt_no.'.');
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r','r.id','=','ur.role_id')->where('ur.user_id',$userId)->where('ur.status','active')->pluck('r.code')->unique()->values();
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
