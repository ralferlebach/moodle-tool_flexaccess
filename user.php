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
 * Drill-down into one FlexAccess user: account, credential funnel and course accesses.
 *
 * In the system scope a site administrator sees every FlexAccess course access of the user; in the
 * course scope (courseid set) a course manager sees exactly this course and nothing else.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\navigation;
use tool_flexaccess\local\recovery;
use tool_flexaccess\local\user_presenter;

$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$scope = $courseid > 0 ? $courseid : null;

if ($scope !== null) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('tool/flexaccess:viewcourseusers', $context);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_heading(format_string($course->fullname));
} else {
    require_login();
    $context = context_system::instance();
    require_capability('tool/flexaccess:viewaccounts', $context);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));
}
$pageurl = new moodle_url('/admin/tool/flexaccess/user.php', ['userid' => $userid, 'courseid' => $courseid]);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('userdetails', 'tool_flexaccess'));

$snapshot = recovery::snapshot($userid, $scope);
// In the course scope the user must belong to this course; otherwise nothing is revealed.
if ($snapshot === null || ($scope !== null && !$snapshot->enrolments && !$snapshot->unenrolledcourses)) {
    throw new moodle_exception('usernotfound', 'tool_flexaccess');
}

if ($action !== '') {
    require_sesskey();
    if ($action === 'resendsetpassword') {
        // A credential for a permanent identity: a site-level right, never a course one.
        require_capability('tool/flexaccess:convertaccounts', context_system::instance());
        $status = \auth_flexaccess\api::resend_set_password($userid);
    } else if ($action === 'resendverification') {
        require_capability($scope !== null ? 'tool/flexaccess:recovercourse' : 'tool/flexaccess:recoversystem', $context);
        $status = \auth_flexaccess\api::resend_verification($userid);
    } else {
        throw new moodle_exception('invalidrequest', 'error');
    }
    $ok = in_array($status, ['queued', 'alreadyqueued'], true);
    redirect(
        $pageurl,
        get_string('resendstatus_' . $status, 'tool_flexaccess'),
        null,
        $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('userdetails', 'tool_flexaccess') . ': ' . s($snapshot->fullname));
echo $scope !== null ? navigation::render_course($courseid, navigation::USERS) : navigation::render_system(navigation::USERS);

// Account facts.
$facts = new html_table();
$facts->attributes['class'] = 'generaltable flexaccess-userfacts';
$facts->data = [
    [get_string('accountemail', 'tool_flexaccess'), s($snapshot->email)],
    [get_string('accountreferencelabel', 'tool_flexaccess'), s($snapshot->reference)],
    [get_string('accounttype', 'tool_flexaccess'), \tool_flexaccess\local\account_labels::type($snapshot->accounttype)],
    [get_string('accountstate', 'tool_flexaccess'), \tool_flexaccess\local\account_labels::state($snapshot->accountstate)],
    [get_string('accountexpires', 'tool_flexaccess'),
        $snapshot->timeexpires > 0 ? userdate($snapshot->timeexpires) : get_string('never')],
    [get_string('usersuspended', 'tool_flexaccess'), get_string($snapshot->suspended ? 'yes' : 'no')],
    [get_string('verificationpending', 'tool_flexaccess'), get_string($snapshot->verificationpending ? 'yes' : 'no')],
    [get_string('credentialfunnel', 'tool_flexaccess'), get_string('credential_' . $snapshot->credentialstatus, 'tool_flexaccess')],
];
if ($snapshot->mismatches) {
    $facts->data[] = [
        get_string('inconsistencies', 'tool_flexaccess'),
        implode(', ', array_map(
            static fn(string $c): string => get_string('mismatch_' . $c, 'auth_flexaccess'),
            $snapshot->mismatches
        )),
    ];
}
echo html_writer::table($facts);

// Course accesses (drill-down), in the same presentation as the lists.
echo $OUTPUT->heading(get_string('courseaccesses', 'tool_flexaccess'), 3);
echo html_writer::table(user_presenter::table([$userid => $snapshot], $scope, false));

// Actions.
$buttons = [];
if (recovery::can_recover($snapshot, $scope)) {
    $params = ['userid' => $userid];
    if ($scope !== null) {
        $params['courseid'] = $courseid;
    }
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/admin/tool/flexaccess/recover.php', $params),
        get_string('recoveraction', 'tool_flexaccess'),
        'get'
    );
}
if (
    $snapshot->verificationpending && has_capability(
        $scope !== null ? 'tool/flexaccess:recovercourse' : 'tool/flexaccess:recoversystem',
        $context
    )
) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'resendverification', 'sesskey' => sesskey()]),
        get_string('resendverification', 'tool_flexaccess')
    );
}
if (
    $snapshot->accountstate === 'pendingcredential'
        && has_capability('tool/flexaccess:convertaccounts', context_system::instance())
) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'resendsetpassword', 'sesskey' => sesskey()]),
        get_string('resendsetpassword', 'tool_flexaccess')
    );
}
echo html_writer::div(implode(' ', $buttons), 'mt-3');
echo $OUTPUT->footer();
