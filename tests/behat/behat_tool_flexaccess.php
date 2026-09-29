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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for tool_flexaccess: frozen accounts for the recovery and suspension-origin pages.
 *
 * @package    tool_flexaccess
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_flexaccess extends behat_base {
    /**
     * Creates a temporary FlexAccess visitor in a course whose account has expired (FlexAccess lock).
     *
     * @Given a frozen FlexAccess account :fullname exists in course :coursefullname
     * @param string $fullname "Firstname Lastname" of the visitor.
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function a_frozen_flexaccess_account_exists_in_course(string $fullname, string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        set_config('enrol_plugins_enabled', implode(',', array_unique(array_merge(
            explode(',', (string) get_config('core', 'enrol_plugins_enabled')),
            ['flexaccess']
        ))));
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance($courseid);
        $userid = (int) \enrol_flexaccess\local\enrol_service::reserve_and_enrol(
            $enrolid,
            fn(): int => \auth_flexaccess\api::create_temporary_user(time() + 3600, $courseid),
            time()
        )->userid;
        [$first, $last] = array_pad(explode(' ', $fullname, 2), 2, '');
        $DB->update_record('user', (object) ['id' => $userid, 'firstname' => $first, 'lastname' => $last]);
        $DB->set_field('auth_flexaccess_account', 'timeexpires', time() - 1, ['userid' => $userid]);
        \auth_flexaccess\local\account_service::expire_due();
    }

    /**
     * Turns the suspension of a FlexAccess account into one of unknown origin (legacy data).
     *
     * @Given the suspension of the FlexAccess account :fullname has an unknown origin
     * @param string $fullname "Firstname Lastname".
     * @return void
     */
    public function the_suspension_has_an_unknown_origin(string $fullname): void {
        global $DB;
        [$first, $last] = array_pad(explode(' ', $fullname, 2), 2, '');
        $userid = (int) $DB->get_field('user', 'id', ['firstname' => $first, 'lastname' => $last], MUST_EXIST);
        $DB->set_field('auth_flexaccess_account', 'lockedby', null, ['userid' => $userid]);
        \tool_flexaccess\local\reconciliation::reconcile_user(
            $userid,
            \tool_flexaccess\local\reconciliation::MODE_REPAIR,
            \tool_flexaccess\local\reconciliation::SOURCE_TASK
        );
    }

    /**
     * Opens the course-level FlexAccess user view.
     *
     * @When I open the FlexAccess users of course :coursefullname
     * @param string $coursefullname Full name of an existing course.
     * @return void
     */
    public function i_open_the_flexaccess_users_of_course(string $coursefullname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['fullname' => $coursefullname], MUST_EXIST);
        $this->execute('behat_general::i_visit', [new \moodle_url('/admin/tool/flexaccess/course.php', ['courseid' => $courseid])]);
    }
}
