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
 * Who may reach the access-list functions, and which roles hold the capabilities by default.
 *
 * Two things have to hold and are easy to break independently: every guarded function must refuse
 * a role that lacks the capability, and no capability may be handed to a role that should not have
 * it. This class pins down both.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
final class access_permissions_test extends \advanced_testcase {
    /**
     * Create a course and one enrolled user per role.
     *
     * @return array{0:\stdClass,1:array<string,\stdClass>}
     */
    private function course_with_roles(): array {
        $course = $this->getDataGenerator()->create_course();
        $users = [];
        foreach (['student', 'teacher', 'editingteacher', 'manager'] as $shortname) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, $shortname);
            $users[$shortname] = $user;
        }
        // A user with no relationship to the course at all.
        $users['outsider'] = $this->getDataGenerator()->create_user();
        return [$course, $users];
    }

    public function test_course_page_is_refused_to_roles_without_a_capability(): void {
        $this->resetAfterTest();
        [$course, $users] = $this->course_with_roles();
        $courseid = (int) $course->id;

        // Expected access to the course access-list page, per role.
        $expected = [
            'student' => false,
            'teacher' => false,
            'editingteacher' => true,
            'manager' => true,
            'outsider' => false,
        ];
        foreach ($expected as $role => $allowed) {
            $this->setUser($users[$role]);
            $this->assertSame(
                $allowed,
                batch::can_open_course_page($courseid),
                "Unexpected page access for role '$role'."
            );
            if ($allowed) {
                continue;
            }
            try {
                batch::require_course_page($courseid);
                $this->fail("Role '$role' was allowed to open the access-list page.");
            } catch (\required_capability_exception $e) {
                $this->assertInstanceOf(\required_capability_exception::class, $e);
            }
        }
    }

    public function test_each_operation_is_refused_to_roles_without_its_capability(): void {
        $this->resetAfterTest();
        [$course, $users] = $this->course_with_roles();
        $courseid = (int) $course->id;

        // Only a manager may provision, issue credentials or convert accounts. An editing teacher
        // may look and request, nothing more.
        $matrix = [
            'student' => ['view' => false, 'create' => false, 'request' => false],
            'teacher' => ['view' => false, 'create' => false, 'request' => false],
            'editingteacher' => ['view' => true, 'create' => false, 'request' => true],
            'manager' => ['view' => true, 'create' => true, 'request' => true],
            'outsider' => ['view' => false, 'create' => false, 'request' => false],
        ];
        foreach ($matrix as $role => $rights) {
            $this->setUser($users[$role]);
            $this->assertSame($rights['view'], batch::can_view($courseid), "view / $role");
            $this->assertSame($rights['create'], batch::can_create($courseid), "create / $role");
            $this->assertSame($rights['request'], batch::can_request($courseid), "request / $role");

            foreach (['require_create' => 'create', 'require_issue' => null, 'require_convert' => null] as $method => $key) {
                $allowed = $key === null ? ($role === 'manager') : $rights[$key];
                if ($allowed) {
                    batch::$method($courseid);
                    continue;
                }
                try {
                    batch::$method($courseid);
                    $this->fail("Role '$role' passed $method().");
                } catch (\required_capability_exception $e) {
                    $this->assertInstanceOf(\required_capability_exception::class, $e);
                }
            }
        }
    }

    public function test_no_capability_is_granted_to_an_unintended_role(): void {
        global $DB;
        $this->resetAfterTest();

        // The archetypes declared in db/access.php. Any capability reaching a role beyond these -
        // above all a student - is a privilege escalation and must fail this test.
        $expected = [
            'tool/flexaccess:viewdashboard' => ['manager'],
            'tool/flexaccess:viewaccounts' => ['manager'],
            'tool/flexaccess:convertaccounts' => ['manager'],
            'tool/flexaccess:managemailqueue' => ['manager'],
            'tool/flexaccess:viewpolicies' => ['manager'],
            'tool/flexaccess:managepolicies' => ['manager'],
            'tool/flexaccess:managecampaigns' => ['manager'],
            'tool/flexaccess:manageinvitations' => ['manager'],
            'tool/flexaccess:managebatches' => ['manager'],
            'tool/flexaccess:managecoursebatches' => ['manager'],
            'tool/flexaccess:viewcoursebatches' => ['editingteacher', 'manager'],
            'tool/flexaccess:createcoursebatches' => ['manager'],
            'tool/flexaccess:issuebatchcredentials' => ['manager'],
            'tool/flexaccess:convertbatchaccounts' => ['manager'],
            'tool/flexaccess:requestbatches' => ['editingteacher', 'manager'],
        ];

        foreach ($expected as $capability => $roles) {
            $granted = $DB->get_fieldset_sql(
                "SELECT DISTINCT r.archetype
                   FROM {role_capabilities} rc
                   JOIN {role} r ON r.id = rc.roleid
                  WHERE rc.capability = :cap AND rc.permission = :allow AND r.archetype <> ''",
                ['cap' => $capability, 'allow' => CAP_ALLOW]
            );
            sort($granted);
            $wanted = $roles;
            sort($wanted);
            $this->assertSame($wanted, $granted, "Unexpected roles hold $capability.");
        }
    }

    public function test_switching_role_to_student_hides_the_access_lists(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        // A site administrator sees everything, as expected.
        $this->setAdminUser();
        $this->assertTrue(batch::can_open_course_page($courseid));

        // Switching the role to student must show what a student really sees. Moodle suspends the
        // administrator bypass for the switched context, so a check asked in the COURSE context
        // now answers honestly - a check against the system context would not, which is how the
        // Previously the access-list entry stayed visible while previewing a course as a student.
        role_switch($studentrole, \context_course::instance($courseid));

        $this->assertFalse(
            batch::can_open_course_page($courseid),
            'The access-list page is still offered while previewing the course as a student.'
        );
        $this->assertFalse(batch::can_view($courseid));
        $this->assertFalse(batch::can_create($courseid));
        $this->assertFalse(batch::can_request($courseid));
        $this->assertFalse(batch::can_manage($courseid));

        try {
            batch::require_course_page($courseid);
            $this->fail('The access-list page opened while previewing the course as a student.');
        } catch (\required_capability_exception $e) {
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }

        // Leaving the switched role restores the administrator's own access.
        role_switch(0, \context_course::instance($courseid));
        $this->assertTrue(batch::can_open_course_page($courseid));
    }
}
