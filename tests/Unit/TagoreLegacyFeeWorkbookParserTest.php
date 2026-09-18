<?php

namespace Tests\Unit;

use App\Services\Tagore\LegacyFeeWorkbookParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class TagoreLegacyFeeWorkbookParserTest extends TestCase
{
    private function sheet(array $rows, string $title)
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($title);
        foreach ($rows as $r => $values) foreach (array_values($values) as $c => $value) $sheet->setCellValueByColumnAndRow($c + 1, $r + 1, $value);
        return $sheet;
    }

    public function test_fee_structure_uses_real_2026_27_headers_and_preserves_one_time_fee(): void
    {
        $sheet = $this->sheet([
            ['TAGORE PUBLIC SCHOOL, GUDHA GORJI'],
            ['FEE STRUCTURE - 2026-27'],
            ['', '', 'TUITION FEE'],
            ['CLASS', 'ADM.FEE', 'I', 'II', 'III', 'TOTAL FEE', 'ONE TIME FEE'],
            ['VIII', 1000, 20500, 20500, 10400, 51400, 46250],
        ], 'FEE STRUCTURE');

        $records = (new LegacyFeeWorkbookParser())->parse($sheet);

        $this->assertCount(1, $records);
        $this->assertSame('VIII', $records[0]['data']['CLASS']);
        $this->assertSame(46250.0, (float) $records[0]['data']['ONE TIME FEE']);
    }

    public function test_bus_parser_handles_two_route_tables_side_by_side(): void
    {
        $sheet = $this->sheet([
            ['SN', 'ROUTE NAME', 'AMOUNT', '', 'SN', 'ROUTE NAME', 'AMOUNT'],
            [1, 'Route A', 12000, '', 1, 'Route X', 15000],
            [2, 'Route B', 13000, '', 2, 'Route Y', 16000],
        ], 'BUS FEE 26-27');

        $records = (new LegacyFeeWorkbookParser())->parse($sheet);

        $this->assertCount(4, $records);
        $this->assertSame('Route A', $records[0]['data']['ROUTE NAME']);
        $this->assertSame(16000.0, (float) $records[3]['data']['AMOUNT']);
    }

    public function test_xii_science_parser_preserves_multiple_receipts_and_total_received(): void
    {
        $sheet = $this->sheet([
            ['index', 'XII', 'Student Name', "Father's Name", 'Fee Amount', 'R No', 'Date', 'Amount', 'R No.1', 'Date.1', 'Amount.1', 'R No.2', 'Date.2', 'Amount.2', 'Total', 'Balance'],
            [0, 6857, 'AAYUSHI SHARMA', 'VIKESH KUMAR SHARMA', 86000, 407, '2026-04-28', 5000, '', '', '', '', '', '', 5000, 81000],
            [1, 4711, 'AKANSHA', 'DAYARAM KHAIRWA', 135480, 792, '2026-05-15', 35000, 414, '2026-04-28', 5000, '', '', '', 40000, 95480],
        ], 'XII SCI FEE STRUCTURE');

        $records = (new LegacyFeeWorkbookParser())->parse($sheet);

        $this->assertCount(2, $records);
        $this->assertCount(2, $records[1]['data']['PAYMENTS']);
        $this->assertSame(40000.0, (float) $records[1]['data']['RECEIVED']);
        $this->assertSame(414, $records[1]['data']['PAYMENTS'][1]['receipt']);
    }

    public function test_all_ledger_is_tagged_as_reconciliation_reference(): void
    {
        $sheet = $this->sheet([
            ['SRNO', 'STUDENT NAME', 'OP BALANCE', 'BALANCE'],
            [1, 'TEST STUDENT', 1000, 2500],
        ], 'ALL LEDGER');

        $records = (new LegacyFeeWorkbookParser())->parse($sheet);

        $this->assertCount(1, $records);
        $this->assertSame('ledger_reference', $records[0]['type']);
        $this->assertEquals(2500, (float) $records[0]['data']['BALANCE']);
    }
}
