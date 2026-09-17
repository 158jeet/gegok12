<?php

namespace App\Services\Tagore;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LegacyFeeReconciliationService
{
    public function compare(string $path, int $institutionId, ?int $academicYearId = null): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('ALL LEDGER');
        if (!$sheet) {
            return ['available' => false, 'message' => 'ALL LEDGER sheet not found.', 'rows' => [], 'totals' => []];
        }

        $headers = $this->detectHeaders($sheet->toArray(null, true, true, true));
        if (!$headers) {
            return ['available' => false, 'message' => 'ALL LEDGER header row could not be detected.', 'rows' => [], 'totals' => []];
        }

        $rows = [];
        $all = $sheet->toArray(null, true, true, true);
        foreach ($all as $number => $values) {
            if ($number <= $headers['row']) continue;
            $row = $this->mapRow($headers['map'], $values);
            $name = trim((string)$this->value($row, ['STUDENT', 'STUDENT NAME', 'NAME']));
            if ($name === '') continue;
            $key = trim((string)$this->value($row, ['REG NO', 'REGISTRATION NO', 'ADM NO', 'ADMISSION NO', 'STUDENT ID']));
            $expected = $this->money($this->value($row, ['BALANCE', 'BALANCE DUE', 'FEE BALANCE', 'DUE', 'OUTSTANDING', 'TOTAL DUE']));
            $student = $this->resolve($institutionId, $key, $name);
            $actual = null;
            if ($student) {
                $q = DB::table('tagore_fee_obligations')->where('institution_id', $institutionId)->where('student_id', $student);
                if ($academicYearId) $q->where('academic_year_id', $academicYearId);
                $actual = round((float)$q->sum('outstanding_amount'), 2);
            }
            $delta = $student && $actual !== null ? round($actual - $expected, 2) : null;
            $rows[] = [
                'row' => $number,
                'source_key' => $key ?: null,
                'student_name' => $name,
                'student_id' => $student,
                'legacy_balance' => $expected,
                'tagore_balance' => $actual,
                'difference' => $delta,
                'status' => !$student ? 'unmapped' : (abs((float)$delta) < 0.01 ? 'matched' : 'mismatch'),
            ];
        }

        $totals = [
            'rows' => count($rows),
            'mapped' => count(array_filter($rows, fn ($r) => $r['student_id'] !== null)),
            'unmapped' => count(array_filter($rows, fn ($r) => $r['status'] === 'unmapped')),
            'matched' => count(array_filter($rows, fn ($r) => $r['status'] === 'matched')),
            'mismatch' => count(array_filter($rows, fn ($r) => $r['status'] === 'mismatch')),
            'legacy_balance' => round(array_sum(array_column($rows, 'legacy_balance')), 2),
            'tagore_balance' => round(array_sum(array_filter(array_column($rows, 'tagore_balance'), fn ($v) => $v !== null)), 2),
        ];
        $totals['difference'] = round($totals['tagore_balance'] - $totals['legacy_balance'], 2);
        return ['available' => true, 'message' => null, 'rows' => $rows, 'totals' => $totals];
    }

    private function detectHeaders(array $all): ?array
    {
        foreach (array_slice($all, 0, 20, true) as $number => $values) {
            $labels = array_values(array_filter(array_map(fn ($v) => strtoupper(trim((string)$v)), $values)));
            $hits = count(array_intersect($labels, ['STUDENT', 'STUDENT NAME', 'NAME', 'BALANCE', 'BALANCE DUE', 'FEE BALANCE', 'REG NO', 'ADM NO']));
            if ($hits >= 2) {
                $map = [];
                foreach ($values as $col => $value) {
                    $label = strtoupper(trim((string)$value));
                    if ($label !== '') $map[$col] = $label;
                }
                return ['row' => $number, 'map' => $map];
            }
        }
        return null;
    }

    private function mapRow(array $map, array $values): array
    {
        $row = [];
        foreach ($map as $col => $label) $row[$label] = $values[$col] ?? null;
        return $row;
    }

    private function resolve(int $institutionId, string $key, string $name): ?int
    {
        if ($key !== '') {
            $mapped = DB::table('tagore_legacy_student_mappings')->where('institution_id', $institutionId)->where('source_system', 'legacy_erp')->where('source_key', $key)->value('student_id');
            if ($mapped) return (int)$mapped;
        }
        $school = DB::table('tagore_institutions')->where('id', $institutionId)->value('school_id');
        if (!$school) return null;
        $ids = DB::table('users')->where('school_id', $school)->where('usergroup_id', 6)->whereRaw('lower(trim(name))=?', [mb_strtolower($name)])->pluck('id');
        return $ids->count() === 1 ? (int)$ids->first() : null;
    }

    private function value(array $row, array $keys): mixed
    {
        foreach ($keys as $key) if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') return $row[$key];
        return null;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') return 0;
        return round((float)preg_replace('/[^0-9.\-]/', '', (string)$value), 2);
    }
}
