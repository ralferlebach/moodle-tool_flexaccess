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

/**
 * Teacher-facing, course-scoped management of anonymous access-list batches.
 *
 * This is the in-course counterpart to the site-wide admin/tool/flexaccess/batches.php: a teacher
 * creates, lists and downloads access-account batches (XLSX / PDF list / printable cards) for their
 * own course, without leaving the course context.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\batch;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
batch::require_manage($courseid);

$baseurl = new moodle_url('/admin/tool/flexaccess/coursebatches.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursebatches', 'tool_flexaccess'));
$PAGE->set_heading(format_string($course->fullname));

// Create a batch bound to this course.
if ($action === 'new') {
    $form = new \tool_flexaccess\form\coursebatch_form($baseurl->out(false) . '&action=new');
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        batch::create(
            $data->name,
            $courseid,
            (bool) $data->permanent,
            (int) $data->count,
            $data->usernameprefix
        );
        redirect(
            $baseurl,
            get_string('batch:created', 'tool_flexaccess', $data->count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('batch:create', 'tool_flexaccess'));
    $form->display();
    echo $OUTPUT->footer();
    return;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursebatches', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('coursebatches_intro', 'tool_flexaccess'));

echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url($baseurl, ['action' => 'new']),
        get_string('batch:create', 'tool_flexaccess'),
        'get'
    ),
    'mb-3'
);

$batches = batch::for_course($courseid);
if (!$batches) {
    echo $OUTPUT->notification(get_string('coursebatches_none', 'tool_flexaccess'), 'info');
    echo $OUTPUT->footer();
    return;
}

$table = new html_table();
$table->head = [
    get_string('batch:name', 'tool_flexaccess'),
    get_string('batch:accounttype', 'tool_flexaccess'),
    get_string('batch:members', 'tool_flexaccess'),
    get_string('download'),
];
foreach ($batches as $b) {
    $dl = new moodle_url('/admin/tool/flexaccess/batchdownload.php', ['id' => $b->id, 'sesskey' => sesskey()]);
    $links = html_writer::link(new moodle_url($dl, ['format' => 'excel']), 'XLSX')
        . ' · ' . html_writer::link(
            new moodle_url($dl, ['format' => 'pdflist']),
            get_string('batch:downloadpdflist', 'tool_flexaccess')
        )
        . ' · ' . html_writer::link(
            new moodle_url($dl, ['format' => 'cards']),
            get_string('batch:downloadcards', 'tool_flexaccess')
        );
    $type = $b->permanent
        ? get_string('batch:type_permanent', 'tool_flexaccess')
        : get_string('batch:type_temporary', 'tool_flexaccess');
    $table->data[] = [format_string($b->name), $type, $b->membercount, $links];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
