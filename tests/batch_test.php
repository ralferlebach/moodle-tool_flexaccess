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
use tool_flexaccess\local\batch_export;

/**
 * Tests for batch provisioning and credential export.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_flexaccess\local\batch::class)]
final class batch_test extends \advanced_testcase {
    /**
     * Skip when the sibling plugins that create/enrol accounts are not installed.
     *
     * @return void
     */
    private function require_siblings(): void {
        if (!class_exists('\auth_flexaccess\api') || !class_exists('\enrol_flexaccess\local\enrol_service')) {
            $this->markTestSkipped('auth_flexaccess/enrol_flexaccess are not installed.');
        }
    }

    /**
     * create() makes the requested number of enrolled accounts and records membership.
     *
     * @return void
     */
    public function test_create_provisions_enrolled_accounts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance((int) $course->id);

        $result = batch::create('Workshop A', (int) $course->id, false, 5, 'kurs', 10);
        $this->assertGreaterThan(0, $result['batchid']);
        $this->assertCount(5, $result['credentials']);

        $batch = batch::get($result['batchid']);
        $this->assertSame(5, (int) $batch->membercount);
        $this->assertCount(5, batch::members($result['batchid']));
        // Every generated account is enrolled in the course.
        $this->assertSame(5, count_enrolled_users($context));

        // Usernames are unique and carry the prefix; passwords are non-trivial.
        $usernames = array_keys($result['credentials']);
        $this->assertSame($usernames, array_unique($usernames));
        foreach ($result['credentials'] as $username => $password) {
            $this->assertStringStartsWith('kurs-', $username);
            $this->assertGreaterThanOrEqual(10, strlen($password));
            $this->assertTrue($DB->record_exists('user', ['username' => $username]));
        }
    }

    /**
     * Permanent batches create authenticated accounts; temporary ones create temporary accounts.
     *
     * @return void
     */
    public function test_account_type(): void {
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();

        $perm = batch::create('Perm', (int) $course->id, true, 2, 'perm', 10);
        foreach (batch::members($perm['batchid']) as $m) {
            $this->assertSame(
                \auth_flexaccess\local\account_type::AUTHENTICATED_USER,
                \auth_flexaccess\api::classify_user((int) $m->userid)
            );
        }
        $temp = batch::create('Temp', (int) $course->id, false, 2, 'temp', 10);
        foreach (batch::members($temp['batchid']) as $m) {
            $this->assertSame(
                \auth_flexaccess\local\account_type::TEMPORARY_USER,
                \auth_flexaccess\api::classify_user((int) $m->userid)
            );
        }
    }

    /**
     * reset_credentials() issues a fresh password per member that actually authenticates.
     *
     * @return void
     */
    public function test_reset_credentials(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();

        $result = batch::create('Reset', (int) $course->id, true, 3, 'kurs', 12);
        $fresh = batch::reset_credentials($result['batchid'], 12);
        $this->assertCount(3, $fresh);

        // The new password validates against the stored hash.
        [$username, $password] = [array_key_first($fresh), reset($fresh)];
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $this->assertTrue(validate_internal_user_password($user, $password));
    }

    /**
     * Each exporter produces non-empty output with the expected file signature.
     *
     * @return void
     */
    public function test_exporters_produce_files(): void {
        $this->resetAfterTest();
        $this->require_siblings();
        $this->setAdminUser();
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Export', (int) $course->id, false, 3, 'kurs', 10);
        $batch = batch::get($result['batchid']);
        $creds = $result['credentials'];

        $xlsx = batch_export::excel($batch, $creds);
        $this->assertStringStartsWith('PK', $xlsx); // XLSX is a zip container.

        $list = batch_export::pdf_list($batch, $creds);
        $this->assertStringStartsWith('%PDF', $list);

        $cards = batch_export::login_cards($batch, $creds, 'https://example.com/course/view.php?id=1');
        $this->assertStringStartsWith('%PDF', $cards);
    }
}
