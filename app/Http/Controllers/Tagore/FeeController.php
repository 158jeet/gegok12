<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Services\Tagore\FeeReceiptMailer;
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
            ->first(['u.id', 'u.name', 'u.school_id', 'i.id as institution_id', 'i.display_name as institution']);
        abort_unless($student, 404);
        $this->authorizeStudent($userId, $studentId, $student->institution_id, $roles);

        $fees = DB::table('tagore_fee_obligations')->where('student_id', $studentId)->orderByDesc('due_date')->orderByDesc('id')->get();
        $items = DB::table('tagore_fee_obligation_items as oi')->join('tagore_fee_obligations as o', 'o.id', '=', 'oi.fee_obligation_id')->where('o.student_id', $studentId)->orderBy('o.id')->orderBy('oi.id')->get(['oi.*', 'o.due_date']);
        $payments = $roles->contains('PARENT') || $roles->contains('STUDENT') ? collect() : DB::table('tagore_payments')->where('student_id', $studentId)->where('status', 'success')->orderByDesc('paid_at')->get();
        $concessions = DB::table('tagore_fee_concessions')->where('student_id', $studentId)->where('status', 'approved')->orderByDesc('id')->get();
        $summary = [
            'gross' => round((float) $fees->sum('gross_amount'), 2),
            'discount' => round((float) $fees->sum('discount_amount'), 2),
            'concession' => round((float) $fees->sum('concession_amount'), 2),
            'payable' => round((float) $fees->sum('net_amount'), 2),
            'paid' => round((float) $fees->sum('paid_amount'), 2),
            'outstanding' => round((float) $fees->sum('outstanding_amount'), 2),
        ];

        // Teachers/coordinators must not learn about siblings outside their assigned class/grade.
        if ($roles->intersect(['TEACHER', 'COORDINATOR'])->isNotEmpty()
            && $roles->intersect(['OWNER', 'PRINCIPAL', 'ACCOUNTS', 'CASHIER'])->isEmpty()) {
            $familyBalances = collect();
        } else {
            // Parents see only their own linked family; finance/admin users stay within the authorised institution.
            $familyQuery = DB::table('tagore_parent_students as focus')
                ->join('users as parent', 'parent.id', '=', 'focus.parent_user_id')
                ->join('tagore_parent_students as sibling_link', function ($join) {
                    $join->on('sibling_link.parent_user_id', '=', 'focus.parent_user_id')
                        ->where('sibling_link.status', '=', 'active');
                })
                ->join('users as sibling', 'sibling.id', '=', 'sibling_link.student_id')
                ->leftJoin('tagore_institutions as sibling_institution', 'sibling_institution.school_id', '=', 'sibling.school_id')
                ->leftJoin('tagore_fee_obligations as sibling_fee', function ($join) {
                    $join->on('sibling_fee.student_id', '=', 'sibling.id')
                        ->on('sibling_fee.institution_id', '=', 'sibling_institution.id');
                })
                ->where('focus.student_id', $studentId)
                ->where('focus.status', 'active')
                ->where('sibling_link.status', 'active')
                ->whereNull('parent.deleted_at')
                ->whereNull('sibling.deleted_at');
            if ($roles->contains('PARENT')) {
                $familyQuery->where('focus.parent_user_id', $userId);
            } else {
                $familyQuery->where('sibling_institution.id', $student->institution_id);
            }
            $familyBalances = $familyQuery
                ->groupBy('focus.parent_user_id', 'parent.name')
                ->selectRaw('focus.parent_user_id, parent.name as parent_name, COUNT(DISTINCT sibling_link.student_id) as children_count, COALESCE(SUM(sibling_fee.net_amount),0) as payable, COALESCE(SUM(sibling_fee.paid_amount),0) as paid, COALESCE(SUM(sibling_fee.outstanding_amount),0) as outstanding')
                ->orderBy('parent.name')
                ->get();
        }

        return view('tagore.fees.student', compact('student', 'fees', 'items', 'payments', 'concessions', 'summary', 'roles', 'familyBalances'));
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
        $scopedStudentIds = null;
        if ($roles->intersect(['TEACHER', 'COORDINATOR'])->isNotEmpty()
            && $roles->intersect(['OWNER', 'PRINCIPAL', 'ACCOUNTS', 'CASHIER'])->isEmpty()) {
            $scopedStudentIds = $this->schoolScopedStudentIds($userId, $roles, $ids->map(fn ($id) => (int) $id)->all());
        }

        $summaryQuery = DB::table('tagore_fee_obligations')->whereIn('institution_id', $ids);
        if ($scopedStudentIds !== null) $summaryQuery->whereIn('student_id', $scopedStudentIds);
        $summary = $summaryQuery
            ->selectRaw('COALESCE(SUM(gross_amount),0) gross, COALESCE(SUM(discount_amount),0) discount, COALESCE(SUM(concession_amount),0) concession, COALESCE(SUM(net_amount),0) payable, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(outstanding_amount),0) outstanding')->first();

        $recentQuery = DB::table('tagore_payments as p')
            ->join('users as u', 'u.id', '=', 'p.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'p.institution_id')
            ->leftJoin('tagore_payment_reconciliations as r', 'r.payment_id', '=', 'p.id')
            ->whereIn('p.institution_id', $ids);
                if ($roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty()) {
            $recentQuery->whereIn('p.status', ['success', 'review_required', 'refunded']);
        } else {
            $recentQuery->where('p.status', 'success');
        }
        if ($scopedStudentIds !== null) $recentQuery->whereIn('p.student_id', $scopedStudentIds);
        $recent = $recentQuery
            ->orderByDesc('p.paid_at')->orderByDesc('p.id')->limit(100)
            ->get(['p.id', 'p.receipt_no', 'p.amount', 'p.status as payment_status', 'p.payment_mode', 'p.gateway', 'p.gateway_order_id', 'p.gateway_payment_id', 'p.reference_number', 'p.paid_at', 'u.name as student', 'i.display_name as institution', 'r.status as reconciliation_status', 'r.settlement_id', 'r.settlement_amount', 'r.settlement_date', 'r.refund_reference', 'r.refund_date']);

        $pendingReconciliation = $scopedStudentIds === null
            && $roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty()
            ? (clone $recent)->filter(fn ($payment) => ($payment->gateway === 'razorpay' || $payment->gateway === 'online')
                && (($payment->payment_status ?? '') === 'review_required' || ($payment->reconciliation_status ?? 'pending') === 'pending'))->count()
            : 0;
        // Institution- and class-scoped students with outstanding balances for the collection workspace.
        $collectibleStudentsQuery = DB::table('tagore_fee_obligations as o')
            ->join('users as u', 'u.id', '=', 'o.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'o.institution_id')
            ->whereIn('o.institution_id', $ids)
            ->where('o.outstanding_amount', '>', 0)
            ->whereNull('u.deleted_at');
        if ($scopedStudentIds !== null) {
            $collectibleStudentsQuery->whereIn('o.student_id', $scopedStudentIds);
        }
        $collectibleStudents = $collectibleStudentsQuery
            ->groupBy('u.id', 'u.name', 'i.id', 'i.display_name')
            ->selectRaw('u.id as student_id, u.name as student_name, i.id as institution_id, i.display_name as institution, SUM(o.outstanding_amount) as outstanding')
            ->orderByDesc('outstanding')
            ->orderBy('u.name')
            ->limit(500)
            ->get();
        // Consolidate only the students the current finance/academic role may inspect.
        $familyQuery = DB::table('tagore_parent_students as ps')
            ->join('users as parent', 'parent.id', '=', 'ps.parent_user_id')
            ->join('users as child', 'child.id', '=', 'ps.student_id')
            ->join('tagore_institutions as child_institution', 'child_institution.school_id', '=', 'child.school_id')
            ->leftJoin('tagore_fee_obligations as o', function ($join) use ($ids) {
                $join->on('o.student_id', '=', 'ps.student_id')->whereIn('o.institution_id', $ids);
            })
            ->where('ps.status', 'active')
            ->whereIn('child_institution.id', $ids)
            ->whereNull('parent.deleted_at')
            ->whereNull('child.deleted_at');
        if ($scopedStudentIds !== null) $familyQuery->whereIn('ps.student_id', $scopedStudentIds);
        $familyBalances = $familyQuery
            ->groupBy('ps.parent_user_id', 'parent.name', 'parent.mobile_no')
            ->selectRaw('ps.parent_user_id, parent.name as parent_name, parent.mobile_no, COUNT(DISTINCT ps.student_id) as children_count, COALESCE(SUM(o.net_amount),0) as payable, COALESCE(SUM(o.paid_amount),0) as paid, COALESCE(SUM(o.outstanding_amount),0) as outstanding')
            ->orderByDesc('outstanding')
            ->orderBy('parent.name')
            ->limit(500)
            ->get();

        return view('tagore.fees.accounts', compact('institutions', 'summary', 'recent', 'pendingReconciliation', 'roles', 'familyBalances', 'collectibleStudents'));
    }

    public function recordOfflinePayment(Request $request, int $studentId, FeeService $feeService)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        $this->authorizePaymentRecording($roles);
        $student = DB::table('users as u')->join('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')->where('u.id', $studentId)->first(['u.id', 'i.id as institution_id']);
        abort_unless($student, 404);
        $this->authorizeInstitution($userId, $student->institution_id, $roles);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,upi,card,cheque,bank_transfer,other'],
            'reference_number' => ['nullable', 'string', 'max:160'],
        ]);
        $paymentId = $feeService->recordOfflinePayment($studentId, $student->institution_id, (float) $data['amount'], null, $userId, $data['reference_number'] ?? null, [], $data['payment_mode']);
        app(FeeReceiptMailer::class)->send($paymentId);
        return back()->with('success', 'Payment recorded successfully.');
    }

    public function reconcile(Request $request, int $paymentId)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty(), 403);

        $payment = DB::table('tagore_payments')->where('id', $paymentId)->whereIn('status', ['success', 'review_required'])->first();
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

    public function recordVerifiedRefund(Request $request, int $paymentId)
    {
        $userId = (int) $request->user()->id;
        $roles = $this->roles($userId);
        abort_unless($roles->intersect(['OWNER', 'ACCOUNTS'])->isNotEmpty(), 403);

        $data = $request->validate([
            'refund_reference' => ['required', 'string', 'max:160'],
            'refund_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $userId, $roles, $paymentId, $data) {
            $payment = DB::table('tagore_payments')->where('id', $paymentId)->lockForUpdate()->first();
            abort_unless($payment && $payment->status === 'review_required', 404);
            abort_unless(in_array($payment->gateway, ['razorpay', 'online'], true), 422, 'Only captured online payments can be marked refunded here.');

            if ($payment->family_payment_group_id) {
                $group = DB::table('tagore_family_payment_groups')->where('id', $payment->family_payment_group_id)->lockForUpdate()->first();
                abort_unless($group && $group->status === 'review_required', 409, 'The family payment group is no longer awaiting review.');

                $payments = DB::table('tagore_payments')->where('family_payment_group_id', $group->id)->lockForUpdate()->get();
                $orders = DB::table('tagore_payment_orders')->where('family_payment_group_id', $group->id)->lockForUpdate()->get();
                abort_unless($payments->isNotEmpty() && $payments->every(fn ($row) => $row->status === 'review_required'), 409, 'Every child payment in this family checkout must still be awaiting review.');
                abort_unless($orders->isNotEmpty() && $orders->count() === $payments->count() && $orders->every(fn ($row) => $row->status === 'review_required'), 409, 'Every child order in this family checkout must still be awaiting review.');

                foreach ($payments as $row) {
                    $this->authorizeInstitution($userId, (int) $row->institution_id, $roles);
                }
                $paymentIds = $payments->pluck('id')->map(fn ($id) => (int) $id)->all();

                DB::table('tagore_family_payment_groups')->where('id', $group->id)->update([
                    'status' => 'refunded',
                    'notes' => 'Gateway refund recorded by authorised accounts operator: '.$data['refund_reference'].'. '.$data['notes'],
                    'updated_at' => now(),
                ]);
                DB::table('tagore_payment_orders')->where('family_payment_group_id', $group->id)->update([
                    'status' => 'refunded',
                    'updated_at' => now(),
                ]);
            } else {
                $this->authorizeInstitution($userId, (int) $payment->institution_id, $roles);
                $order = DB::table('tagore_payment_orders')->where('id', $payment->payment_order_id)->lockForUpdate()->first();
                abort_unless($order && $order->status === 'review_required', 409, 'The payment order is no longer awaiting refund review.');
                $paymentIds = [(int) $payment->id];
                DB::table('tagore_payment_orders')->where('id', $payment->payment_order_id)->where('status', 'review_required')->update([
                    'status' => 'refunded',
                    'updated_at' => now(),
                ]);
            }

            foreach ($paymentIds as $id) {
                $row = DB::table('tagore_payments')->where('id', $id)->first();
                DB::table('tagore_payments')->where('id', $id)->update([
                    'status' => 'refunded',
                    'notes' => trim((string) ($row->notes ?? '').' Gateway refund verified: '.$data['refund_reference'].'. '.$data['notes']),
                    'updated_at' => now(),
                ]);
                $existingReconciliation = DB::table('tagore_payment_reconciliations')->where('payment_id', $id)->first();
                DB::table('tagore_payment_reconciliations')->updateOrInsert(
                    ['payment_id' => $id],
                    [
                        'gateway' => $row->gateway ?: 'razorpay',
                        'settlement_id' => $existingReconciliation->settlement_id ?? null,
                        'settlement_amount' => $existingReconciliation->settlement_amount ?? null,
                        'settlement_date' => $existingReconciliation->settlement_date ?? null,
                        'status' => 'refunded',
                        'refund_reference' => $data['refund_reference'],
                        'refund_date' => Carbon::parse($data['refund_date'])->toDateString(),
                        'refund_notes' => $data['notes'],
                        'reconciled_by' => $userId,
                        'reconciled_at' => now(),
                        'notes' => $existingReconciliation->notes ?? null,
                        'created_at' => $existingReconciliation->created_at ?? now(),
                        'updated_at' => now(),
                    ],
                );                DB::table('tagore_audit_events')->insert([
                    'user_id' => $userId,
                    'institution_id' => $row->institution_id,
                    'action' => 'PAYMENT_REFUND_RECORDED',
                    'entity_type' => 'tagore_payments',
                    'entity_id' => $id,
                    'old_values_json' => json_encode(['status' => 'review_required']),
                    'new_values_json' => json_encode(['status' => 'refunded', 'refund_reference' => $data['refund_reference'], 'refund_date' => $data['refund_date']]),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Verified gateway refund recorded. The affected fee balances are available for a fresh checkout.');
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
        // Coordinators may view the Accounts workspace but cannot record financial transactions.
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS', 'CASHIER'])->isNotEmpty(), 403);
    }

    private function authorizePaymentRecording($roles): void
    {
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'ACCOUNTS', 'CASHIER'])->isNotEmpty(), 403);
    }

    private function scopeInstitutions($query, int $userId, $roles): void
    {
        if (!$roles->contains('OWNER')) {
            $query->whereIn('i.id', DB::table('tagore_user_roles')->where('user_id', $userId)->where('status', 'active')->whereNotNull('institution_id')
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->pluck('institution_id'));
        }
    }

    private function authorizeInstitution(int $userId, int $institutionId, $roles): void
    {
        if (!$roles->contains('OWNER')) {
            abort_unless(DB::table('tagore_user_roles')->where('user_id', $userId)->where('institution_id', $institutionId)->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->exists(), 403);
        }
    }

    private function roles(int $userId)
    {
        return DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->where(fn ($query) => $query->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')->unique()->values();
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
        $staff = $roles->intersect(['OWNER', 'PRINCIPAL', 'COORDINATOR', 'TEACHER', 'ACCOUNTS', 'CASHIER'])->isNotEmpty();
        abort_unless($staff, 403);
        $this->authorizeInstitution($userId, (int) $institutionId, $roles);

        if ($roles->intersect(['OWNER', 'PRINCIPAL', 'ACCOUNTS', 'CASHIER'])->isNotEmpty()) return;
        abort_unless($this->schoolUserCanAccessStudent($userId, $studentId, $roles), 403);
    }

    private function schoolUserCanAccessStudent(int $userId, int $studentId, $roles): bool
    {
        $student = DB::table('users')->where('id', $studentId)->whereNull('deleted_at')->first(['school_id']);
        if (!$student) return false;

        $academic = DB::table('student_academics as sa')
            ->where('sa.user_id', $studentId)->where('sa.school_id', $student->school_id)
            ->whereNull('sa.deleted_at')->orderByDesc('sa.id')->first(['sa.standardLink_id', 'sa.academic_year_id']);
        if (!$academic) return false;

        if ($roles->contains('TEACHER') && DB::table('class_teacher_links')
            ->where('teacher_id', $userId)->where('school_id', $student->school_id)
            ->where('standardLink_id', $academic->standardLink_id)
            ->where('academic_year_id', $academic->academic_year_id)
            ->where('status', 1)->whereNull('deleted_at')->exists()) {
            return true;
        }

        if ($roles->contains('COORDINATOR')) {
            $feeViewId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
            if (!$feeViewId) return false;
            return DB::table('tagore_user_scopes')
                ->where('user_id', $userId)->where('scope_type', 'standard_link')
                ->where('scope_id', $academic->standardLink_id)->where('permission_id', $feeViewId)->exists();
        }

        return false;
    }

    private function schoolScopedStudentIds(int $userId, $roles, array $institutionIds): array
    {
        if ($institutionIds === []) return [];

        $feeViewId = DB::table('tagore_permissions')->where('code', 'fee.view')->value('id');
        $query = DB::table('users as student')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'student.school_id')
            ->whereIn('i.id', $institutionIds)
            ->where('student.usergroup_id', 6)
            ->whereNull('student.deleted_at')
            ->whereExists(function ($academic) use ($userId, $roles, $feeViewId) {
                $academic->selectRaw('1')->from('student_academics as sa')
                    ->whereColumn('sa.user_id', 'student.id')
                    ->whereColumn('sa.school_id', 'student.school_id')
                    ->whereNull('sa.deleted_at')
                    ->whereRaw('sa.id = (select max(sa_latest.id) from student_academics as sa_latest where sa_latest.user_id = student.id and sa_latest.school_id = student.school_id and sa_latest.deleted_at is null)')
                    ->where(function ($scope) use ($userId, $roles, $feeViewId) {
                        if ($roles->contains('TEACHER')) {
                            $scope->whereExists(function ($assignment) use ($userId) {
                                $assignment->selectRaw('1')->from('class_teacher_links as ctl')
                                    ->whereColumn('ctl.standardLink_id', 'sa.standardLink_id')
                                    ->whereColumn('ctl.academic_year_id', 'sa.academic_year_id')
                                    ->whereColumn('ctl.school_id', 'student.school_id')
                                    ->where('ctl.teacher_id', $userId)->where('ctl.status', 1)
                                    ->whereNull('ctl.deleted_at');
                            });
                        }
                        if ($roles->contains('COORDINATOR')) {
                            if ($feeViewId) {
                                $coordinatorScope = fn ($sub) => $sub->selectRaw('1')->from('tagore_user_scopes as us')
                                    ->whereColumn('us.scope_id', 'sa.standardLink_id')
                                    ->where('us.user_id', $userId)->where('us.scope_type', 'standard_link')
                                    ->where('us.permission_id', $feeViewId);
                                if ($roles->contains('TEACHER')) {
                                    $scope->orWhereExists($coordinatorScope);
                                } else {
                                    $scope->whereExists($coordinatorScope);
                                }
                            } elseif (!$roles->contains('TEACHER')) {
                                $scope->whereRaw('1 = 0');
                            }
                        }
                    });
            });

        return $query->pluck('student.id')->map(fn ($id) => (int) $id)->all();
    }
}
