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
 * Recover frozen FlexAccess users: preview, explicit confirmation, one result per user.
 *
 * Works for a single user and for batches, in a course (courseid set: only that course's enrolments
 * change) or site-wide. Enrolment reactivation and re-enrolment are opt-in per enrolment.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\navigation;
use tool_flexaccess\local\recovery;

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$userids = optional_param_array('userids', [], PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($userid > 0) {
    $userids[] = $userid;
}
$userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
$scope = $courseid > 0 ? $courseid : null;

if ($scope !== null) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('tool/flexaccess:recovercourse', $context);
    $PAGE->set_context($context);
    $backurl = new moodle_url('/admin/tool/flexaccess/course.php', ['courseid' => $courseid]);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_heading(format_string($course->fullname));
} else {
    require_login();
    $context = context_system::instance();
    require_capability('tool/flexaccess:recoversystem', $context);
    $PAGE->set_context($context);
    $backurl = new moodle_url('/admin/tool/flexaccess/accounts.php');
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));
}
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/recover.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('recovertitle', 'tool_flexaccess'));

if (!$userids) {
    redirect($backurl, get_string('recovernoselection', 'tool_flexaccess'), null, \core\output\notification::NOTIFY_WARNING);
}

/**
 * Decode "userid:value" pairs posted by the preview form.
 *
 * @param string $name Parameter name.
 * @return array<int, int[]> userid => values.
 */
function tool_flexaccess_recover_pairs(string $name): array {
    $pairs = [];
    foreach (optional_param_array($name, [], PARAM_RAW) as $raw) {
        if (preg_match('/^(\d+):(\d+)$/', (string) $raw, $m)) {
            $pairs[(int) $m[1]][] = (int) $m[2];
        }
    }
    return $pairs;
}

$tabs = $scope !== null ? navigation::render_course($courseid, navigation::USERS) : navigation::render_system(navigation::USERS);

if ($confirm && confirm_sesskey()) {
    $results = recovery::recover_batch($userids, $scope, [
        'reactivate' => tool_flexaccess_recover_pairs('reactivate'),
        'reenrol' => tool_flexaccess_recover_pairs('reenrol'),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('recoverresults', 'tool_flexaccess'));
    echo $tabs;
    $snapshots = recovery::snapshots(array_keys($results), $scope);
    $table = new html_table();
    $table->head = [get_string('accountname', 'tool_flexaccess'), get_string('recoveroutcome', 'tool_flexaccess')];
    foreach ($results as $id => $result) {
        $labels = array_map(
            static fn(string $o): string => get_string('recoveroutcome_' . $o, 'tool_flexaccess'),
            $result->outcomes
        );
        if ($result->reason !== '') {
            $labels[] = get_string('recoverreason', 'tool_flexaccess', s($result->reason));
        }
        $name = isset($snapshots[$id]) ? s($snapshots[$id]->fullname) : (string) $id;
        $table->data[] = [$name, implode(html_writer::empty_tag('br'), $labels)];
    }
    echo html_writer::table($table);
    echo $OUTPUT->continue_button($backurl);
    echo $OUTPUT->footer();
    exit;
}

// Preview: what would change, per user; enrolment changes are explicit checkboxes.
$snapshots = recovery::snapshots($userids, $scope);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recovertitle', 'tool_flexaccess'));
echo $tabs;
echo html_writer::tag('p', get_string('recoverpreviewintro', 'tool_flexaccess'));

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/admin/tool/flexaccess/recover.php'))->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->head = [
    get_string('accountname', 'tool_flexaccess'),
    get_string('accountstate', 'tool_flexaccess'),
    get_string('recoverplannedaccount', 'tool_flexaccess'),
    get_string('recoverplannedenrolments', 'tool_flexaccess'),
];
foreach ($userids as $id) {
    if (!isset($snapshots[$id])) {
        continue;
    }
    $snapshot = $snapshots[$id];
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids[]', 'value' => $id]);
    $eligible = recovery::can_recover($snapshot, $scope);
    $action = $eligible ? recovery::predict_account_action($snapshot) : 'outofscope';
    $enrolcell = [];
    foreach ($snapshot->enrolments as $enrolment) {
        $label = format_string(get_course($enrolment->courseid)->shortname) . ': ' . get_string(
            $enrolment->status === ENROL_USER_ACTIVE ? 'enrolactive' : 'enrolsuspended',
            'tool_flexaccess'
        ) . ($enrolment->timeend > 0 ? ' (' . userdate($enrolment->timeend) . ')' : '');
        if ($eligible && $enrolment->reactivatable) {
            $enrolcell[] = html_writer::checkbox(
                'reactivate[]',
                $id . ':' . $enrolment->ueid,
                false,
                get_string('recoverreactivate', 'tool_flexaccess', $label)
            );
        } else {
            $enrolcell[] = s($label);
        }
    }
    foreach ($snapshot->unenrolledcourses as $unenrolled) {
        $label = format_string(get_course($unenrolled)->shortname);
        if ($eligible && has_capability('tool/flexaccess:reenrol', context_course::instance($unenrolled))) {
            $enrolcell[] = html_writer::checkbox(
                'reenrol[]',
                $id . ':' . $unenrolled,
                false,
                get_string('recoverreenrol', 'tool_flexaccess', $label)
            );
        } else {
            $enrolcell[] = get_string('recoverreenrolrequired', 'tool_flexaccess', $label);
        }
    }
    $table->data[] = [
        s($snapshot->fullname),
        \tool_flexaccess\local\account_labels::state($snapshot->accountstate),
        get_string('recoveraction_' . $action, 'tool_flexaccess'),
        $enrolcell ? implode(html_writer::empty_tag('br'), $enrolcell) : '-',
    ];
}
echo html_writer::table($table);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('recoverconfirm', 'tool_flexaccess'),
    'class' => 'btn btn-primary',
]);
echo ' ' . html_writer::link($backurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
