<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;

class FeeService
{
    public function recordOfflinePayment(int $studentId, int $institutionId, float $amount, ?int $parentUserId = null, ?int $createdBy = null, ?string $reference = null): int
    {
        return DB::transaction(function () use ($studentId, $institutionId, $amount, $parentUserId, $createdBy, $reference) {
            $paymentId = DB::table('tagore_payments')->insertGetId([
                'student_id' => $studentId,
                'parent_user_id' => $parentUserId,
                'institution_id' => $institutionId,
                'amount' => $amount,
                'currency' => 'INR',
                'gateway' => 'offline',
                'status' => 'success',
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tagore_financial_transactions')->insert([
                'institution_id' => $institutionId,
                'student_id' => $studentId,
                'parent_user_id' => $parentUserId,
                'transaction_type' => 'PAYMENT',
                'reference_type' => 'tagore_payment',
                'reference_id' => $paymentId,
                'debit' => 0,
                'credit' => $amount,
                'description' => $reference ?: 'Offline payment',
                'transaction_date' => now(),
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $paymentId;
        });
    }
}
