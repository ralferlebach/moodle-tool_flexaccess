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

        // A duration, not a point in time: the monotonic stopwatch (not the frozen calendar clock).
        $stopwatch = \core\di::get(\tool_flexaccess\local\stopwatch::class);
        $mark = $stopwatch->start();
        $rows = batch_import::parse($path);
        $elapsed = $stopwatch->seconds_since($mark);

        $this->assertCount(1, $rows);
        $this->assertSame('kurs-abc', $rows[0]['username']);
        // Bounded work, not proportional to the declared dimension.
        $this->assertLessThan(20, $elapsed, 'Parsing was driven by the declared row dimension.');
    }

    /**
     * Rewrite the worksheet XML of a workbook in place (for hand-made, manipulated packages).
     *
     * @param string $path Workbook.
     * @param callable $rewrite Receives and returns the sheet XML.
     * @param array $extra Further package entries to add (name => content).
     */
    private function rewrite(string $path, callable $rewrite, array $extra = []): void {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $zip->addFromString('xl/worksheets/sheet1.xml', $rewrite($zip->getFromName('xl/worksheets/sheet1.xml')));
        foreach ($extra as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    /**
     * The error code of a refused file.
     *
     * @param string $path Workbook.
     * @return \moodle_exception
     */
    private function refusal(string $path): \moodle_exception {
        try {
            batch_import::parse($path);
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail('The file must be refused.');
    }

    /**
     * Data rows for $n records.
     *
     * @param int $n Records.
     * @return array
     */
    private function records(int $n): array {
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $rows[] = ["kurs-user$i", 'pw', 'First', 'Last', "u$i@example.com", '', ''];
        }
        return $rows;
    }

    public function test_exactly_the_limit_is_imported_completely(): void {
        $this->resetAfterTest();
        $rows = batch_import::parse($this->workbook($this->records(batch_import::MAX_IMPORT_ROWS)));
        $this->assertCount(batch_import::MAX_IMPORT_ROWS, $rows);
        $this->assertSame('kurs-user' . batch_import::MAX_IMPORT_ROWS, end($rows)['username']);
    }

    public function test_limit_plus_one_is_refused(): void {
        $this->resetAfterTest();
        $e = $this->refusal($this->workbook($this->records(batch_import::MAX_IMPORT_ROWS + 1)));
        $this->assertSame('batchimporttoomanyrows', $e->errorcode);
    }

    /**
     * A single populated row far outside the window - the case that used to be dropped silently.
     *
     * @return array
     */
    public static function out_of_window_rows(): array {
        return ['A2003' => [2003], 'A50000' => [50000]];
    }

    /**
     * A populated row outside the window is refused with its row number.
     *
     * @dataProvider out_of_window_rows
     * @param int $rownumber Row of the stray record.
     */
    public function test_a_sparse_record_outside_the_window_is_refused(int $rownumber): void {
        $this->resetAfterTest();
        $path = $this->workbook([['kurs-first', 'pw', 'A', 'B', 'a@example.com', '', '']]);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $spreadsheet->getActiveSheet()->fromArray([['kurs-lost', 'pw', 'L', 'Ost', 'lost@example.com']], null, "A$rownumber");
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $e = $this->refusal($path);
        $this->assertSame('batchimportoutofwindow', $e->errorcode);
        $this->assertSame($rownumber, $e->a->row);
        $this->assertStringContainsString((string) $rownumber, get_string($e->errorcode, 'tool_flexaccess', $e->a));
    }

    public function test_a_sparse_sheet_inside_the_window_loses_no_record(): void {
        $this->resetAfterTest();
        $path = $this->workbook([]);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $last = batch_import::MAX_IMPORT_ROWS + 1;
        foreach ([2, 700, $last] as $r) {
            $spreadsheet->getActiveSheet()->fromArray([["kurs-row$r", 'pw', 'F', 'L', "r$r@example.com"]], null, "A$r");
        }
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $rows = batch_import::parse($path);
        $this->assertSame(['kurs-row2', 'kurs-row700', "kurs-row$last"], array_column($rows, 'username'));
    }

    public function test_a_record_without_username_is_refused_not_skipped(): void {
        $this->resetAfterTest();
        $e = $this->refusal($this->workbook([
            ['kurs-a', 'pw', 'A', 'A', 'a@example.com', '', ''],
            ['', '', 'Nobody', 'Known', 'nobody@example.com', '', ''],
        ]));
        $this->assertSame('batchimportrowwithoutusername', $e->errorcode);
        $this->assertSame(3, $e->a->row);

        // A value in a column the import does not read counts as well.
        $path = $this->workbook([['kurs-a', 'pw', 'A', 'A', 'a@example.com', '', '']]);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $spreadsheet->getActiveSheet()->setCellValue('K9', 'note');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $this->assertSame(9, $this->refusal($path)->a->row);
    }

    public function test_a_manipulated_dimension_does_not_hide_rows(): void {
        $this->resetAfterTest();
        $path = $this->workbook([['kurs-a', 'pw', 'A', 'A', 'a@example.com', '', '']]);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $spreadsheet->getActiveSheet()->setCellValue('A2003', 'kurs-hidden');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        // The sheet claims to end at row 2; the stray record is still found.
        $this->rewrite($path, fn(string $xml): string => preg_replace(
            '~<dimension ref="[^"]*"/>~',
            '<dimension ref="A1:H2"/>',
            $xml
        ));
        $zip = new \ZipArchive();
        $zip->open($path);
        $this->assertStringContainsString('<dimension ref="A1:H2"/>', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $this->assertSame('batchimportoutofwindow', $this->refusal($path)->errorcode);
    }

    public function test_a_highly_compressed_package_is_refused_before_loading(): void {
        $this->resetAfterTest();
        $path = $this->workbook([['kurs-a', 'pw', 'A', 'A', 'a@example.com', '', '']]);
        // 17 MiB of whitespace inside the sheet compresses to a few KiB: far below the upload limit.
        $padding = str_repeat(' ', 17 * 1048576);
        $this->rewrite($path, fn(string $xml): string => str_replace('<sheetData>', '<sheetData>' . $padding, $xml));
        $this->assertLessThan(batch_import::MAX_IMPORT_BYTES, filesize($path));

        $memory = memory_get_usage();
        $this->assertSame('batchimportcomplex', $this->refusal($path)->errorcode);
        $this->assertLessThan(64 * 1048576, memory_get_usage() - $memory);
    }

    public function test_too_many_cells_are_refused(): void {
        $this->resetAfterTest();
        $path = $this->workbook([['kurs-a', 'pw', 'A', 'A', 'a@example.com', '', '']]);
        // Empty styled cells cost nothing to store, but every one of them costs the reader memory.
        $cells = '';
        for ($r = 3; $r <= 1002; $r++) {
            $cells .= "<row r=\"$r\">";
            for ($c = 0; $c < 41; $c++) {
                $cells .= '<c r="' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . $r . '"/>';
            }
            $cells .= '</row>';
        }
        $this->rewrite($path, fn(string $xml): string => str_replace('</sheetData>', $cells . '</sheetData>', $xml));

        $this->assertSame('batchimportcomplex', $this->refusal($path)->errorcode);
    }

    public function test_a_second_worksheet_is_refused(): void {
        $this->resetAfterTest();
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('H1', batch_import::SCHEMA_VERSION);
        $spreadsheet->getActiveSheet()->setCellValue('A2', 'kurs-a');
        $spreadsheet->createSheet()->setCellValue('A2', 'kurs-elsewhere');
        $path = make_request_directory() . '/two.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $this->assertSame('batchimportbadschema', $this->refusal($path)->errorcode);
    }

    public function test_a_refused_file_changes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        if (!class_exists('\\enrol_flexaccess\\local\\enrol_service')) {
            $this->markTestSkipped('enrol_flexaccess is not installed.');
        }
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $batchid = \tool_flexaccess\local\batch::create('Import', (int) $course->id, false, 2, 'kurs', 10)['batchid'];
        $before = [
            'users' => $DB->get_records('user', null, 'id', 'id, username, email, auth, timemodified'),
            'batch' => $DB->get_record('tool_flexaccess_batch', ['id' => $batchid]),
            'members' => $DB->get_records('tool_flexaccess_batch_member', ['batchid' => $batchid]),
            'tasks' => $DB->count_records('task_adhoc'),
        ];
        $rows = [];
        foreach ($before['members'] as $member) {
            $rows[] = [$DB->get_field('user', 'username', ['id' => $member->userid]), '', 'New', 'Name', 'n@example.com', '', ''];
        }
        $path = $this->workbook($rows);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $spreadsheet->getActiveSheet()->setCellValue('A50000', 'kurs-lost');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $this->refusal($path);

        $this->assertEquals($before, [
            'users' => $DB->get_records('user', null, 'id', 'id, username, email, auth, timemodified'),
            'batch' => $DB->get_record('tool_flexaccess_batch', ['id' => $batchid]),
            'members' => $DB->get_records('tool_flexaccess_batch_member', ['batchid' => $batchid]),
            'tasks' => $DB->count_records('task_adhoc'),
        ]);
    }
}
