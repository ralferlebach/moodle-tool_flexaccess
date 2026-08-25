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

use PHPUnit\Framework\Attributes\CoversClass;
use tool_flexaccess\local\batch;

/**
 * Tests for the course-scoped access-list batch management (T2).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_flexaccess\local\batch::class)]
final class coursebatches_test extends \advanced_testcase {
    public function test_for_course_scopes_to_the_course(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();

        batch::create('List A', (int) $c1->id, false, 3, 'kurs');
        batch::create('List B', (int) $c1->id, false, 2, 'kurs');
        batch::create('Other', (int) $c2->id, false, 4, 'kurs');

        $this->assertCount(2, batch::for_course((int) $c1->id));
        $this->assertSame(2, batch::count_for_course((int) $c1->id));
        $this->assertSame(1, batch::count_for_course((int) $c2->id));
        $this->assertSame(0, batch::count_for_course((int) $c1->id + 999));
    }

    public function test_can_manage_editing_teacher_but_not_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);
        $this->assertTrue(batch::can_manage((int) $course->id));

        $this->setUser($student);
        $this->assertFalse(batch::can_manage((int) $course->id));
    }

    public function test_require_manage_throws_for_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        batch::require_manage((int) $course->id);
    }

    public function test_site_manager_can_manage_any_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->assertTrue(batch::can_manage((int) $course->id));
    }
}
