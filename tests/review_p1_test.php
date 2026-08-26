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
use tool_flexaccess\local\invitation;

/**
 * Review P1 fixes: granular batch capabilities and honest revoke reporting.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
final class review_p1_test extends \advanced_testcase {
    public function test_granular_batch_capabilities_gate_actions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;

        // A plain user without any batch capability is refused each granular action.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        foreach (['require_create', 'require_issue', 'require_convert'] as $method) {
            try {
                batch::$method($courseid);
                $this->fail("$method should have thrown for a user without capability.");
            } catch (\required_capability_exception $e) {
                $this->assertInstanceOf(\required_capability_exception::class, $e);
            }
        }

        // An admin (holds every capability) passes each granular action.
        $this->setAdminUser();
        batch::require_create($courseid);
        batch::require_issue($courseid);
        batch::require_convert($courseid);
        $this->assertTrue(batch::can_create($courseid));
    }

    public function test_revoke_reports_whether_it_revoked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $id = invitation::create((int) $course->id, 'person@example.com', 0, null, time());

        // A pending invitation is revoked and reports success.
        $this->assertTrue(invitation::revoke($id));
        $this->assertSame(invitation::STATUS_REVOKED, invitation::get($id)->status);

        // Revoking again (already revoked) reports failure instead of a misleading success.
        $this->assertFalse(invitation::revoke($id));
    }
}
