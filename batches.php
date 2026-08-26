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
 * Provision and manage batches of course accounts.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\batch;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:managebatches', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/batches.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('batches', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$returnurl = new moodle_url('/admin/tool/flexaccess/batches.php');
$form = new \tool_flexaccess\form\batch_form(new moodle_url($returnurl, ['action' => 'new']));

if ($action === 'new') {
    if ($form->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $form->get_data()) {
        $now = time();
        $expiry = (int) ($data->expiry ?? 0);
        $timeexpires = (!$data->permanent && $expiry > 0) ? $now + $expiry : null;
        $result = batch::create(
            (string) $data->name,
            (int) $data->courseid,
            (bool) $data->permanent,
            (int) $data->count,
            (string) $data->usernameprefix,
            (int) $data->passwordlength,
            $timeexpires,
            $now
        );
        // Large batches are provisioned in the background; say so instead of implying they exist.
        $queued = ($result['status'] ?? '') === batch::STATUS_QUEUED;
        redirect(
            new moodle_url($returnurl, ['action' => 'view', 'id' => $result['batchid']]),
            get_string($queued ? 'batchcreatedqueued' : 'batchcreated', 'tool_flexaccess', $data->count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('batchcreate', 'tool_flexaccess'));
    echo html_writer::tag('p', get_string('batches_intro', 'tool_flexaccess'));
    $form->display();
    echo $OUTPUT->footer();
    die;
}

if ($action === 'view' && $id > 0) {
    $batch = batch::get($id);
    if (!$batch) {
        redirect($returnurl);
    }
    $course = $DB->get_record('course', ['id' => $batch->courseid], 'id, fullname', IGNORE_MISSING);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($batch->name));
    echo html_writer::tag('p', get_string('batchsummary', 'tool_flexaccess', (object) [
        'count' => $batch->membercount,
        'course' => $course ? format_string($course->fullname) : ('#' . $batch->courseid),
        'type' => get_string($batch->permanent ? 'batchtype_permanent' : 'batchtype_temporary', 'tool_flexaccess'),
    ]));
    echo $OUTPUT->notification(get_string('batchdownloadnote', 'tool_flexaccess'), 'info');

    $dl = new moodle_url('/admin/tool/flexaccess/batchdownload.php', ['id' => $id, 'sesskey' => sesskey()]);
    echo html_writer::start_div('mb-3');
    echo $OUTPUT->single_button(
        new moodle_url($dl, ['format' => 'all']),
        get_string('batchdownloadall', 'tool_flexaccess'),
        'get'
    );
    echo html_writer::end_div();
    echo html_writer::div(
        html_writer::link(new moodle_url($dl, ['format' => 'excel']), get_string('batchdownloadexcel', 'tool_flexaccess'))
        . ' · ' .
        html_writer::link(new moodle_url($dl, ['format' => 'pdflist']), get_string('batchdownloadpdflist', 'tool_flexaccess'))
        . ' · ' .
        html_writer::link(new moodle_url($dl, ['format' => 'cards']), get_string('batchdownloadcards', 'tool_flexaccess'))
    );
    echo html_writer::tag('p', html_writer::link($returnurl, get_string('back')), ['class' => 'mt-3']);

    echo $OUTPUT->heading(get_string('batchconvert', 'tool_flexaccess'), 4);
    echo html_writer::tag('p', get_string('batchconvert_intro', 'tool_flexaccess'));
    echo $OUTPUT->single_button(
        new moodle_url('/admin/tool/flexaccess/batchconvert.php', ['id' => $id]),
        get_string('batchconvert', 'tool_flexaccess'),
        'get'
    );
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('batches', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('batches_intro', 'tool_flexaccess'));
echo $OUTPUT->single_button(
    new moodle_url($returnurl, ['action' => 'new']),
    get_string('batchcreate', 'tool_flexaccess'),
    'get'
);

$total = batch::count_all();
$batches = batch::all($page * $perpage, $perpage);
if ($batches) {
    $courseids = array_values(array_unique(array_map(static fn($b) => (int) $b->courseid, $batches)));
    $courses = $courseids ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname') : [];
    $table = new html_table();
    $table->head = [
        get_string('batchname', 'tool_flexaccess'),
        get_string('batchcourse', 'tool_flexaccess'),
        get_string('batchaccounttype', 'tool_flexaccess'),
        get_string('batchcount', 'tool_flexaccess'),
        '',
    ];
    foreach ($batches as $b) {
        $table->data[] = [
            format_string($b->name),
            isset($courses[$b->courseid])
                ? html_writer::link(
                    new moodle_url('/admin/tool/flexaccess/coursebatches.php', ['courseid' => $b->courseid]),
                    format_string($courses[$b->courseid]->fullname),
                    ['title' => get_string('batchopenincourse', 'tool_flexaccess')]
                )
                : ('#' . $b->courseid),
            get_string($b->permanent ? 'batchtype_permanent' : 'batchtype_temporary', 'tool_flexaccess'),
            $b->membercount,
            html_writer::link(
                new moodle_url($returnurl, ['action' => 'view', 'id' => $b->id]),
                get_string('batchdownloadopen', 'tool_flexaccess')
            ),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $returnurl);
} else {
    echo html_writer::tag('p', get_string('batches_none', 'tool_flexaccess'));
}
echo $OUTPUT->footer();
