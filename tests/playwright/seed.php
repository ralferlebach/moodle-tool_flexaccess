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
 * CLI seed for the enrol_flexaccess Playwright tests.
 *
 * Creates a course with an anonymous-temporary FlexAccess instance and prints the base URL, course
 * id and course name as shell "export" lines so the CI runner can source them.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
// Tool plugins live one level deeper (admin/tool/<name>), so the path to config.php differs
// from the other three plugins.
require(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

// A presentable name: the screenshots of a green run are used as illustrations.
$coursename = 'My favourite course';
$category = \core_course_category::get_default();
$course = create_course((object) [
    'fullname' => $coursename,
    // Unique by construction: two seeds started within the same second would otherwise
    // collide on the shortname and the course could not be created.
    'shortname' => uniqid('FAPW', true),
    'category' => $category->id,
    'visible' => 1,
]);

set_config('allowwidening', 1, 'enrol_flexaccess');
$plugin = enrol_get_plugin('flexaccess');
$enrolid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED]);
\enrol_flexaccess\local\instance_config::save($enrolid, [
    'allowtemporary' => 1,
    'allowquick' => 1,
    'maxparticipants' => 0,
]);

// The browser journey cannot read verification email, so persistence is immediate on this test
// site, and the FlexAccess auth method must be enabled so converted accounts can log in again.
set_config('requireemailverification', 0, 'auth_flexaccess');
$enabledauths = get_enabled_auth_plugins();
if (!in_array('flexaccess', $enabledauths, true)) {
    $enabledauths[] = 'flexaccess';
    set_config('auth', implode(',', $enabledauths));
}

// Both halves of FlexAccess must be enabled, as on any site that uses it: entry flows stop before any
// side effect while the enrolment or the authentication plugin is disabled (kill switch, 2026092900).
$enabledenrols = explode(',', (string) get_config('core', 'enrol_plugins_enabled'));
if (!in_array('flexaccess', $enabledenrols, true)) {
    $enabledenrols[] = 'flexaccess';
    set_config('enrol_plugins_enabled', implode(',', array_filter($enabledenrols)));
}
\cache::make('enrol_flexaccess', 'policy')->purge();

echo "export FLEXACCESS_BASE_URL='" . $CFG->wwwroot . "'\n";
echo "export FLEXACCESS_COURSE_ID='" . $course->id . "'\n";
echo "export FLEXACCESS_COURSE_NAME='" . $coursename . "'\n";
// A manager account for the authenticated accessibility checks of the plugin's own administration
// pages. Created on a throwaway CI site only; the specs skip when the variables are absent.
$adminuser = $DB->get_record('user', ['username' => 'flexa11y'], '*', IGNORE_MISSING);
if (!$adminuser) {
    $newuser = (object) [
        'username' => 'flexa11y',
        'password' => 'Flex-A11y-Pass!1',
        'firstname' => 'Access',
        'lastname' => 'Checker',
        'email' => 'flexa11y@example.invalid',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];
    // Moodle 5.3 deprecates user_create_user() in favour of \core\user::create_user().
    $createdid = method_exists(\core\user::class, 'create_user')
        ? \core\user::create_user($newuser, true, false)
        : user_create_user($newuser, true, false);
    $adminuser = \core_user::get_user($createdid);
}
role_assign(
    $DB->get_field('role', 'id', ['shortname' => 'manager']),
    $adminuser->id,
    \context_system::instance()->id
);

// Colour modes (Moodle 5.3+, theme_boost): enabled, and the manager follows the browser's colour scheme,
// so the navigation smoke test can check the FlexAccess pages in light and in dark mode.
if (class_exists('\\theme_boost\\colour_mode')) {
    set_config('enablecolourmodes', 1, 'theme_boost');
    set_user_preference(\theme_boost\colour_mode::PREFERENCE, \theme_boost\colour_mode::AUTO, $adminuser->id);
}

// Deliberately NOT called FLEXACCESS_ADMIN_*: this is a manager, not the site administrator.
// Overriding the admin credentials with it denied the specs access to /admin pages that require
// moodle/site:config.
echo "export FLEXACCESS_MANAGER_USER='flexa11y'\n";
echo "export FLEXACCESS_MANAGER_PASS='Flex-A11y-Pass!1'\n";

// Recovery fixtures (tool_flexaccess #10): three permanent FlexAccess accounts whose FlexAccess course
// access in the recovery course is suspended - one plainly recoverable, one whose Moodle user an
// administrator suspended, and one whose course has its FlexAccess enrolment method disabled.
$recoverycourse = create_course((object) [
    'fullname' => 'Recovery course',
    'shortname' => uniqid('FAPWREC', true),
    'category' => $category->id,
    'visible' => 1,
]);
$blockedcourse = create_course((object) [
    'fullname' => 'Closed course',
    'shortname' => uniqid('FAPWCLOSED', true),
    'category' => $category->id,
    'visible' => 1,
]);
$recoveryenrol = $DB->get_record('enrol', ['id' => $plugin->add_instance($recoverycourse, ['status' => ENROL_INSTANCE_ENABLED])]);
$blockedenrol = $DB->get_record('enrol', ['id' => $plugin->add_instance($blockedcourse, ['status' => ENROL_INSTANCE_ENABLED])]);
$participantroleid = \enrol_flexaccess\local\participant_role::get_id();
$recoverypass = 'Recover-Me-Pass!1';
$recoveryusers = [];
foreach (['ok' => ['Robin', 'Recovers'], 'locked' => ['Lena', 'Locked'], 'closed' => ['Clara', 'Closed']] as $key => $name) {
    $username = 'flexrecover' . $key;
    $existing = $DB->get_record('user', ['username' => $username], 'id', IGNORE_MISSING);
    $userid = $existing ? (int) $existing->id : (int) user_create_user((object) [
        'username' => $username,
        'password' => $recoverypass,
        'auth' => 'flexaccess',
        'firstname' => $name[0],
        'lastname' => $name[1],
        'email' => $username . '@example.invalid',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ], true, false);
    if (!$DB->record_exists('auth_flexaccess_account', ['userid' => $userid])) {
        \auth_flexaccess\local\account_service::create_authenticated(
            $userid,
            \auth_flexaccess\local\account_service::generate_unique_reference()
        );
    }
    // Set explicitly: the journey signs in with it, and user_create_user() leaves it to the auth plugin.
    update_internal_user_password(\core_user::get_user($userid), $recoverypass);
    $instance = $key === 'closed' ? $blockedenrol : $recoveryenrol;
    $plugin->enrol_user($instance, $userid, $participantroleid, 0, 0, ENROL_USER_SUSPENDED);
    $recoveryusers[$key] = $userid;
}
// Suspended by an administrator: FlexAccess must not lift this, and must not report success.
$DB->set_field('user', 'suspended', 1, ['id' => $recoveryusers['locked']]);
$DB->set_field('auth_flexaccess_account', 'lockedby', 'admin', ['userid' => $recoveryusers['locked']]);
// A deliberately disabled enrolment method stays disabled; recovery shows it as the blocker.
$DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $blockedenrol->id]);
\core\session\manager::destroy_user_sessions($recoveryusers['locked']);

echo "export FLEXACCESS_RECOVERY_COURSE_ID='" . $recoverycourse->id . "'\n";
echo "export FLEXACCESS_RECOVERY_CLOSED_COURSE_ID='" . $blockedcourse->id . "'\n";
echo "export FLEXACCESS_RECOVERY_OK_ID='" . $recoveryusers['ok'] . "'\n";
echo "export FLEXACCESS_RECOVERY_LOCKED_ID='" . $recoveryusers['locked'] . "'\n";
echo "export FLEXACCESS_RECOVERY_CLOSED_ID='" . $recoveryusers['closed'] . "'\n";
echo "export FLEXACCESS_RECOVERY_PASS='" . $recoverypass . "'\n";

// Merge review fixture (auth_flexaccess #14): a pair that is only registered - two live accounts, never
// merged by tool_mergeusers - with an LTI binding on the account to be removed. The LTI identities page
// must not offer to move that binding.
$reviewsource = (int) \auth_flexaccess\api::create_temporary_user(time() + DAYSECS);
$DB->set_field('user', 'firstname', 'Unmerged', ['id' => $reviewsource]);
$DB->set_field('user', 'lastname', 'Source', ['id' => $reviewsource]);
$bindingkey = [
    'issuer256' => hash('sha256', 'https://platform.example.invalid'),
    'sub256' => hash('sha256', 'unmerged-1'),
];
if (!$DB->record_exists('auth_lti_linked_login', $bindingkey)) {
    $DB->insert_record('auth_lti_linked_login', (object) [
        'userid' => $reviewsource,
        'issuer' => 'https://platform.example.invalid',
        'issuer256' => hash('sha256', 'https://platform.example.invalid'),
        'sub' => 'unmerged-1',
        'sub256' => hash('sha256', 'unmerged-1'),
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
}
$reviewrow = \auth_flexaccess\local\merge_registry::record($reviewsource, (int) $recoveryusers['ok'], 'admin');
\auth_flexaccess\local\merge_registry::finish($reviewrow, \auth_flexaccess\local\merge_registry::REVIEW, ['sourcebindings'], []);
echo "export FLEXACCESS_MERGE_REVIEW_SOURCE_ID='" . $reviewsource . "'\n";
