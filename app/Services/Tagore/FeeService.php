<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeService
{
    /** Create a student fee obligation and its immutable line items. */
    public function createObligation(
        int $studentId,
        int $institutionId,
        array $data,
        ?int $createdBy = null,
    ): int {
        return DB::transaction(function () use ($studentId, $institutionId, $data, $createdBy) {
            $items = $data['items'] ?? [];
            $gross = round((float) ($data['gross_amount'] ?? collect($items)->sum('gross_amount')), 2);
            $discount = round((float) ($data['discount_amount'] ?? collect($items)->sum('discount_amount')), 2);
            $concession = round((float) ($data['concession_amount'] ?? collect($items)->sum('concession_amount')), 2);
            $net = round($gross - $discount - $concession, 2);

            if ($gross < 0 || $discount < 0 || $concession < 0 || $net < 0) {
                throw ValidationException::withMessages(['fee' => 'Fee amounts cannot produce a negative payable amount.']);
            }
            if (!$items) {
                throw ValidationException::withMessages(['items' => 'A fee obligation must contain at least one fee line.']);
            }

            $now = now();
            $obligationId = DB::table('tagore_fee_obligations')->insertGetId([
                'student_id' => $studentId,
                'institution_id' => $institutionId,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'fee_structure_id' => $data['fee_structure_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'concession_amount' => $concession,
                'net_amount' => $net,
                'paid_amount' => 0,
                'outstanding_amount' => $net,
                'status' => $net > 0 ? 'pending' : 'paid',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($items as $item) {
                $itemGross = round((float) ($item['gross_amount'] ?? $item['amount'] ?? 0), 2);
                $itemDiscount = round((float) ($item['discount_amount'] ?? 0), 2);
                $itemConcession = round((float) ($item['concession_amount'] ?? 0), 2);
                $itemNet = round($itemGross - $itemDiscount - $itemConcession, 2);
                if ($itemGross < 0 || $itemDiscount < 0 || $itemConcession < 0 || $itemNet < 0) {
                    throw ValidationException::withMessages(['items' => 'A fee line contains an invalid amount.']);
                }

                DB::table('tagore_fee_obligation_items')->insert([
                    'fee_obligation_id' => $obligationId,
                    'fee_component_id' => $item['fee_component_id'] ?? null,
                    'fee_head' => $item['fee_head'] ?? 'Fee',
                    'code' => $item['code'] ?? null,
                    'gross_amount' => $itemGross,
                    'discount_amount' => $itemDiscount,
                    'concession_amount' => $itemConcession,
                    'net_amount' => $itemNet,
                    'paid_amount' => 0,
                    'outstanding_amount' => $itemNet,
                    'metadata_json' => isset($item['metadata']) ? json_encode($item['metadata']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (!empty($data['frequency'])) {
                $this->createInstallments($obligationId, $net, (string) $data['frequency'], $data['due_date'] ?? null);
            }

            $this->audit($studentId, $institutionId, 'FEE_OBLIGATION_CREATED', 'tagore_fee_obligations', $obligationId, $createdBy, [
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'concession_amount' => $concession,
                'net_amount' => $net,
            ]);
            return $obligationId;
        });
    }

    /** Generate missing demands for a resolved class/section student set. */
    public function createBulkDemands(
        array $studentIds,
        int $institutionId,
        int $academicYearId,
        int $feeStructureId,
        string $frequency,
        array $items,
        float $grossAmount,
        ?string $dueDate = null,
        ?int $createdBy = null,
    ): array {
        return DB::transaction(function () use ($studentIds, $institutionId, $academicYearId, $feeStructureId, $frequency, $items, $grossAmount, $dueDate, $createdBy) {
            $created = [];
            $skipped = [];
            foreach (array_values(array_unique(array_map('intval', $studentIds))) as $studentId) {
                $existing = DB::table('tagore_fee_obligations')
                    ->where('student_id', $studentId)
                    ->where('institution_id', $institutionId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('fee_structure_id', $feeStructureId)
                    ->lockForUpdate()
                    ->exists();
                if ($existing) {
                    $skipped[] = $studentId;
                    continue;
                }

                $id = $this->createObligation($studentId, $institutionId, [
                    'academic_year_id' => $academicYearId,
                    'fee_structure_id' => $feeStructureId,
                    'due_date' => $dueDate,
                    'frequency' => $frequency,
                    'items' => $items,
                    'gross_amount' => $grossAmount,
                ], $createdBy);
                $created[] = $id;
            }
            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    private function createInstallments(int $obligationId, float $total, string $frequency, ?string $dueDate): void
    {
        $count = $frequency === 'monthly' ? 12 : ($frequency === 'term' ? 3 : 1);
        $base = round($total / $count, 2);
        $running = 0;
        $start = $dueDate ? \Carbon\Carbon::parse($dueDate) : now();
        for ($n = 1; $n <= $count; $n++) {
            $amount = $n === $count ? round($total - $running, 2) : $base;
            $running = round($running + $amount, 2);
            $date = $count === 1 ? $start->copy() : $start->copy()->addMonths($n - 1);
            DB::table('tagore_fee_installments')->insert([
                'fee_obligation_id' => $obligationId,
                'installment_no' => $n,
                'name' => $count === 1 ? 'Full Fee' : ($frequency === 'monthly' ? "Month {$n}" : "Term {$n}"),
                'due_date' => $date->toDateString(),
                'amount' => $amount,
                'paid_amount' => 0,
                'outstanding_amount' => $amount,
                'status' => $amount > 0 ? 'pending' : 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Record an offline payment and allocate it to obligations/installments. */
    public function recordOfflinePayment(
        int $studentId,
        int $institutionId,
        float $amount,
        ?int $parentUserId = null,
        ?int $createdBy = null,
        ?string $reference = null,
        array $allocations = [],
        string $paymentMode = 'cash',
    ): int {
        return DB::transaction(function () use ($studentId, $institutionId, $amount, $parentUserId, $createdBy, $reference, $allocations, $paymentMode) {
            $amount = round($amount, 2);
            if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            $requested = round(collect($allocations)->sum(fn ($row) => (float) ($row['amount'] ?? 0)), 2);
            if ($allocations && abs($requested - $amount) > 0.009) throw ValidationException::withMessages(['allocations' => 'Payment allocations must equal the payment amount.']);

            $now = now();
            $receiptNo = $this->nextReceiptNo($institutionId);
            $paymentId = DB::table('tagore_payments')->insertGetId([
                'receipt_no' => $receiptNo, 'student_id' => $studentId, 'parent_user_id' => $parentUserId,
                'institution_id' => $institutionId, 'amount' => $amount, 'currency' => 'INR', 'payment_mode' => $paymentMode,
                'gateway' => 'offline', 'reference_number' => $reference, 'status' => 'success', 'paid_at' => $now,
                'notes' => $reference ?: 'Offline payment', 'created_at' => $now, 'updated_at' => $now,
            ]);

            if (!$allocations) {
                $open = DB::table('tagore_fee_obligations')->where('student_id', $studentId)->where('institution_id', $institutionId)
                    ->where('outstanding_amount', '>', 0)->orderBy('due_date')->orderBy('id')->get();
                $remaining = $amount;
                foreach ($open as $obligation) {
                    if ($remaining <= 0) break;
                    $allocate = min($remaining, (float) $obligation->outstanding_amount);
                    $allocations[] = ['fee_obligation_id' => $obligation->id, 'amount' => $allocate];
                    $remaining = round($remaining - $allocate, 2);
                }
                if ($remaining > 0.009) throw ValidationException::withMessages(['amount' => 'Payment exceeds the student outstanding balance.']);
            }

            foreach ($allocations as $allocation) {
                $obligationId = (int) $allocation['fee_obligation_id'];
                $allocate = round((float) $allocation['amount'], 2);
                $obligation = DB::table('tagore_fee_obligations')->where('id', $obligationId)->lockForUpdate()->first();
                if (!$obligation || (int) $obligation->student_id !== $studentId || (int) $obligation->institution_id !== $institutionId) throw ValidationException::withMessages(['allocations' => 'Invalid fee obligation allocation.']);
                if ($allocate <= 0 || $allocate > ((float) $obligation->outstanding_amount + 0.009)) throw ValidationException::withMessages(['allocations' => 'Allocation exceeds the obligation outstanding amount.']);
                DB::table('tagore_payment_allocations')->insert([
                    'payment_id' => $paymentId, 'fee_obligation_id' => $obligationId,
                    'fee_installment_id' => $allocation['fee_installment_id'] ?? null,
                    'fee_obligation_item_id' => $allocation['fee_obligation_item_id'] ?? null,
                    'amount' => $allocate, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $paid = round((float) $obligation->paid_amount + $allocate, 2);
                $outstanding = round((float) $obligation->net_amount - $paid, 2);
                DB::table('tagore_fee_obligations')->where('id', $obligationId)->update(['paid_amount' => $paid, 'outstanding_amount' => max(0, $outstanding), 'status' => $outstanding <= 0.009 ? 'paid' : 'partial', 'updated_at' => $now]);
                if (!empty($allocation['fee_installment_id'])) $this->refreshInstallment((int) $allocation['fee_installment_id']);
            }

            DB::table('tagore_financial_transactions')->insert([
                'institution_id' => $institutionId, 'student_id' => $studentId, 'parent_user_id' => $parentUserId,
                'transaction_type' => 'PAYMENT', 'reference_type' => 'tagore_payment', 'reference_id' => $paymentId,
                'debit' => 0, 'credit' => $amount, 'description' => $reference ?: 'Offline payment', 'transaction_date' => $now,
                'created_by' => $createdBy, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->audit($studentId, $institutionId, 'FEE_PAYMENT_RECORDED', 'tagore_payments', $paymentId, $createdBy, ['amount' => $amount, 'receipt_no' => $receiptNo, 'payment_mode' => $paymentMode]);
            return $paymentId;
        });
    }

    private function refreshInstallment(int $installmentId): void
    {
        $installment = DB::table('tagore_fee_installments')->where('id', $installmentId)->first();
        if (!$installment) return;
        $paid = round((float) DB::table('tagore_payment_allocations')->where('fee_installment_id', $installmentId)->sum('amount'), 2);
        $outstanding = max(0, round((float) $installment->amount - $paid, 2));
        DB::table('tagore_fee_installments')->where('id', $installmentId)->update(['paid_amount' => $paid, 'outstanding_amount' => $outstanding, 'status' => $outstanding <= 0.009 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'), 'updated_at' => now()]);
    }

    private function nextReceiptNo(int $institutionId): string
    {
        $last = DB::table('tagore_payments')->where('institution_id', $institutionId)->whereNotNull('receipt_no')->lockForUpdate()->orderByDesc('id')->value('receipt_no');
        $sequence = $last && preg_match('/(\d+)$/', $last, $matches) ? ((int) $matches[1]) + 1 : 1;
        return sprintf('TAG-%d-%06d', $institutionId, $sequence);
    }

    private function audit(int $studentId, int $institutionId, string $action, string $entityType, int $entityId, ?int $userId, array $newValues): void
    {
        DB::table('tagore_audit_events')->insert(['user_id' => $userId, 'institution_id' => $institutionId, 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId, 'old_values_json' => null, 'new_values_json' => json_encode($newValues), 'ip_address' => null, 'user_agent' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
