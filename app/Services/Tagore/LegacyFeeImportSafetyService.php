<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegacyFeeImportSafetyService
{
    public function __construct(private LegacyFeeMigrationService $legacy)
    {
    }

    public function preview(string $path, int $institutionId, ?int $academicYearId, int $actorId): array
    {
        $result = $this->legacy->preview($path, $institutionId, $academicYearId, $actorId);
        $this->sanitizeBatch((int) $result['batchId'], $institutionId);

        $batch = DB::table('tagore_fee_import_batches')->where('id', $result['batchId'])->first();

        return [
            'batchId' => (int) $batch->id,
            'success' => (int) $batch->success_count,
            'errors' => (int) $batch->error_count,
            'row_count' => (int) $batch->row_count,
        ];
    }

    public function apply(int $batchId, int $actorId): array
    {
        $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->first();
        abort_unless($batch, 404);

        $this->sanitizeBatch((int) $batchId, (int) $batch->institution_id);

        $errors = DB::table('tagore_fee_import_rows')
            ->where('batch_id', $batchId)
            ->where('status', 'error')
            ->count();

        if ($errors > 0) {
            throw ValidationException::withMessages([
                'batch' => "Import batch {$batchId} has {$errors} rows requiring review. Resolve the student mappings or source data before applying it.",
            ]);
        }

        return $this->legacy->apply($batchId, $actorId);
    }

    private function sanitizeBatch(int $batchId, int $institutionId): void
    {
        $rows = DB::table('tagore_fee_import_rows')
            ->where('batch_id', $batchId)
            ->get();

        foreach ($rows as $row) {
            if (!in_array($row->status, ['ready', 'error'], true)) {
                continue;
            }

            $payload = json_decode((string) $row->raw_json, true) ?: [];
            $source = $payload['data'] ?? [];
            $sourceKey = trim((string) ($row->external_student_key ?? ''));
            $studentId = $this->explicitMapping($institutionId, $sourceKey);

            if ($studentId) {
                DB::table('tagore_fee_import_rows')->where('id', $row->id)->update([
                    'student_id' => $studentId,
                    'status' => $this->amountError($row, $source) ?: 'ready',
                    'error_message' => $this->amountError($row, $source),
                    'updated_at' => now(),
                ]);
                continue;
            }

            if ($sourceKey !== '' && ctype_digit($sourceKey) && $row->student_id) {
                $sourceName = $this->normalise($this->value($source, ['STUDENT', 'STUDENT NAME', 'NAME']));
                $userName = DB::table('users')->where('id', $row->student_id)->value('name');

                if ($sourceName === '' || $this->normalise((string) $userName) !== $sourceName) {
                    DB::table('tagore_fee_import_rows')->where('id', $row->id)->update([
                        'student_id' => null,
                        'status' => 'error',
                        'error_message' => 'Legacy numeric identifier was not accepted as a GegoK12 user ID. Create an explicit legacy student mapping or provide a uniquely matching student name.',
                        'updated_at' => now(),
                    ]);
                    continue;
                }
            }

            $amountError = $this->amountError($row, $source);
            if ($amountError) {
                DB::table('tagore_fee_import_rows')->where('id', $row->id)->update([
                    'status' => 'error',
                    'error_message' => $amountError,
                    'updated_at' => now(),
                ]);
            }
        }

        $errors = DB::table('tagore_fee_import_rows')->where('batch_id', $batchId)->where('status', 'error')->count();
        $ready = DB::table('tagore_fee_import_rows')->where('batch_id', $batchId)->where('status', 'ready')->count();

        DB::table('tagore_fee_import_batches')->where('id', $batchId)->update([
            'success_count' => $ready,
            'error_count' => $errors,
            'status' => $errors > 0 ? 'needs_review' : 'ready',
            'updated_at' => now(),
        ]);
    }

    private function explicitMapping(int $institutionId, string $sourceKey): ?int
    {
        if ($sourceKey === '') {
            return null;
        }

        return DB::table('tagore_legacy_student_mappings')
            ->where('institution_id', $institutionId)
            ->where('source_system', 'legacy_erp')
            ->where('source_key', $sourceKey)
            ->value('student_id');
    }

    private function amountError(object $row, array $source): ?string
    {
        if (!in_array($row->row_type, ['student_fee', 'opening_balance'], true)) {
            return null;
        }

        if ($row->row_type === 'opening_balance') {
            return ((float) ($row->opening_balance ?? 0)) < 0
                ? 'Opening balance cannot be negative.'
                : null;
        }

        $gross = round((float) ($row->gross_amount ?? 0), 2);
        $discount = round((float) ($row->discount_amount ?? 0), 2);
        $concession = round((float) ($row->concession_amount ?? 0), 2);
        $paid = round((float) ($row->paid_amount ?? 0), 2);
        $net = round($gross - $discount - $concession, 2);

        if ($gross < 0 || $discount < 0 || $concession < 0 || $paid < 0) {
            return 'Fee amounts cannot be negative.';
        }
        if ($discount + $concession > $gross && $gross > 0) {
            return 'Discount plus concession exceeds the gross fee; source data requires review.';
        }
        if ($paid > $net + 0.01) {
            return 'Received amount exceeds the net fee. The import will not silently cap or discard the excess; source allocation requires review.';
        }

        return null;
    }

    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return $row[$key];
            }
        }
        return null;
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
