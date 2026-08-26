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

use tool_flexaccess\local\batch;

/**
 * P0-1: a personalised/converted batch member must never have its password rotated by a batch
 * credential re-issue (credential-takeover protection).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
final class batch_credential_lifecycle_test extends \advanced_testcase {
    public function test_reissue_skips_converted_member_and_keeps_their_password(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('List', (int) $course->id, false, 2, 'kurs');
        $batchid = (int) $result['batchid'];
        $members = array_values(batch::members($batchid));

        // Simulate one member being personalised/converted to a permanent account.
        $convertedid = (int) $members[0]->userid;
        \auth_flexaccess\api::admin_convert($convertedid, 'real.person@example.com', 'Real', 'Person');
        $DB->set_field('tool_flexaccess_batch_member', 'converted', 1, ['userid' => $convertedid]);
        $hashbefore = $DB->get_field('user', 'password', ['id' => $convertedid]);

        $credentials = batch::reset_credentials($batchid, 10);

        // The converted account keeps its password and is absent from the re-issued set.
        $this->assertSame($hashbefore, $DB->get_field('user', 'password', ['id' => $convertedid]));
        $convertedusername = $DB->get_field('tool_flexaccess_batch_member', 'username', ['userid' => $convertedid]);
        $this->assertArrayNotHasKey($convertedusername, $credentials);
        // The still-batch-managed member is re-issued.
        $this->assertCount(1, $credentials);
    }

    public function test_set_account_password_refuses_personalised_account(): void {
        $this->resetAfterTest();
        // The refusal lives in auth_flexaccess. A co-installed auth sibling that predates the
        // hardening would legitimately still return true here, so skip instead of failing.
        if ((int) get_config('auth_flexaccess', 'version') < 2026082415) {
            $this->markTestSkipped('auth_flexaccess predates the set_account_password() hardening.');
        }
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('List', (int) $course->id, false, 1, 'kurs');
        $member = array_values(batch::members((int) $result['batchid']))[0];
        $userid = (int) $member->userid;

        // Placeholder batch account: password reset is allowed.
        $this->assertTrue(\auth_flexaccess\api::set_account_password($userid, 'Fresh-Pass!1'));

        // After personalisation the same API must refuse.
        \auth_flexaccess\api::admin_convert($userid, 'real@example.com', 'Real', 'Person');
        $this->assertFalse(\auth_flexaccess\api::set_account_password($userid, 'Another-Pass!2'));
    }
}
