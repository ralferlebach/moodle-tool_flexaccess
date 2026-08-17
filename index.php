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
 * Administrative dashboard page for tool_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:viewdashboard', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('dashboard', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'tool_flexaccess'));

$stats = \auth_flexaccess\api::account_stats();
$mail = \auth_flexaccess\api::mailqueue_summary();

$accounttable = new html_table();
$accounttable->caption = get_string('dashaccounts', 'tool_flexaccess');
$accounttable->data = [
    [get_string('dashtotal', 'tool_flexaccess'), $stats['total']],
    [get_string('accounttypetemporary', 'tool_flexaccess'), $stats['temporary']],
    [get_string('accounttypeauthenticated', 'tool_flexaccess'), $stats['authenticated']],
    [get_string('dashprovisional', 'tool_flexaccess'), $stats['provisional']],
    [get_string('dashexpired', 'tool_flexaccess'), $stats['expired']],
];
echo html_writer::table($accounttable);

$mailtable = new html_table();
$mailtable->caption = get_string('dashmail', 'tool_flexaccess');
$due = $mail['nextdue'] > 0 ? userdate($mail['nextdue']) : get_string('dashnone', 'tool_flexaccess');
$mailtable->data = [
    [get_string('mqqueued', 'tool_flexaccess'), $mail['queued']],
    [get_string('mqsent', 'tool_flexaccess'), $mail['sent']],
    [get_string('mqfailed', 'tool_flexaccess'), $mail['failed']],
    [get_string('dashnextdue', 'tool_flexaccess'), $due],
];
echo html_writer::table($mailtable);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/admin/tool/flexaccess/accounts.php'),
        get_string('accounts', 'tool_flexaccess')
    ) . ' | ' .
    html_writer::link(
        new moodle_url('/admin/tool/flexaccess/mailqueue.php'),
        get_string('mailqueue', 'tool_flexaccess')
    )
);

echo $OUTPUT->footer();
