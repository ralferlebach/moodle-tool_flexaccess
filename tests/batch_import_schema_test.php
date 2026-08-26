<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_flexaccess;

use tool_flexaccess\local\batch_import;

/**
 * Import hardening: bounded, deterministic, and loud about data loss.
 *
 * The importer must reject anything it cannot faithfully process instead of quietly dropping
 * records or letting a hostile workbook drive the reader.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch_import
 */
final class batch_import_schema_test extends \advanced_testcase {
    /**
     * Write a workbook with the given data rows, optionally with the schema marker.
     *
     * @param array $rows Data rows (each an array of up to seven values).
     * @param bool $marker Whether to write the schema marker into H1.
     * @return string Path to the written file.
     */
    private function workbook(array $rows, bool $marker = true): string {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['User', 'Pass', 'First', 'Last', 'Email', 'Profile', 'NewUser']], null, 'A1');
        if ($marker) {
            $sheet->setCellValue('H1', batch_import::SCHEMA_VERSION);
        }
        if ($rows) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $path = make_request_directory() . '/import.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        return $path;
    }

    public function test_file_without_schema_marker_is_rejected(): void {
        $this->resetAfterTest();
        $path = $this->workbook([['kurs-abc', 'pw', 'Max', 'Muster', 'max@example.com', '', '']], false);

        $this->expectException(\moodle_exception::class);
        batch_import::parse($path);
    }

    public function test_corrupt_file_is_rejected_with_a_clear_error(): void {
        $this->resetAfterTest();
        $path = make_request_directory() . '/broken.xlsx';
        file_put_contents($path, "PK\x03\x04 this is not a real workbook");

        try {
            batch_import::parse($path);
            $this->fail('A corrupt workbook must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('batchimportunreadable', $e->errorcode);
        }
    }

    public function test_too_many_rows_are_refused_instead_of_silently_truncated(): void {
        $this->resetAfterTest();
        $rows = [];
        for ($i = 0; $i <= batch_import::MAX_IMPORT_ROWS; $i++) {
            $rows[] = ["kurs-user$i", 'pw', 'First', 'Last', "u$i@example.com", '', ''];
        }
        $path = $this->workbook($rows);

        try {
            batch_import::parse($path);
            $this->fail('An over-long import must be refused, not truncated.');
        } catch (\moodle_exception $e) {
            $this->assertSame('batchimporttoomanyrows', $e->errorcode);
        }
    }

    public function test_inflated_row_dimension_does_not_drive_the_reader(): void {
        $this->resetAfterTest();
        // A hostile workbook can claim an enormous row dimension (the CVE-2026-40902 pattern).
        // The bounded iterator must ignore that claim and return only the real data rows.
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['User', 'Pass', 'First', 'Last', 'Email', 'Profile', 'NewUser']], null, 'A1');
        $sheet->setCellValue('H1', batch_import::SCHEMA_VERSION);
        $sheet->fromArray([['kurs-abc', 'pw', 'Max', 'Muster', 'max@example.com', '', '']], null, 'A2');
        // Declare a row far beyond the limit; no data is written in between.
        $sheet->getRowDimension(900000)->setRowHeight(20);
        $path = make_request_directory() . '/inflated.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $start = microtime(true);
        $rows = batch_import::parse($path);
        $elapsed = microtime(true) - $start;

        $this->assertCount(1, $rows);
        $this->assertSame('kurs-abc', $rows[0]['username']);
        // Bounded work, not proportional to the declared dimension.
        $this->assertLessThan(20, $elapsed, 'Parsing was driven by the declared row dimension.');
    }
}
