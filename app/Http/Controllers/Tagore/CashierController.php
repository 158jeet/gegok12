<?php

namespace App\Http\Controllers\Tagore;

use App\Http\Controllers\Controller;
use App\Events\SinglePushEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashierController extends Controller
{
    public function index(Request $request): View
    {
        [$roles, $institutionIds] = $this->scope($request);
        $filters = $this->filters($request);
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $collectorUserId = $this->cashierOnly($roles) ? (int) $request->user()->id : null;
        $payments = DB::table('tagore_payments as p')
            ->whereIn('p.institution_id', $institutionIds)
            ->where('p.status', 'success');
        if ($collectorUserId) $this->restrictToCollector($payments, $collectorUserId);
        $today = (clone $payments)->where('p.paid_at', '>=', $todayStart)->where('p.paid_at', '<', $todayStart->copy()->addDay());
        $month = (clone $payments)->where('p.paid_at', '>=', $monthStart)->where('p.paid_at', '<=', now());

        $summary = [
            'today_total' => round((float) (clone $today)->sum('p.amount'), 2),
            'today_count' => (clone $today)->count(),
            'month_total' => round((float) (clone $month)->sum('p.amount'), 2),
            'month_count' => (clone $month)->count(),
            'outstanding' => round((float) DB::table('tagore_fee_obligations')->whereIn('institution_id', $institutionIds)->sum('outstanding_amount'), 2),
            'students_with_dues' => DB::table('tagore_fee_obligations')->whereIn('institution_id', $institutionIds)->where('outstanding_amount', '>', 0)->distinct()->count('student_id'),
        ];

        $modeTotals = (clone $today)->select('p.payment_mode')
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(p.amount),0) as amount')
            ->groupBy('p.payment_mode')->orderByDesc('amount')->get();

        $transactions = $this->filteredPayments($institutionIds, $filters, $collectorUserId)
            ->orderByDesc('p.paid_at')->orderByDesc('p.id')->paginate(25)->withQueryString();

        $dueQuery = DB::table('tagore_fee_obligations as o')
            ->join('users as u', 'u.id', '=', 'o.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'o.institution_id')
            ->whereIn('o.institution_id', $institutionIds)
            ->where('o.outstanding_amount', '>', 0)->whereNull('u.deleted_at');
        if ($filters['q'] !== '') {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $dueQuery->where(function ($query) use ($needle) {
                $query->where('u.name', 'like', $needle)
                    ->orWhere('u.registration_number', 'like', $needle)
                    ->orWhere('u.id', 'like', $needle);
            });
        }
        $dueStudents = $dueQuery->groupBy('u.id', 'u.name', 'u.registration_number', 'i.id', 'i.display_name')
            ->selectRaw('u.id as student_id, u.name as student_name, u.registration_number, i.id as institution_id, i.display_name as institution, SUM(o.outstanding_amount) as outstanding')
            ->orderByDesc('outstanding')->orderBy('u.name')->limit(40)->get();

        $institutions = DB::table('tagore_institutions')->whereIn('id', $institutionIds)
            ->orderBy('display_name')->get(['id', 'display_name']);

        return view('tagore.cashier.index', compact('roles', 'filters', 'summary', 'modeTotals', 'transactions', 'dueStudents', 'institutions'));
    }

    public function sendReminder(Request $request, int $studentId)
    {
        [$roles, $institutionIds] = $this->scope($request);
        if ($this->cashierOnly($roles)) $this->assertCashierPermission($request, 'fee.remind');

        $student = DB::table('users as u')
            ->join('tagore_institutions as i', 'i.school_id', '=', 'u.school_id')
            ->where('u.id', $studentId)->where('u.usergroup_id', 6)
            ->whereNull('u.deleted_at')->whereIn('i.id', $institutionIds)
            ->select('u.id', 'u.name', 'u.school_id', 'i.id as institution_id', 'i.display_name as institution')
            ->first();
        abort_unless($student, 404);

        $outstanding = round((float) DB::table('tagore_fee_obligations')
            ->where('student_id', $studentId)->where('institution_id', $student->institution_id)
            ->where('outstanding_amount', '>', 0)->sum('outstanding_amount'), 2);
        abort_if($outstanding <= 0, 422, 'This student has no outstanding fee balance.');

        $parentIds = DB::table('tagore_parent_students')
            ->where('student_id', $studentId)->where('status', 'active')
            ->pluck('parent_user_id')->unique()->values();
        $parents = DB::table('users')->whereIn('id', $parentIds)
            ->where('usergroup_id', 7)->whereNull('deleted_at')->get(['id']);

        if ($parents->isEmpty()) {
            return back()->with('warning', 'No active parent account is linked to this student. Verify the family link before sending a reminder.');
        }

        $message = 'Fee reminder: ₹' . number_format($outstanding, 2)
            . ' is currently outstanding for ' . $student->name . ' at ' . $student->institution
            . '. Please open the Tagore Parent Portal to review the fee details and payment options.';

        foreach ($parents as $parent) {
            event(new SinglePushEvent([
                'school_id' => $student->school_id,
                'user_id' => $parent->id,
                'message' => $message,
                'type' => 'private message',
            ]));
        }

        DB::table('tagore_audit_events')->insert([
            'user_id' => $request->user()->id,
            'institution_id' => $student->institution_id,
            'action' => 'FEE_DUE_REMINDER_SENT',
            'entity_type' => 'users',
            'entity_id' => $studentId,
            'old_values_json' => null,
            'new_values_json' => json_encode([
                'outstanding_amount' => $outstanding,
                'parent_user_ids' => $parents->pluck('id')->all(),
                'channel' => 'parent_app_push',
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', $parents->count() . ' fee reminder(s) sent to linked parent app account(s).');
    }

    public function export(Request $request): StreamedResponse
    {
        [$roles, $institutionIds] = $this->scope($request);
        if ($this->cashierOnly($roles)) $this->assertCashierPermission($request, 'payment.export');

        $filters = $this->filters($request);
        $collectorUserId = $this->cashierOnly($roles) ? (int) $request->user()->id : null;
        $rows = $this->filteredPayments($institutionIds, $filters, $collectorUserId)
            ->orderBy('p.paid_at')->orderBy('p.id')->limit(10000)->get();

        $filename = 'tagore-collection-' . $filters['from'] . '-to-' . $filters['to'] . '.csv';
        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Receipt', 'Student', 'Registration No', 'Institution', 'Payment Date', 'Payment Mode', 'Reference', 'Amount (INR)', 'Status']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->receipt_no, $row->student, $row->registration_number, $row->institution,
                    $row->paid_at, $row->payment_mode, $row->reference_number,
                    number_format((float) $row->amount, 2, '.', ''), $row->status,
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredPayments(array $institutionIds, array $filters, ?int $collectorUserId = null)
    {
        $query = DB::table('tagore_payments as p')
            ->join('users as u', 'u.id', '=', 'p.student_id')
            ->join('tagore_institutions as i', 'i.id', '=', 'p.institution_id')
            ->whereIn('p.institution_id', $institutionIds)->where('p.status', 'success')
            ->whereBetween('p.paid_at', [
                Carbon::parse($filters['from'])->startOfDay(),
                Carbon::parse($filters['to'])->endOfDay(),
            ]);
        if ($collectorUserId) $this->restrictToCollector($query, $collectorUserId);
        if ($filters['mode'] !== '') $query->where('p.payment_mode', $filters['mode']);
        if ($filters['q'] !== '') {
            $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $query->where(function ($sub) use ($needle) {
                $sub->where('u.name', 'like', $needle)
                    ->orWhere('u.registration_number', 'like', $needle)
                    ->orWhere('p.receipt_no', 'like', $needle)
                    ->orWhere('p.reference_number', 'like', $needle)
                    ->orWhere('p.id', 'like', $needle);
            });
        }
        return $query->select([
            'p.id', 'p.receipt_no', 'p.amount', 'p.payment_mode', 'p.reference_number',
            'p.paid_at', 'p.status', 'u.name as student', 'u.registration_number',
            'i.display_name as institution',
        ]);
    }

    private function assertCashierPermission(Request $request, string $permission): void
    {
        $allowed = DB::table('tagore_user_roles as ur')
            ->join('tagore_role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('tagore_permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('ur.user_id', $request->user()->id)->where('ur.status', 'active')
            ->where('p.code', $permission)->exists();
        abort_unless($allowed, 403);
    }

    private function cashierOnly($roles): bool
    {
        return $roles->contains('CASHIER') && $roles->intersect(['OWNER', 'PRINCIPAL', 'ACCOUNTS'])->isEmpty();
    }

    private function restrictToCollector($query, int $userId): void
    {
        $query->whereExists(function ($audit) use ($userId) {
            $audit->selectRaw('1')->from('tagore_audit_events as a')
                ->whereColumn('a.entity_id', 'p.id')
                ->where('a.entity_type', 'tagore_payments')
                ->where('a.action', 'FEE_PAYMENT_RECORDED')
                ->where('a.user_id', $userId);
        });
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:cash,upi,card,cheque,bank_transfer,other'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = $data['from'] ?? now()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        abort_if(Carbon::parse($to)->lt(Carbon::parse($from)), 422, 'The end date must be on or after the start date.');
        abort_if(Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366, 422, 'Choose a date range of one year or less.');
        return ['from' => $from, 'to' => $to, 'mode' => $data['mode'] ?? '', 'q' => trim($data['q'] ?? '')];
    }

    private function scope(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $roles = DB::table('tagore_user_roles as ur')->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->where('ur.status', 'active')
            ->where(fn ($q) => $q->whereNull('ur.start_date')->orWhereDate('ur.start_date', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ur.end_date')->orWhereDate('ur.end_date', '>=', today()))
            ->pluck('r.code')->unique()->values();
        abort_unless($roles->intersect(['OWNER', 'PRINCIPAL', 'ACCOUNTS', 'CASHIER'])->isNotEmpty(), 403);

        $institutions = DB::table('tagore_institutions')->where('status', 'active');
        if (!$roles->contains('OWNER')) {
            $institutions->whereIn('id', DB::table('tagore_user_roles')->where('user_id', $userId)
                ->where('status', 'active')->whereNotNull('institution_id')
                ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->pluck('institution_id'));
        }
        return [$roles, $institutions->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }
}
