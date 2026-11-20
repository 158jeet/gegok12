<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LegacyFeeImportService
{
    public function preview(string $path, int $institutionId, ?int $academicYearId, int $createdBy): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $this->findSheet($spreadsheet, ['OPENING', 'OPENING BALANCE', 'OPENING BALANCES']);
        if (!$sheet) throw ValidationException::withMessages(['file' => 'No OPENING sheet was found.']);
        $rows = $this->rows($sheet);
        $batchId = DB::table('tagore_fee_import_batches')->insertGetId([
            'institution_id' => $institutionId, 'academic_year_id' => $academicYearId, 'created_by' => $createdBy,
            'source_name' => basename($path), 'source_type' => strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'xlsx'),
            'import_type' => 'opening_balance', 'status' => 'previewed', 'row_count' => count($rows),
            'mapping_json' => json_encode(['sheet' => $sheet->getTitle(), 'matching' => 'id_or_exact_name']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $success = 0; $errors = 0;
        foreach ($rows as $index => $row) {
            $key = $this->value($row, ['REG NO', 'REGISTRATION NO', 'STUDENT ID', 'ADM NO', 'ADMISSION NO']);
            $name = trim((string) $this->value($row, ['STUDENT', 'STUDENT NAME', 'NAME']));
            $balance = $this->money($this->value($row, ['BALANCE', 'FEE BALANCE', 'OPENING BALANCE', 'FEE AMOUNT']));
            [$studentId, $error] = $this->matchStudent($institutionId, $key, $name);
            $status = $error ? 'error' : ($balance > 0 ? 'ready' : 'skipped');
            if ($status === 'ready') $success++; if ($error) $errors++;
            DB::table('tagore_fee_import_rows')->insert([
                'batch_id' => $batchId, 'row_number' => $index + 2,
                'external_student_key' => $key !== null ? (string) $key : null, 'student_id' => $studentId,
                'opening_balance' => $balance, 'status' => $status, 'error_message' => $error,
                'raw_json' => json_encode($row, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('tagore_fee_import_batches')->where('id', $batchId)->update([
            'success_count' => $success, 'error_count' => $errors, 'status' => $errors ? 'needs_review' : 'ready', 'updated_at' => now(),
        ]);
        return compact('batchId', 'success', 'errors') + ['row_count' => count($rows)];
    }

    public function applyOpeningBalances(int $batchId, int $createdBy): int
    {
        return DB::transaction(function () use ($batchId, $createdBy) {
            $batch = DB::table('tagore_fee_import_batches')->where('id', $batchId)->lockForUpdate()->first();
            abort_unless($batch, 404);
            if (!in_array($batch->status, ['ready', 'needs_review'], true)) throw ValidationException::withMessages(['batch' => 'Import batch is not ready.']);
            $rows = DB::table('tagore_fee_import_rows')->where('batch_id', $batchId)->where('status', 'ready')->lockForUpdate()->get();
            $applied = 0;
            foreach ($rows as $row) {
                if (!$row->student_id || (float) $row->opening_balance <= 0) continue;
                $obligationId = DB::table('tagore_fee_obligations')->insertGetId([
                    'student_id' => $row->student_id, 'institution_id' => $batch->institution_id, 'academic_year_id' => $batch->academic_year_id,
                    'fee_structure_id' => null, 'due_date' => null, 'gross_amount' => $row->opening_balance, 'discount_amount' => 0,
                    'concession_amount' => 0, 'net_amount' => $row->opening_balance, 'paid_amount' => 0,
                    'outstanding_amount' => $row->opening_balance, 'status' => 'opening_balance', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('tagore_fee_obligation_items')->insert([
                    'fee_obligation_id' => $obligationId, 'fee_head' => 'Opening Balance', 'code' => 'OPENING_BALANCE',
                    'gross_amount' => $row->opening_balance, 'discount_amount' => 0, 'concession_amount' => 0,
                    'net_amount' => $row->opening_balance, 'paid_amount' => 0, 'outstanding_amount' => $row->opening_balance,
                    'metadata_json' => json_encode(['import_batch_id' => $batchId, 'source_row' => $row->row_number]), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('tagore_financial_transactions')->insert([
                    'institution_id' => $batch->institution_id, 'student_id' => $row->student_id, 'transaction_type' => 'OPENING_BALANCE',
                    'reference_type' => 'tagore_fee_import_batch', 'reference_id' => $batchId, 'debit' => $row->opening_balance, 'credit' => 0,
                    'balance_after' => null, 'description' => 'Legacy fee opening balance', 'transaction_date' => now(), 'created_by' => $createdBy,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('tagore_fee_import_rows')->where('id', $row->id)->update(['status' => 'applied', 'updated_at' => now()]);
                $applied++;
            }
            DB::table('tagore_fee_import_batches')->where('id', $batchId)->update(['status' => 'applied', 'updated_at' => now()]);
            return $applied;
        });
    }

    private function findSheet($spreadsheet, array $names) { foreach ($spreadsheet->getWorksheetIterator() as $sheet) { $title = strtoupper(trim($sheet->getTitle())); foreach ($names as $name) if ($title === $name) return $sheet; } return null; }
    private function rows($sheet): array {
        $all = $sheet->toArray(null, true, true, true); if (!$all) return [];
        $headers = array_map(fn ($v) => strtoupper(trim((string) $v)), array_shift($all)); $rows = [];
        foreach ($all as $values) { $row = []; foreach ($headers as $column => $header) if ($header !== '') $row[$header] = $values[$column] ?? null; if (count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) > 0) $rows[] = $row; }
        return $rows;
    }
    private function value(array $row, array $keys) { foreach ($keys as $key) if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') return $row[$key]; return null; }
    private function money($value): float { return round((float) preg_replace('/[^0-9.\-]/', '', (string) $value), 2); }
    private function matchStudent(int $institutionId, $key, string $name): array {
        $schoolId = DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
        if (!$schoolId) return [null, 'Institution is not linked to a GegoK12 school.'];
        if ($key !== null && ctype_digit(trim((string) $key))) {
            $student = DB::table('users')->where('id', (int) $key)->where('school_id', $schoolId)->where('usergroup_id', 6)->first(['id']);
            if ($student) return [$student->id, null];
        }
        if ($name === '') return [null, 'Student name is missing.'];
        $matches = DB::table('users')->where('school_id', $schoolId)->where('usergroup_id', 6)->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->pluck('id');
        if ($matches->count() === 1) return [$matches->first(), null];
        if ($matches->count() > 1) return [null, 'Multiple exact-name matches; manual mapping required.'];
        return [null, 'Student could not be matched by ID or exact name.'];
    }
}
