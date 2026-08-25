<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;
use tool_flexaccess\local\batch;
use tool_flexaccess\local\batch_import;

/**
 * Tests for the batch round-trip conversion (campaign, part 2).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_flexaccess\local\batch_import::class)]
final class batch_convert_test extends \advanced_testcase {
    /**
     * Skip when the sibling plugins are not installed.
     *
     * @return void
     */
    private function require_siblings(): void {
        if (!class_exists('\auth_flexaccess\api') || !class_exists('\enrol_flexaccess\local\enrol_service')) {
            $this->markTestSkipped('auth_flexaccess/enrol_flexaccess are not installed.');
        }
    }

    /**
     * Build rows for a batch's members with personalised data.
     *
     * @param int $batchid Batch id.
     * @param string $newusername Optional explicit new username for every row.
     * @return array<int,array<string,string>>
     */
    private function rows_for(int $batchid, string $newusername = ''): array {
        $rows = [];
        $i = 0;
        foreach (batch::members($batchid) as $m) {
            $rows[] = [
                'username' => $m->username,
                'firstname' => 'Max' . $i,
                'lastname' => 'Muster' . $i,
                'email' => 'person' . $i . '@example.com',
                'newusername' => $newusername,
            ];
            $i++;
        }
        return $rows;
    }

    /**
     * A temporary batch converts to permanent authenticated accounts with the email as username.
     *
     * @return void
     */
    public function test_convert_email_rule(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();

        $result = batch::create('Convert', (int) $course->id, false, 3, 'kurs', 10);
        $rows = $this->rows_for($result['batchid']);
        $summary = batch_import::convert($result['batchid'], $rows, batch_import::RULE_EMAIL);

        $this->assertSame(3, $summary['converted']);
        $this->assertSame(0, $summary['skipped']);
        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['email' => $row['email']], '*', MUST_EXIST);
            $this->assertSame(
                \auth_flexaccess\local\account_type::AUTHENTICATED_USER,
                \auth_flexaccess\api::classify_user((int) $user->id)
            );
            $this->assertSame($row['email'], $user->username);
            $this->assertSame($row['firstname'], $user->firstname);
            $this->assertEquals(0, (int) $user->emailstop);
        }
    }

    /**
     * The "keep" rule preserves the original generated username after conversion.
     *
     * @return void
     */
    public function test_convert_keep_rule(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();

        $result = batch::create('Keep', (int) $course->id, false, 2, 'kurs', 10);
        $rows = $this->rows_for($result['batchid']);
        $originals = array_column($rows, 'username');
        $summary = batch_import::convert($result['batchid'], $rows, batch_import::RULE_KEEP);

        $this->assertSame(2, $summary['converted']);
        foreach ($rows as $i => $row) {
            $user = $DB->get_record('user', ['email' => $row['email']], '*', MUST_EXIST);
            $this->assertSame($originals[$i], $user->username);
        }
    }

    /**
     * An explicit new-username value overrides the chosen rule.
     *
     * @return void
     */
    public function test_explicit_newusername_wins(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();

        $result = batch::create('Explicit', (int) $course->id, false, 1, 'kurs', 10);
        $rows = $this->rows_for($result['batchid'], 'chosen-name');
        // Rule says email, but the explicit column must win.
        $summary = batch_import::convert($result['batchid'], $rows, batch_import::RULE_EMAIL);

        $this->assertSame(1, $summary['converted']);
        $user = $DB->get_record('user', ['email' => $rows[0]['email']], '*', MUST_EXIST);
        $this->assertSame('chosen-name', $user->username);
    }

    /**
     * A row with no email is skipped with an error and leaves the account unconverted.
     *
     * @return void
     */
    public function test_missing_email_skipped(): void {
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();

        $result = batch::create('Skip', (int) $course->id, false, 1, 'kurs', 10);
        $rows = $this->rows_for($result['batchid']);
        $rows[0]['email'] = '';
        $summary = batch_import::convert($result['batchid'], $rows, batch_import::RULE_EMAIL);

        $this->assertSame(0, $summary['converted']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertNotEmpty($summary['errors']);
    }

    /**
     * parse() reads usernames, personal fields and the optional new username from an .xlsx file.
     *
     * @return void
     */
    public function test_parse_roundtrip(): void {
        $this->resetAfterTest();
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['User', 'Pass', 'First', 'Last', 'Email', 'Profile', 'NewUser']], null, 'A1');
        $sheet->fromArray([['Kurs-ABC', 'pw', 'Max', 'Muster', 'Max@Example.com', '', 'MaxM']], null, 'A2');
        $sheet->fromArray([['', '', '', '', '', '', '']], null, 'A3');
        $path = make_request_directory() . '/import.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $rows = batch_import::parse($path);
        $this->assertCount(1, $rows);
        $this->assertSame('kurs-abc', $rows[0]['username']);
        $this->assertSame('Max', $rows[0]['firstname']);
        $this->assertSame('max@example.com', $rows[0]['email']);
        $this->assertSame('maxm', $rows[0]['newusername']);
    }
}
