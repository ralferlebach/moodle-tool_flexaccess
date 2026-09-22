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

use tool_flexaccess\local\navigation;

/**
 * Tests for the central FlexAccess tab structure (issues tool#4, tool#5).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\navigation
 */
final class navigation_test extends \advanced_testcase {
    /**
     * An administrator sees the task-oriented main tabs with the grouped sub-tabs.
     *
     * @return void
     */
    public function test_admin_sees_all_tabs_grouped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $tabs = navigation::system_tabs();
        $this->assertSame(
            [navigation::OVERVIEW, navigation::USERS, navigation::ACCESSLISTS, navigation::OUTREACH, navigation::POLICIES,
                navigation::STATUS],
            array_keys($tabs)
        );
        $this->assertSame(['invitations', 'campaigns'], array_keys($tabs[navigation::OUTREACH]['subtabs']));
        $this->assertSame(['policyoverview', 'managepolicies'], array_keys($tabs[navigation::POLICIES]['subtabs']));
        $this->assertSame(['systemcheck', 'mailqueue'], array_keys($tabs[navigation::STATUS]['subtabs']));
        // Existing endpoints stay the targets, so deep links keep working.
        $this->assertStringEndsWith('/admin/tool/flexaccess/accounts.php', $tabs[navigation::USERS]['url']->out_omit_querystring());
        // Exactly one tab is selected; its main tab is activated so the group row is shown with it.
        $tree = navigation::tree($tabs, navigation::STATUS, 'mailqueue');
        $selected = [];
        $activated = [];
        foreach ($tree->subtree as $main) {
            if ($main->activated || $main->selected) {
                $activated[] = $main->id;
            }
            foreach ($main->subtree as $subtab) {
                if ($subtab->selected) {
                    $selected[] = $subtab->id;
                }
            }
        }
        $this->assertSame(['mailqueue'], $selected);
        $this->assertSame([navigation::STATUS], $activated);
        $html = navigation::render_system(navigation::STATUS, 'mailqueue');
        $this->assertStringContainsString(get_string('mailqueue', 'tool_flexaccess'), $html);
    }

    /**
     * Tabs are filtered by capability; a user with nothing gets no bar.
     *
     * @return void
     */
    public function test_tabs_respect_capabilities(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/flexaccess:viewaccounts', CAP_ALLOW, $roleid, \context_system::instance()->id);
        assign_capability('tool/flexaccess:managemailqueue', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $tabs = navigation::system_tabs();
        $this->assertSame([navigation::USERS, navigation::STATUS], array_keys($tabs));
        // Only the permitted sub-page of the status group, and the main tab points to it.
        $this->assertSame(['mailqueue'], array_keys($tabs[navigation::STATUS]['subtabs']));
        $this->assertStringEndsWith('mailqueue.php', $tabs[navigation::STATUS]['url']->out_omit_querystring());

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], navigation::system_tabs());
        $this->assertSame('', navigation::render_system(navigation::OVERVIEW));
    }

    /**
     * The course scope offers users, access lists and restrictions according to course capabilities.
     *
     * @return void
     */
    public function test_course_tabs(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $tabs = navigation::course_tabs((int) $course->id);
        $this->assertArrayHasKey(navigation::USERS, $tabs);
        $this->assertArrayHasKey(navigation::ACCESSLISTS, $tabs);
        $this->assertStringContainsString('courseid=' . $course->id, $tabs[navigation::USERS]['url']->out(false));
        $this->setUser($student);
        $this->assertSame([], navigation::course_tabs((int) $course->id));
    }

    /**
     * The course navigation offers one FlexAccess entry under "More" for permitted users.
     *
     * @return void
     */
    public function test_course_navigation_entry(): void {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/flexaccess/lib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $root = new \navigation_node(['text' => 'root', 'key' => 'root']);
        tool_flexaccess_extend_navigation_course($root, $course, \context_course::instance((int) $course->id));
        $this->assertNotFalse($root->get('flexaccess'));
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $root = new \navigation_node(['text' => 'root', 'key' => 'root']);
        tool_flexaccess_extend_navigation_course($root, $course, \context_course::instance((int) $course->id));
        $this->assertFalse($root->get('flexaccess'));
    }
}
