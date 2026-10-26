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

use tool_flexaccess\local\batch;
use tool_flexaccess\local\credential_issue;

/**
 * Issuing a credential package is complete, consistent and all-or-nothing (tool_flexaccess #15).
 *
 * Every failure - a member that changed, a password that cannot be changed, a defective file, a
 * failed archive, a repeated or concurrent request - leaves every password and the batch unchanged.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\credential_issue
 * @covers \tool_flexaccess\local\batch::reset_credentials
 */
final class credential_issue_test extends \advanced_testcase {
    /** @var int Batch under test. */
    private int $batchid;

    /**
     * A complete batch of three temporary accounts.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\auth_flexaccess\api') || !class_exists('\enrol_flexaccess\local\enrol_service')) {
            $this->markTestSkipped('auth_flexaccess/enrol_flexaccess are not installed.');
        }
        $this->resetAfterTest();
        // The issue rolls back its own transaction on failure.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $this->batchid = (int) batch::create('Issue', (int) $course->id, false, 3, 'kurs', 10)['batchid'];
    }

    /**
     * Password hashes of the members and the issue time: everything an issue may change.
     *
     * @return array
     */
    private function state(): array {
        global $DB;
        $members = batch::members($this->batchid);
        $hashes = [];
        foreach ($members as $member) {
            $hashes[$member->username] = $DB->get_field('user', 'password', ['id' => $member->userid]);
        }
        return [
            'hashes' => $hashes,
            'timeissued' => (int) $DB->get_field('tool_flexaccess_batch', 'timeissued', ['id' => $this->batchid]),
            'events' => $DB->count_records('logstore_standard_log', [
                'eventname' => '\tool_flexaccess\event\batch_credentials_issued',
            ]),
        ];
    }

    /**
     * Issue with the given (possibly fault-injecting) issuer.
     *
     * @param credential_issue $issuer Issuer.
     * @param string|null $token Confirmed token (default: the current plan).
     * @param bool $mayreissue Whether a reissue is allowed.
     * @return array The package.
     */
    private function issue(credential_issue $issuer, ?string $token = null, bool $mayreissue = true): array {
        return $issuer->issue(
            $this->batchid,
            $token ?? $issuer->plan($this->batchid)->token,
            $mayreissue,
            make_request_directory(),
            'https://example.com/course/view.php?id=1'
        );
    }

    /**
     * Assert that a failing issue is refused with the given error and changes nothing.
     *
     * @param string $errorcode Expected error.
     * @param callable $issue Performs the issue.
     */
    private function assert_refused_without_change(string $errorcode, callable $issue): void {
        $before = $this->state();
        try {
            $issue();
            $this->fail("The issue must be refused ($errorcode).");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
        $this->assertSame($before, $this->state());
    }

    /**
     * Text of a PDF document.
     *
     * @param string $content PDF.
     * @return string
     */
    private function pdftext(string $content): string {
        $path = make_request_directory() . '/doc.pdf';
        file_put_contents($path, $content);
        exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null', $lines, $exit);
        $this->assertSame(0, $exit);
        return implode("\n", $lines);
    }

    public function test_the_package_holds_one_complete_consistent_credential_set(): void {
        global $DB;
        $before = $this->state();
        $package = $this->issue(new credential_issue());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($package['zip']));
        $this->assertSame(3, $zip->numFiles);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        sort($names);
        $this->assertSame(['Issue-kaertchen.pdf', 'Issue-liste.pdf', 'Issue.xlsx'], $names);

        // The spreadsheet holds exactly the members, each with a password that is now valid.
        $xlsx = make_request_directory() . '/issue.xlsx';
        file_put_contents($xlsx, $zip->getFromName('Issue.xlsx'));
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx)->getActiveSheet();
        $credentials = [];
        for ($r = 2; $sheet->getCell("A$r")->getValue() !== null; $r++) {
            $credentials[(string) $sheet->getCell("A$r")->getValue()] = (string) $sheet->getCell("B$r")->getValue();
        }
        $this->assertSame(array_keys($before['hashes']), array_keys($credentials));
        foreach ($credentials as $username => $password) {
            $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
            $this->assertTrue(validate_internal_user_password($user, $password), $username);
        }

        // Both PDFs carry the same set.
        if (!is_executable('/usr/bin/pdftotext')) {
            $this->markTestIncomplete('pdftotext is not available; the PDF content was not compared.');
        }
        foreach (['Issue-liste.pdf', 'Issue-kaertchen.pdf'] as $pdf) {
            $text = $this->pdftext($zip->getFromName($pdf));
            foreach ($credentials as $username => $password) {
                $this->assertStringContainsString($username, $text, $pdf);
                $this->assertStringContainsString($password, $text, $pdf);
            }
        }
        $zip->close();

        // The issue is recorded and audited; no credential is stored anywhere.
        $after = $this->state();
        $this->assertGreaterThan(0, $after['timeissued']);
        $this->assertSame($before['events'] + 1, $after['events']);
        $event = $DB->get_record('logstore_standard_log', ['eventname' => '\tool_flexaccess\event\batch_credentials_issued']);
        foreach ($credentials as $password) {
            $this->assertStringNotContainsString($password, $event->other);
        }
    }

    public function test_a_member_that_changed_after_the_confirmation_refuses_the_issue(): void {
        global $DB;
        $issuer = new credential_issue();
        $token = $issuer->plan($this->batchid)->token;
        $member = array_values(batch::members($this->batchid))[1];
        delete_user($DB->get_record('user', ['id' => $member->userid]));

        $before = $this->state();
        try {
            $this->issue($issuer, $token);
            $this->fail('A stale confirmation must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('batchissuestale', $e->errorcode);
        }
        $this->assertSame($before, $this->state());

        // Confirmed again, the deleted account is named and left out, never dropped silently.
        $plan = $issuer->plan($this->batchid);
        $this->assertSame([$member->username], $plan->unavailable);
        $this->assertCount(2, $plan->issue);
        $this->assertSame(2, $this->issue($issuer, $plan->token)['count']);
        $other = json_decode($DB->get_field('logstore_standard_log', 'other', [
            'eventname' => '\tool_flexaccess\event\batch_credentials_issued',
        ]), true);
        $this->assertSame([$member->username], $other['unavailable']);
    }

    public function test_a_failed_password_change_rolls_back_every_password(): void {
        $issuer = new class extends credential_issue {
            /** @var int Calls so far. */
            public int $calls = 0;

            /**
             * The first change succeeds, the second fails.
             *
             * @param int $userid Account.
             * @param string $password Password.
             * @return bool
             */
            protected function set_password(int $userid, string $password): bool {
                // The first change succeeds, the second fails.
                return ++$this->calls === 1 ? parent::set_password($userid, $password) : false;
            }
        };
        $this->assert_refused_without_change('batchissuepasswordfailed', fn() => $this->issue($issuer));
        $this->assertSame(2, $issuer->calls);
    }

    /**
     * Defective exporters: an empty document, a truncated document, a spreadsheet that lacks a member.
     *
     * @return array
     */
    public static function defective_exporters(): array {
        return [
            'empty list' => ['list', fn(string $content): string => ''],
            'truncated cards' => ['cards', fn(string $content): string => substr($content, 0, (int) (strlen($content) / 2))],
            'spreadsheet without a member' => ['excel', null],
        ];
    }

    /**
     * A defective file is detected before any password changes.
     *
     * @dataProvider defective_exporters
     * @param string $kind Defective file.
     * @param callable|null $damage How it is damaged (null: built from a credential set that lacks a member).
     */
    public function test_a_defective_file_changes_nothing(string $kind, ?callable $damage): void {
        $issuer = new class ($kind, $damage) extends credential_issue {
            /**
             * Fault injection.
             *
             * @param string $kind File to damage.
             * @param callable|null $damage Damage.
             */
            public function __construct(
                /** @var string File to damage. */
                private string $kind,
                /** @var callable|null Damage. */
                private $damage
            ) {
            }

            /**
             * Build one file, damaged if it is the chosen one.
             *
             * @param string $kind File.
             * @param \stdClass $batch Batch.
             * @param array $credentials Credentials.
             * @param string $courseurl Course URL.
             * @return string
             */
            protected function render(string $kind, \stdClass $batch, array $credentials, string $courseurl): string {
                if ($kind !== $this->kind) {
                    return parent::render($kind, $batch, $credentials, $courseurl);
                }
                if ($this->damage === null) {
                    array_pop($credentials);
                    return parent::render($kind, $batch, $credentials, $courseurl);
                }
                return ($this->damage)(parent::render($kind, $batch, $credentials, $courseurl));
            }
        };
        $this->assert_refused_without_change('batchissuefailed', fn() => $this->issue($issuer));
    }

    public function test_a_failed_write_or_archive_changes_nothing(): void {
        $nowrite = new class extends credential_issue {
            /**
             * The spreadsheet cannot be written.
             *
             * @param string $path Path.
             * @param string $content Content.
             * @return int|false
             */
            protected function write(string $path, string $content): int|false {
                return str_ends_with($path, '.xlsx') ? false : parent::write($path, $content);
            }
        };
        $this->assert_refused_without_change('batchissuefailed', fn() => $this->issue($nowrite));

        $noarchive = new class extends credential_issue {
            /**
             * Packing fails.
             *
             * @param array $paths Files.
             * @param string $zippath Archive.
             * @return bool
             */
            protected function archive(array $paths, string $zippath): bool {
                return false;
            }
        };
        $this->assert_refused_without_change('batchissuefailed', fn() => $this->issue($noarchive));

        // An archive that reports success but lacks a file is caught as well.
        $partial = new class extends credential_issue {
            /**
             * Packing reports success but leaves a file out.
             *
             * @param array $paths Files.
             * @param string $zippath Archive.
             * @return bool
             */
            protected function archive(array $paths, string $zippath): bool {
                array_pop($paths);
                return parent::archive($paths, $zippath);
            }
        };
        $this->assert_refused_without_change('batchissuefailed', fn() => $this->issue($partial));
    }

    public function test_a_repeated_submission_is_refused(): void {
        $issuer = new credential_issue();
        $token = $issuer->plan($this->batchid)->token;
        $this->issue($issuer, $token);
        // The same confirmation once more (double click, reload, replay): refused even for an administrator.
        $this->assert_refused_without_change('batchissuestale', fn() => $this->issue($issuer, $token));
    }

    public function test_a_concurrent_issue_is_serialised(): void {
        global $CFG;
        // The native PostgreSQL and MySQL locks are re-entrant within one connection, so a second request is
        // emulated with the record lock factory, whose locks also hold against the same process.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $lock = \core\lock\lock_config::get_lock_factory(credential_issue::LOCK_TYPE)->get_lock('batch_' . $this->batchid, 0);
        $this->assertNotFalse($lock);
        try {
            $this->assert_refused_without_change('batchissuebusy', fn() => $this->issue(new credential_issue()));
        } finally {
            $lock->release();
        }
        $this->assertSame(3, $this->issue(new credential_issue())['count']);
    }

    public function test_a_repeated_download_needs_an_audited_reissue(): void {
        global $DB;
        $issuer = new credential_issue();
        $this->issue($issuer);
        $first = $this->state();

        // Without the reissue right, a second package is refused.
        $this->assert_refused_without_change('batchalreadyissued', fn() => $this->issue($issuer, null, false));

        // A confirmed reissue rotates every password again and is logged as a reissue.
        $this->waitForSecond();
        $this->assertSame(3, $this->issue($issuer)['count']);
        $second = $this->state();
        foreach ($first['hashes'] as $username => $hash) {
            $this->assertNotSame($hash, $second['hashes'][$username]);
        }
        $this->assertSame($first['events'] + 1, $second['events']);
        $last = $DB->get_records('logstore_standard_log', [
            'eventname' => '\tool_flexaccess\event\batch_credentials_issued',
        ], 'id DESC', 'id, other', 0, 1);
        $this->assertTrue(json_decode(reset($last)->other, true)['reissue']);
    }

    public function test_reset_credentials_is_all_or_nothing(): void {
        global $DB;
        $member = array_values(batch::members($this->batchid))[2];
        // Changed outside the batch without being marked personalised: refused, not skipped.
        $DB->set_field('user', 'auth', 'manual', ['id' => $member->userid]);
        $this->assert_refused_without_change('batchissuepasswordfailed', fn() => batch::reset_credentials($this->batchid));
    }
}
