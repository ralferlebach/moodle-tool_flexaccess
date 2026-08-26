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
 * Course-scoped access-list batches: managers create and download them; teachers request them.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\batch;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$prefillcount = optional_param('count', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
batch::require_request($courseid);

$cancreate = batch::can_create($courseid);
$canview = batch::can_view($courseid);
$baseurl = new moodle_url('/admin/tool/flexaccess/coursebatches.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursebatches', 'tool_flexaccess'));
$PAGE->set_heading(format_string($course->fullname));

// Managers create a batch bound to this course.
if ($action === 'new' && $cancreate) {
    batch::require_create($courseid);
    $form = new \tool_flexaccess\form\coursebatch_form($baseurl->out(false) . '&action=new');
    if ($prefillcount > 0) {
        $form->set_data(['count' => $prefillcount]);
    }
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        batch::create($data->name, $courseid, (bool) $data->permanent, (int) $data->count, $data->usernameprefix);
        redirect(
            $baseurl,
            get_string('batchcreated', 'tool_flexaccess', $data->count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('batchcreate', 'tool_flexaccess'));
    $form->display();
    echo $OUTPUT->footer();
    return;
}

// Teachers without provisioning rights request a batch, which notifies the course's provisioners.
if ($action === 'request' && !$cancreate) {
    $form = new \tool_flexaccess\form\coursebatchrequest_form($baseurl->out(false) . '&action=request');
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        $notified = batch::notify_request($courseid, (int) $USER->id, (int) $data->count);
        $msg = $notified > 0
            ? get_string('coursebatches_requested', 'tool_flexaccess', $notified)
            : get_string('coursebatches_requestednorecipients', 'tool_flexaccess');
        redirect(
            $baseurl,
            $msg,
            null,
            $notified > 0 ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('coursebatches_request', 'tool_flexaccess'));
    $form->display();
    echo $OUTPUT->footer();
    return;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursebatches', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('coursebatches_intro', 'tool_flexaccess'));

echo html_writer::start_div('mb-3');
if ($cancreate) {
    echo $OUTPUT->single_button(
        new moodle_url($baseurl, ['action' => 'new']),
        get_string('batchcreate', 'tool_flexaccess'),
        'get'
    );
} else {
    echo $OUTPUT->single_button(
        new moodle_url($baseurl, ['action' => 'request']),
        get_string('coursebatches_request', 'tool_flexaccess'),
        'get'
    );
}
echo html_writer::end_div();

// Users who may neither view nor create only see the request entry point above.
if (!$canview) {
    echo $OUTPUT->notification(get_string('coursebatches_requesthint', 'tool_flexaccess'), 'info');
    echo $OUTPUT->footer();
    return;
}

$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
$total = batch::count_for_course($courseid);
$batches = batch::for_course($courseid, $page * $perpage, $perpage);
if (!$batches) {
    echo $OUTPUT->notification(get_string('coursebatches_none', 'tool_flexaccess'), 'info');
    echo $OUTPUT->footer();
    return;
}

$table = new html_table();
$table->head = [
    get_string('batchname', 'tool_flexaccess'),
    get_string('batchaccounttype', 'tool_flexaccess'),
    get_string('batchmembers', 'tool_flexaccess'),
    get_string('batchstatus', 'tool_flexaccess'),
    get_string('download'),
];
foreach ($batches as $b) {
    $dl = new moodle_url('/admin/tool/flexaccess/batchdownload.php', ['id' => $b->id, 'sesskey' => sesskey()]);
    $links = html_writer::link(new moodle_url($dl, ['format' => 'excel']), 'XLSX')
        . ' · ' . html_writer::link(
            new moodle_url($dl, ['format' => 'pdflist']),
            get_string('batchdownloadpdflist', 'tool_flexaccess')
        )
        . ' · ' . html_writer::link(
            new moodle_url($dl, ['format' => 'cards']),
            get_string('batchdownloadcards', 'tool_flexaccess')
        );
    $type = $b->permanent
        ? get_string('batchtype_permanent', 'tool_flexaccess')
        : get_string('batchtype_temporary', 'tool_flexaccess');
    // While a batch is still being provisioned there is nothing complete to issue credentials for.
    if (($b->status ?? batch::STATUS_COMPLETE) !== batch::STATUS_COMPLETE) {
        $links = '-';
    }
    $table->data[] = [
        format_string($b->name),
        $type,
        $b->membercount,
        batch::status_label($b),
        $links,
    ];
}
echo html_writer::table($table);
if ($total > $perpage) {
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}
echo $OUTPUT->footer();
