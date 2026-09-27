<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FeeController extends Controller
{
    public function student(Request $request, int $studentId): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $student = DB::table('users as u')
            ->leftJoin('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')
            ->where('u.id', $studentId)
            ->first(['u.id', 'u.name', 'i.id as institution_id', 'i.display_name as institution']);
        abort_unless($student, 404);
        $this->authorizeStudent($userId, $studentId, $student->institution_id, $roles);

        $fees = DB::table('tagore_fee_obligations')->where('student_id', $studentId)->orderByDesc('due_date')->orderByDesc('id')->get();
        $items = DB::table('tagore_fee_obligation_items as oi')->join('tagore_fee_obligations as o', 'o.id', '=', 'oi.fee_obligation_id')->where('o.student_id', $studentId)->orderBy('o.id')->orderBy('oi.id')->get(['oi.*', 'o.due_date']);
        $payments = DB::table('tagore_payments')->where('student_id', $studentId)->where('status', 'success')->orderByDesc('paid_at')->get();
        $concessions = DB::table('tagore_fee_concessions')->where('student_id', $studentId)->where('status', 'approved')->orderByDesc('id')->get();
        $summary = [
            'gross' => round((float) $fees->sum('gross_amount'), 2),
            'discount' => round((float) $fees->sum('discount_amount'), 2),
            'concession' => round((float) $fees->sum('concession_amount'), 2),
            'payable' => round((float) $fees->sum('net_amount'), 2),
            'paid' => round((float) $fees->sum('paid_amount'), 2),
            'outstanding' => round((float) $fees->sum('outstanding_amount'), 2),
        ];

        return view('tagore.fees.student', compact('student', 'fees', 'items', 'payments', 'concessions', 'summary', 'roles'));
    }

    public function accounts(Request $request): View
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $this->authorizeAccounts($roles);

        $institutions = DB::table('tagore_institutions as i')
            ->join('schools as s', 's.id', '=', 'i.school_id')
            ->where('i.status', 'active');
        $this->scopeInstitutions($institutions, $userId, $roles);
        $institutions = $institutions->orderBy('i.display_name')->get(['i.id', 'i.display_name']);
        $ids = $institutions->pluck('id');

        $summary = DB::table('tagore_fee_obligations')->whereIn('institution_id', $ids)
            ->selectRaw('COALESCE(SUM(gross_amount),0) gross, COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(concession_amount),0) concession, COALESCE(SUM(net_amount),0) payable, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(outstanding_amount),0) outstanding')->first();

        $recent = DB::table('tagore_payments as p')
            ->join('users as u', 'u.id', '=', 'p.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'p.institution_id')
            ->leftJoin('tagore_payment_reconciliations as r', 'r.payment_id', '=', 'p.id')
            ->whereIn('p.institution_id', $ids)
            ->where('p.status', 'success')
            ->orderByDesc('p.paid_at')->orderByDesc('p.id')->limit(100)
            ->get(['p.id', 'p.receipt_no', 'p.amount', 'p.payment_mode', 'p.gateway', 'p.gateway_order_id', 'p.gateway_payment_id', 'p.reference_number', 'p.paid_at', 'u.name as student', 'i.display_name as institution', 'r.status as reconciliation_status', 'r.settlement_id', 'r.settlement_amount', 'r.settlement_date']);

        $pendingReconciliation = (clone $recent)->filter(fn ($payment) => ($payment->gateway === 'razorpay' || $payment->gateway === 'online') && ($payment->reconciliation_status ?? 'pending') === 'pending')->count();

        return view('tagore.fees.accounts', compact('institutions', 'summary', 'recent', 'pendingReconciliation', 'roles'));
    }

    public function recordOfflinePayment(Request $request, int $studentId, FeeService $feeService)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $this->authorizeAccounts($roles);
        $student = DB::table('users as u')->join('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')->where('u.id', $studentId)->first(['u.id', 'i.id as institution_id']);
        abort_unless($student, 404);
        $this->authorizeInstitution($userId, $student->institution_id, $roles);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,cheque,bank_transfer,other'],
            'reference_number' => ['nullable', 'string', 'max:160'],
        ]);
        $feeService->recordOfflinePayment($studentId, $student->institution_id, (float) $data['amount'], null, $userId, $data['reference_number'] ?? null, [], $data['payment_mode']);
        return back()->with('success', 'Payment recorded successfully.');
    }

    public function reconcile(Request $request, int $paymentId)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty(), 403);

        $payment = DB::table('tagore_payments')->where('id', $paymentId)->where('status', 'success')->first();
        abort_unless($payment, 404);
        $this->authorizeInstitution($userId, (int) $payment->institution_id, $roles);
        abort_unless(in_array($payment->gateway, ['razorpay', 'online'], true), 422, 'Only online gateway payments require reconciliation.');

        $data = $request->validate([
            'settlement_id' => ['nullable', 'string', 'max:160'],
            'settlement_amount' => ['required', 'numeric', 'min:0'],
            'settlement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $status = abs((float) $data['settlement_amount'] - (float) $payment->amount) < 0.01 ? 'matched' : 'mismatch';

        DB::table('tagore_payment_reconciliations')->updateOrInsert(
            ['payment_id' => $paymentId],
            [
                'gateway' => $payment->gateway ?: 'razorpay',
                'settlement_id' => $data['settlement_id'] ?? null,
                'settlement_amount' => round((float) $data['settlement_amount'], 2),
                'settlement_date' => Carbon::parse($data['settlement_date'])->toDateString(),
                'status' => $status,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
                'notes' => $data['notes'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('tagore_audit_events')->insert([
            'user_id' => $userId,
            'institution_id' => $payment->institution_id,
            'action' => 'PAYMENT_RECONCILED',
            'entity_type' => 'tagore_payment_reconciliations',
            'entity_id' => $paymentId,
            'old_values_json' => null,
            'new_values_json' => json_encode(['status' => $status, 'settlement_amount' => (float) $data['settlement_amount'], 'settlement_id' => $data['settlement_id'] ?? null]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', $status === 'matched' ? 'Payment reconciled successfully.' : 'Reconciliation saved as a mismatch for review.');
    }

    public function receipt(Request $request, int $paymentId)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $payment = DB::table('tagore_payments as p')
            ->join('users as u', 'u.id', '=', 'p.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'p.institution_id')
            ->where('p.id', $paymentId)->where('p.status', 'success')
            ->first(['p.*', 'u.name as student', 'i.display_name as institution']);
        abort_unless($payment, 404);
        $this->authorizeStudent($userId, (int) $payment->student_id, (int) $payment->institution_id, $roles);

        $allocations = DB::table('tagore_payment_allocations as a')
            ->leftJoin('tagore_fee_obligations as o', 'o.id', '=', 'a.fee_obligation_id')
            ->where('a.payment_id', $paymentId)
            ->orderBy('a.id')
            ->get(['a.amount', 'a.fee_obligation_id', 'a.fee_installment_id', 'o.due_date']);

        $pdf = Pdf::loadView('tagore.fees.receipt', compact('payment', 'allocations'));
        return $pdf->download(($payment->receipt_no ?: 'tagore-receipt-'.$paymentId).'.pdf');
    }

    private function authorizeAccounts($roles): void
    {
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'ACCOUNTS'])->isNotEmpty(), 403);
    }

    private function scopeInstitutions($query, int $userId, $roles): void
    {
        if (!$roles->contains('OWNER')) {
            $query->whereIn('i.id', DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')->pluck('institution_id'));
        }
    }

    private function authorizeInstitution(int $userId, int $institutionId, $roles): void
    {
        if (!$roles->contains('OWNER')) {
            abort_unless(DB::table('tagore_user_roles')->where('user_id', $userId)->where('institution_id', $institutionId)->where('status', 'active')->exists(), 403);
        }
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $userId)->where('ur.status', 'active')->pluck('r.code')->unique()->values();
    }

    private function authorizeStudent(int $userId, int $studentId, ?int $institutionId, $roles): void
    {
        if ($roles->contains('PARENT')) {
            abort_unless(DB::table('tagore_parent_students')->where('parent_user_id', $userId)->where('student_id', $studentId)->where('status', 'active')->exists(), 403);
            return;
        }
        if ($roles->contains('STUDENT')) {
            abort_unless($studentId === $userId, 403);
            return;
        }
        $staff = $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS'])->isNotEmpty();
        abort_unless($staff, 403);
        $this->authorizeInstitution($userId, (int) $institutionId, $roles);
    }
}
