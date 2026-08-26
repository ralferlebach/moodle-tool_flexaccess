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

use core_privacy\local\request\writer;
use tool_flexaccess\local\batch;
use tool_flexaccess\privacy\provider;

/**
 * Batch data must be reachable by a subject access request and removable by a purge.
 *
 * Batch membership is personal data (it links a person to a generated account), so it has to appear
 * in the contexts, be exported, and disappear when the context's data is deleted.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\privacy\provider
 */
final class privacy_batch_test extends \advanced_testcase {
    public function test_batch_membership_yields_a_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Privacy', (int) $course->id, false, 2, 'kurs');
        $member = array_values(batch::members((int) $result['batchid']))[0];

        // A user whose only tool_flexaccess data is a batch membership must still get a context.
        $contexts = array_map('intval', provider::get_contexts_for_userid((int) $member->userid)->get_contextids());
        $this->assertContains((int) \context_system::instance()->id, $contexts);
    }

    public function test_batch_membership_is_exported(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Privacy', (int) $course->id, false, 1, 'kurs');
        $member = array_values(batch::members((int) $result['batchid']))[0];
        $context = \context_system::instance();

        provider::export_user_data(new \core_privacy\local\request\approved_contextlist(
            \core_user::get_user((int) $member->userid),
            'tool_flexaccess',
            [$context->id]
        ));

        $exported = writer::with_context($context)->get_data(
            ['tool_flexaccess', 'batchmember', (string) $member->id]
        );
        $this->assertNotEmpty($exported);
        $this->assertSame($member->username, $exported->username);
    }

    public function test_deleting_the_context_purges_batch_members(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        batch::create('Privacy', (int) $course->id, false, 3, 'kurs');
        $this->assertGreaterThan(0, $DB->count_records('tool_flexaccess_batch_member'));

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        // Membership rows are entirely personal (user id plus generated username): they must go.
        $this->assertSame(0, $DB->count_records('tool_flexaccess_batch_member'));
    }
}
