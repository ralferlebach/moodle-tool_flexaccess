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
    // Each value is "userid:id"; anything else is discarded by the strict pattern below.
    foreach (optional_param_array($name, [], PARAM_NOTAGS) as $raw) {
        if (preg_match('/^(\d+):(\d+)$/', (string) $raw, $m)) {
            $pairs[(int) $m[1]][] = (int) $m[2];
        }
    }
    return $pairs;
}

/**
 * Render one blocker of the recovery postcondition, with the place where an administrator resolves it.
 *
 * @param stdClass $blocker Blocker (scope 'account'|'course', courseid, code).
 * @param int $userid User the blocker belongs to.
 * @return string HTML.
 */
function tool_flexaccess_recover_blocker(stdClass $blocker, int $userid): string {
    if ($blocker->scope === 'account') {
        $key = 'recoverblocker_account_' . $blocker->code;
        $text = get_string_manager()->string_exists($key, 'tool_flexaccess')
            ? get_string($key, 'tool_flexaccess')
            : get_string('recoverblocker_account', 'tool_flexaccess', s($blocker->code));
    } else {
        $text = \tool_flexaccess\local\user_presenter::course_name($blocker->courseid) . ': '
            . get_string('effectivestate_' . $blocker->code, 'tool_flexaccess');
    }
    $url = recovery::blocker_url($blocker, $userid);
    if ($url) {
        $text .= ' ' . html_writer::link($url, get_string('recoverblockerresolve', 'tool_flexaccess'));
    }
    return $text;
}

$tabs = $scope !== null ? navigation::render_course($courseid, navigation::USERS) : navigation::render_system(navigation::USERS);

if ($confirm && confirm_sesskey()) {
    $results = recovery::recover_batch($userids, $scope, [
        'reactivate' => tool_flexaccess_recover_pairs('reactivate'),
        'reenrol' => tool_flexaccess_recover_pairs('reenrol'),
        'confirmcrosscourse' => optional_param_array('confirmcrosscourse', [], PARAM_INT),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('recoverresults', 'tool_flexaccess'));
    echo $tabs;
    $snapshots = recovery::snapshots(array_keys($results), $scope);
    $table = new html_table();
    $table->head = [
        get_string('accountname', 'tool_flexaccess'),
        get_string('recoveroverall', 'tool_flexaccess'),
        get_string('recoveroutcome', 'tool_flexaccess'),
        get_string('recoverblockers', 'tool_flexaccess'),
    ];
    foreach ($results as $id => $result) {
        // One unambiguous overall status per user; the technical actions are listed beside it.
        $restored = $result->overall === recovery::EFFECTIVE_ACCESS_RESTORED;
        $overall = html_writer::span(
            get_string('recoveroutcome_' . $result->overall, 'tool_flexaccess'),
            'badge ' . ($restored ? 'badge-success bg-success' : 'badge-warning bg-warning text-dark')
        );
        $labels = array_map(
            static fn(string $o): string => get_string('recoveroutcome_' . $o, 'tool_flexaccess'),
            array_diff($result->outcomes, [$result->overall])
        );
        if ($result->reason !== '') {
            $key = 'recoverreason_' . $result->reason;
            $labels[] = get_string_manager()->string_exists($key, 'tool_flexaccess')
                ? get_string($key, 'tool_flexaccess')
                : get_string('recoverreason', 'tool_flexaccess', s($result->reason));
        }
        $blockers = [];
        foreach ($result->blockers as $blocker) {
            $blockers[] = tool_flexaccess_recover_blocker($blocker, (int) $id);
        }
        $name = isset($snapshots[$id]) ? s($snapshots[$id]->fullname) : (string) $id;
        $table->data[] = [
            $name,
            $overall,
            implode(html_writer::empty_tag('br'), $labels),
            $blockers ? implode(html_writer::empty_tag('br'), $blockers) : '-',
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->continue_button($backurl);
    echo $OUTPUT->footer();
    exit;
}

// Preview: what would change, per user; enrolment changes are explicit checkboxes.
$snapshots = recovery::snapshots($userids, $scope);
\tool_flexaccess\local\user_presenter::prefetch_courses($snapshots);
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
$br = html_writer::empty_tag('br');
foreach ($userids as $id) {
    if (!isset($snapshots[$id])) {
        continue;
    }
    $snapshot = $snapshots[$id];
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids[]', 'value' => $id]);
    $eligible = recovery::can_recover($snapshot, $scope);
    $action = $eligible ? recovery::predict_account_action($snapshot) : 'outofscope';

    // Account facts as Core sees them: lifecycle state, Moodle suspension and who set it.
    $statecell = \tool_flexaccess\local\account_labels::state($snapshot->accountstate);
    $provenance = (int) $snapshot->suspended === 1
        ? get_string('recoverlockedby_' . ($snapshot->lockedby ?? 'unknown'), 'tool_flexaccess')
        : get_string('no');
    $statecell .= html_writer::div(get_string('recoversuspended', 'tool_flexaccess', $provenance), 'small');

    $enrolcell = [];
    foreach ($snapshot->enrolments as $enrolment) {
        $facts = [
            get_string($enrolment->status === ENROL_USER_ACTIVE ? 'enrolactive' : 'enrolsuspended', 'tool_flexaccess'),
            get_string(
                'recoverwindow',
                'tool_flexaccess',
                (object) [
                    'start' => $enrolment->timestart > 0 ? userdate($enrolment->timestart) : '-',
                    'end' => $enrolment->timeend > 0 ? userdate($enrolment->timeend) : get_string('never'),
                ]
            ),
            get_string($enrolment->instanceenabled ? 'recoverinstanceenabled' : 'recoverinstancedisabled', 'tool_flexaccess'),
            get_string($enrolment->hasrole ? 'recoverrolepresent' : 'recoverrolemissing', 'tool_flexaccess'),
        ];
        $label = \tool_flexaccess\local\user_presenter::course_name($enrolment->courseid) . ': ' . implode(' · ', $facts);
        $expectedwith = recovery::predict_enrolment($snapshot, $enrolment, true);
        $expectedwithout = recovery::predict_enrolment($snapshot, $enrolment, false);
        $expected = html_writer::div(get_string(
            'recoverexpected',
            'tool_flexaccess',
            $expectedwith === $expectedwithout
                ? get_string('effectivestate_' . $expectedwith, 'tool_flexaccess')
                : get_string('recoverexpectedselected', 'tool_flexaccess', (object) [
                    'selected' => get_string('effectivestate_' . $expectedwith, 'tool_flexaccess'),
                    'unselected' => get_string('effectivestate_' . $expectedwithout, 'tool_flexaccess'),
                ])
        ), 'small text-muted');
        if ($eligible && $enrolment->reactivatable) {
            $enrolcell[] = html_writer::checkbox(
                'reactivate[]',
                $id . ':' . $enrolment->ueid,
                false,
                get_string('recoverreactivate', 'tool_flexaccess', $label)
            ) . $expected;
        } else {
            $enrolcell[] = s($label) . $expected;
        }
        foreach ($enrolment->problems as $problem) {
            // Causes recovery may not resolve itself are pointed at the place an administrator resolves them.
            $blocker = (object) ['scope' => 'course', 'courseid' => $enrolment->courseid, 'code' => $problem];
            if (in_array($problem, ['instance_disabled', 'multiple_instances', 'plugin_disabled'], true)) {
                $enrolcell[] = html_writer::div(tool_flexaccess_recover_blocker($blocker, $id), 'alert alert-warning p-1 my-1');
            }
        }
    }
    foreach ($snapshot->unenrolledcourses as $unenrolled) {
        $label = \tool_flexaccess\local\user_presenter::course_name($unenrolled);
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
    $accountcell = get_string('recoveraction_' . $action, 'tool_flexaccess');
    if ($action === 'foreignlock') {
        $accountcell .= html_writer::div(tool_flexaccess_recover_blocker(
            (object) ['scope' => 'account', 'courseid' => 0, 'code' => 'suspended'],
            $id
        ), 'alert alert-warning p-1 my-1');
    }
    $impact = $eligible ? recovery::cross_course_impact($snapshot, $scope) : [];
    if ($impact) {
        // The account is global: recovering it here also restores access in these other courses.
        $accountcell .= html_writer::div(
            get_string('recovercrosscourse', 'tool_flexaccess', count($impact)),
            'alert alert-warning p-1 my-1'
        );
        if (has_capability('tool/flexaccess:recoversystem', context_system::instance())) {
            $accountcell .= html_writer::checkbox(
                'confirmcrosscourse[]',
                $id,
                false,
                get_string('recovercrosscourseconfirm', 'tool_flexaccess', count($impact))
            );
        } else {
            $accountcell .= html_writer::div(get_string('recoverreason_crosscourse', 'tool_flexaccess'), 'small');
        }
    }
    $table->data[] = [
        s($snapshot->fullname),
        $statecell,
        $accountcell,
        $enrolcell ? implode($br, $enrolcell) : '-',
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
