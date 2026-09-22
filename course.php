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
 * Course-scoped FlexAccess management: the users tab (account and enrolment state together).
 *
 * Reached via Course > More > FlexAccess. Shows exactly the FlexAccess users of this course and
 * offers recovery only for them and only for enrolments of this course.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\navigation;
use tool_flexaccess\local\recovery;
use tool_flexaccess\local\user_presenter;

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

$pageurl = new moodle_url('/admin/tool/flexaccess/course.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursemanagement', 'tool_flexaccess'));
$PAGE->set_heading(format_string($course->fullname));

// Without the user view capability, the FlexAccess entry leads to the first tab the user may open.
if (!has_capability('tool/flexaccess:viewcourseusers', $context)) {
    $tabs = navigation::course_tabs($courseid);
    if (!$tabs) {
        require_capability('tool/flexaccess:viewcourseusers', $context);
    }
    redirect(reset($tabs)['url']);
}

$snapshots = recovery::snapshots(recovery::course_userids($courseid), $courseid);
$canrecover = has_capability('tool/flexaccess:recovercourse', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursemanagement', 'tool_flexaccess'));
echo navigation::render_course($courseid, navigation::USERS);
echo html_writer::tag('p', get_string('courseusersintro', 'tool_flexaccess'));

if (!$snapshots) {
    echo $OUTPUT->notification(get_string('courseusersnone', 'tool_flexaccess'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = user_presenter::table($snapshots, $courseid, $canrecover);
if ($canrecover) {
    // Course batch: select users, preview, then confirm. Only this course's enrolments change.
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/admin/tool/flexaccess/recover.php'))->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::table($table);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('recoverselected', 'tool_flexaccess'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
} else {
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
