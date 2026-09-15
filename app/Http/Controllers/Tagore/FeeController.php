<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeService;
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
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'ACCOUNTS'])->isNotEmpty(), 403);

        $institutions = DB::table('tagore_institutions as i')->join('schools as s', 's.id', '=', 'i.school_id')->where('i.status', 'active');
        if (!$roles->contains('OWNER')) {
            $institutions->whereIn('i.id', DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')->pluck('institution_id'));
        }
        $institutions = $institutions->orderBy('i.display_name')->get(['i.id', 'i.display_name']);
        $ids = $institutions->pluck('id');
        $summary = DB::table('tagore_fee_obligations')->whereIn('institution_id', $ids)->selectRaw('COALESCE(SUM(gross_amount),0) gross, COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(concession_amount),0) concession, COALESCE(SUM(net_amount),0) payable, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(outstanding_amount),0) outstanding')->first();
        $recent = DB::table('tagore_payments as p')->join('users as u', 'u.id', '=', 'p.student_id')->join('tagore_institutions as i', 'i.id', '=', 'p.institution_id')->whereIn('p.institution_id', $ids)->where('p.status', 'success')->orderByDesc('p.paid_at')->limit(50)->get(['p.id', 'p.receipt_no', 'p.amount', 'p.payment_mode', 'p.reference_number', 'p.paid_at', 'u.name as student', 'i.display_name as institution']);

        return view('tagore.fees.accounts', compact('institutions', 'summary', 'recent', 'roles'));
    }

    public function recordOfflinePayment(Request $request, int $studentId, FeeService $feeService)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'ACCOUNTS'])->isNotEmpty(), 403);
        $student = DB::table('users as u')->join('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')->where('u.id', $studentId)->first(['u.id', 'i.id as institution_id']);
        abort_unless($student, 404);
        if (!$roles->contains('OWNER') && !DB::table('tagore_user_roles')->where('user_id', $userId)->where('institution_id', $student->institution_id)->where('status', 'active')->exists()) abort(403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,cheque,bank_transfer,other'],
            'reference_number' => ['nullable', 'string', 'max:160'],
        ]);
        $feeService->recordOfflinePayment($studentId, $student->institution_id, (float) $data['amount'], null, $userId, $data['reference_number'] ?? null, [], $data['payment_mode']);
        return back()->with('success', 'Payment recorded successfully.');
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
        if (!$roles->contains('OWNER')) {
            abort_unless(DB::table('tagore_user_roles')->where('user_id', $userId)->where('institution_id', $institutionId)->where('status', 'active')->exists(), 403);
        }
    }
}
