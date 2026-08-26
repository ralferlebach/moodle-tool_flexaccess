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
 * Tests for course-scoped access-list batches: management, requests and notifications.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
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

    public function test_manage_is_manager_only_not_teacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($manager);
        $this->assertTrue(batch::can_manage((int) $course->id));
        $this->setUser($teacher);
        $this->assertFalse(batch::can_manage((int) $course->id), 'Editing teachers must not provision directly.');
        $this->setUser($student);
        $this->assertFalse(batch::can_manage((int) $course->id));
    }

    public function test_request_is_available_to_teacher_not_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);
        $this->assertTrue(batch::can_request((int) $course->id));
        $this->setUser($student);
        $this->assertFalse(batch::can_request((int) $course->id));
    }

    public function test_managers_for_course_lists_provisioners_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $recipients = batch::managers_for_course((int) $course->id);
        $this->assertArrayHasKey($manager->id, $recipients);
        $this->assertArrayNotHasKey($teacher->id, $recipients);
    }

    public function test_notify_request_messages_the_provisioners(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $course = $this->getDataGenerator()->create_course();
        $manager = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $sink = $this->redirectMessages();
        $notified = batch::notify_request((int) $course->id, (int) $teacher->id, 12);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(1, $notified);
        $this->assertCount(1, $messages);
        $this->assertSame((int) $manager->id, (int) $messages[0]->useridto);
        $this->assertSame('batchrequest', $messages[0]->eventtype);
    }
}
