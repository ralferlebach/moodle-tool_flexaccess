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
 * Administrative policies page for tool_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:viewpolicies', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/policies.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('policies', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('policies', 'tool_flexaccess'));
echo $OUTPUT->notification(get_string('stubpolicies', 'tool_flexaccess'), 'info');
echo html_writer::tag('p', 'Planned diagnostics include effective temporary-user access-key scope (none/system/course) without exposing secret values or hashes.');
echo $OUTPUT->footer();
