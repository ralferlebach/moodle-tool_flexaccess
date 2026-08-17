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
 * Administrative mailqueue page for tool_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:managemailqueue', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/mailqueue.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('mailqueue', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$status = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
$statusfilter = in_array($status, ['queued', 'sent', 'failed'], true) ? $status : '';
$baseurl = new moodle_url('/admin/tool/flexaccess/mailqueue.php', ['status' => $statusfilter]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mailqueue', 'tool_flexaccess'));

$summary = \auth_flexaccess\api::mailqueue_summary();
echo html_writer::tag('p', get_string('mqsummary', 'tool_flexaccess', (object) [
    'queued' => $summary['queued'], 'sent' => $summary['sent'], 'failed' => $summary['failed'],
]));

$total = \auth_flexaccess\api::count_mailqueue($statusfilter);
$rows = \auth_flexaccess\api::list_mailqueue($statusfilter, $page, $perpage);

$table = new html_table();
$table->head = [
    get_string('mqrecipient', 'tool_flexaccess'),
    get_string('mqtype', 'tool_flexaccess'),
    get_string('mqstatus', 'tool_flexaccess'),
    get_string('mqattempts', 'tool_flexaccess'),
    get_string('mqnextrun', 'tool_flexaccess'),
];
foreach ($rows as $row) {
    $nextrun = $row->nextrun > 0 ? userdate($row->nextrun) : '-';
    $table->data[] = [s($row->recipient), s($row->mailtype), s($row->status), (int) $row->attempts, $nextrun];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('mqnone', 'tool_flexaccess'), 'info');
} else {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
