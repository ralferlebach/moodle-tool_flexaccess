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

/**
 * Bounded selections really are bounded.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess;

use tool_flexaccess\local\invitation;
use tool_flexaccess\local\reconciliation;

/**
 * Regression: limits were passed to get_fieldset_sql()/get_fieldset_select(), which take none.
 *
 * @package    tool_flexaccess
 * @covers \tool_flexaccess\local\invitation::due_reminders
 * @covers \tool_flexaccess\local\reconciliation::lock_review_userids
 */
final class query_limits_test extends \advanced_testcase {
    /**
     * The reminder task's per-run cap is applied, in id order.
     */
    public function test_due_reminders_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $id = invitation::create((int) $course->id, "g$i@example.com", 0, null, 1000);
            $DB->set_field('tool_flexaccess_invite', 'timesent', 1000, ['id' => $id]);
        }
        $this->assertSame(array_slice($ids, 0, 2), invitation::due_reminders(2000, 2000, 2));
        $this->assertSame($ids, invitation::due_reminders(2000, 2000));
    }

    /**
     * The lock review list honours its limit and keeps its oldest-first order.
     */
    public function test_lock_review_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $users = [];
        foreach ([300, 100, 200] as $time) {
            $userid = (int) $this->getDataGenerator()->create_user()->id;
            $users[$time] = $userid;
            $DB->insert_record('tool_flexaccess_reconcile', (object) [
                'userid' => $userid,
                'code' => reconciliation::LOCK_REVIEWS[0],
                'status' => 'open',
                'timecreated' => $time,
                'timemodified' => $time,
            ]);
        }
        $this->assertSame([$users[100], $users[200]], reconciliation::lock_review_userids(2));
        $this->assertSame([$users[100], $users[200], $users[300]], reconciliation::lock_review_userids());
    }
}
