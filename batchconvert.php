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
 * Convert a batch's accounts to permanent accounts from a filled-in export spreadsheet.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\batch;
use tool_flexaccess\local\batch_import;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:managebatches', $context);

$id = required_param('id', PARAM_INT);
$batch = batch::get($id);
if (!$batch) {
    redirect(new moodle_url('/admin/tool/flexaccess/batches.php'));
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/batchconvert.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('batch:convert', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$viewurl = new moodle_url('/admin/tool/flexaccess/batches.php', ['action' => 'view', 'id' => $id]);
$form = new \tool_flexaccess\form\batch_convert_form(null, ['id' => $id]);

if ($form->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $form->get_data()) {
    $tempdir = make_request_directory();
    $path = $tempdir . '/import.xlsx';
    $form->save_file('excelfile', $path, true);

    $rows = batch_import::parse($path);
    $result = batch_import::convert($id, $rows, $data->usernamerule, time());

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('batch:convert', 'tool_flexaccess'));
    echo $OUTPUT->notification(
        get_string('batch:convertsummary', 'tool_flexaccess', (object) [
            'converted' => $result['converted'],
            'skipped' => $result['skipped'],
        ]),
        $result['converted'] > 0 ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
    );
    if (!empty($result['errors'])) {
        echo html_writer::start_tag('ul');
        foreach ($result['errors'] as $line) {
            echo html_writer::tag('li', s($line));
        }
        echo html_writer::end_tag('ul');
    }
    echo html_writer::tag('p', html_writer::link($viewurl, get_string('back')), ['class' => 'mt-3']);
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('batch:convert', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('batch:convert_intro', 'tool_flexaccess'));
$form->display();
echo $OUTPUT->footer();
