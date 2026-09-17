<?php

namespace App\Services\Tagore;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LegacyFeeWorkbookParser
{
    public function parse(Worksheet $sheet): array
    {
        $title = strtoupper(trim($sheet->getTitle()));
        return match (true) {
            $title === 'FEE STRUCTURE' => $this->feeStructure($sheet),
            in_array($title, ['BUS FEE 26-27', 'BUS FEE 2026-27'], true) => $this->busFees($sheet),
            in_array($title, ['OPENING', 'OPENING BALANCE', 'OPENING BALANCES'], true) => $this->opening($sheet),
            in_array($title, ['XII SCI FEE STRUCTURE', 'XII SCIENCE FEE STRUCTURE'], true) => $this->xiiScience($sheet),
            in_array($title, ['FEE CONCESSION', 'FEE CONCESSIONS'], true) => $this->tabular($sheet, 'concession'),
            $title === 'STUDENTS' => $this->tabular($sheet, 'student_master'),
            $title === 'ALL LEDGER' => $this->allLedger($sheet),
            default => [],
        };
    }

    private function feeStructure(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = $this->findRowContaining($rows, ['CLASS', 'ADM.FEE', 'TOTAL FEE']);
        if ($headerRow === null) return [];
        $headers = $this->headers($rows[$headerRow]);
        return $this->recordsAfter($rows, $headerRow, $headers, 'fee_structure', function ($row) {
            return $this->nonEmpty($row, ['CLASS']);
        });
    }

    private function busFees(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $header = $this->findRowContaining($rows, ['SN', 'ROUTE NAME', 'AMOUNT']);
        if ($header === null) return [];
        $out = [];
        foreach ($rows as $number => $row) {
            if ($number <= $header) continue;
            foreach ([['A','B','C'], ['E','F','G']] as [$sn, $name, $amount]) {
                $route = trim((string)($row[$name] ?? ''));
                if ($route === '') continue;
                $fee = $this->number($row[$amount] ?? null);
                if ($fee <= 0) continue;
                $out[] = [
                    'type' => 'transport_route', 'row' => $number, 'data' => [
                        'SN' => $row[$sn] ?? null, 'ROUTE NAME' => $route, 'AMOUNT' => $fee,
                    ],
                ];
            }
        }
        return $out;
    }

    private function opening(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $header = $this->findRowContaining($rows, ['SR NO', 'CLASS', 'STUDENT NAME', 'BALANCE']);
        if ($header === null) return [];
        $headers = $this->headers($rows[$header]);
        return $this->recordsAfter($rows, $header, $headers, 'opening_balance', function ($row) {
            return $this->nonEmpty($row, ['STUDENT NAME']) && $this->number($row['BALANCE'] ?? 0) > 0;
        });
    }

    private function xiiScience(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        if (!$rows) return [];
        $header = 1;
        $headers = $this->headers($rows[$header] ?? []);
        $records = [];
        foreach ($rows as $number => $row) {
            if ($number <= $header) continue;
            $name = trim((string)($row['B'] ?? ''));
            if ($name === '') continue;
            $data = [
                'SR NO' => $row['A'] ?? null,
                'STUDENT NAME' => $name,
                "FATHER'S NAME" => $row['C'] ?? null,
                'FEE AMOUNT' => $this->number($row['D'] ?? 0),
            ];
            $paymentNo = 1;
            for ($col = 5; $col <= 15; $col += 3) {
                $receipt = $row[$this->column($col)] ?? null;
                $date = $row[$this->column($col + 1)] ?? null;
                $amount = $this->number($row[$this->column($col + 2)] ?? 0);
                if ($amount <= 0) continue;
                $data["RECEIPT {$paymentNo}"] = $receipt;
                $data["DATE {$paymentNo}"] = $date;
                $data["AMOUNT {$paymentNo}"] = $amount;
                $paymentNo++;
            }
            $records[] = ['type' => 'student_fee', 'row' => $number, 'data' => $data];
        }
        return $records;
    }

    private function allLedger(Worksheet $sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $header = $this->findRowContaining($rows, ['SRNO', 'STUDENT NAME', 'OP BALANCE']);
        if ($header === null) return [];
        $headers = $this->headers($rows[$header]);
        return $this->recordsAfter($rows, $header, $headers, 'ledger_snapshot', function ($row) {
            return $this->nonEmpty($row, ['STUDENT NAME']);
        });
    }

    private function tabular(Worksheet $sheet, string $type): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        $header = $this->findHeaderRow($rows);
        if ($header === null) return [];
        $headers = $this->headers($rows[$header]);
        return $this->recordsAfter($rows, $header, $headers, $type, fn ($row) => count(array_filter($row, fn ($v) => trim((string)$v) !== '')) > 0);
    }

    private function findHeaderRow(array $rows): ?int
    {
        foreach ($rows as $n => $row) {
            $text = array_map(fn ($v) => strtoupper(trim((string)$v)), $row);
            if (in_array('STUDENT NAME', $text, true) || in_array('SR NO', $text, true) || in_array('SRNO', $text, true)) return $n;
        }
        return null;
    }

    private function findRowContaining(array $rows, array $needles): ?int
    {
        foreach ($rows as $n => $row) {
            $text = implode(' | ', array_map(fn ($v) => strtoupper(trim((string)$v)), $row));
            $matched = 0;
            foreach ($needles as $needle) if (str_contains($text, strtoupper($needle))) $matched++;
            if ($matched >= min(2, count($needles))) return $n;
        }
        return null;
    }

    private function headers(array $row): array
    {
        $headers = [];
        foreach ($row as $col => $value) {
            $name = strtoupper(trim((string)$value));
            if ($name !== '') $headers[$col] = $name;
        }
        return $headers;
    }

    private function recordsAfter(array $rows, int $headerRow, array $headers, string $type, callable $keep): array
    {
        $out = [];
        foreach ($rows as $number => $values) {
            if ($number <= $headerRow) continue;
            $data = [];
            foreach ($headers as $col => $name) $data[$name] = $values[$col] ?? null;
            if ($keep($data)) $out[] = ['type' => $type, 'row' => $number, 'data' => $data];
        }
        return $out;
    }

    private function nonEmpty(array $row, array $keys): bool
    {
        foreach ($keys as $key) if (trim((string)($row[$key] ?? '')) !== '') return true;
        return false;
    }

    private function number($value): float
    {
        if ($value === null || $value === '') return 0.0;
        return round((float)preg_replace('/[^0-9.\-]/', '', (string)$value), 2);
    }

    private function column(int $index): string
    {
        $result = '';
        while ($index > 0) { $index--; $result = chr(65 + ($index % 26)) . $result; $index = intdiv($index, 26); }
        return $result;
    }
}
