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
 * FlexAccess system status: cross-plugin readiness with explicit, previewed repairs.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\health;
use tool_flexaccess\local\navigation;

$repair = optional_param('repair', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:viewsystemstatus', $context);

$pageurl = new moodle_url('/admin/tool/flexaccess/status.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tabsystemcheck', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

if ($repair !== '' && !in_array($repair, health::repairs(), true)) {
    throw new moodle_exception('invalidrequest', 'error');
}

// Execute a confirmed repair: capability, POST and sesskey; the result is logged by the service.
if ($repair !== '' && $confirm) {
    require_capability('tool/flexaccess:repairsystem', $context);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
    $changed = health::repair($repair);
    redirect(
        $pageurl,
        get_string('healthrepairdone', 'tool_flexaccess', $changed),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tabsystemcheck', 'tool_flexaccess'));
echo navigation::render_system(navigation::STATUS, 'systemcheck');

// Preview of a repair: what exactly would change, then an explicit confirmation.
if ($repair !== '') {
    require_capability('tool/flexaccess:repairsystem', $context);
    $preview = health::preview($repair);
    echo $OUTPUT->heading(get_string('healthrepair_' . $repair, 'tool_flexaccess'), 3);
    echo html_writer::tag('p', get_string('healthrepairdesc_' . $repair, 'tool_flexaccess'));
    $items = [];
    foreach ($preview['problems'] as $problem) {
        $items[] = get_string('health_role_' . $problem, 'tool_flexaccess');
    }
    if ($preview['userids']) {
        $namefields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
        $users = $DB->get_records_list('user', 'id', array_slice($preview['userids'], 0, 200), '', $namefields);
        foreach ($users as $user) {
            $items[] = html_writer::link(
                new moodle_url('/admin/tool/flexaccess/user.php', ['userid' => $user->id]),
                fullname($user)
            );
        }
    }
    if (!$items) {
        echo $OUTPUT->notification(get_string('healthrepairnothing', 'tool_flexaccess'), 'info');
        echo $OUTPUT->continue_button($pageurl);
    } else {
        echo html_writer::alist($items);
        echo $OUTPUT->confirm(
            get_string('healthrepairconfirm', 'tool_flexaccess', count($items)),
            new single_button(new moodle_url($pageurl, ['repair' => $repair, 'confirm' => 1]), get_string('confirm'), 'post'),
            new single_button($pageurl, get_string('cancel'), 'get')
        );
    }
    echo $OUTPUT->footer();
    exit;
}

$sections = health::run();
$overall = health::overall($sections);
echo $OUTPUT->notification(
    get_string('healthoverall_' . $overall, 'tool_flexaccess'),
    $overall === health::OK ? 'success' : ($overall === health::WARNING ? 'warning' : 'error')
);
$canrepair = has_capability('tool/flexaccess:repairsystem', $context);
$badges = [
    health::OK => 'badge-success bg-success',
    health::INFO => 'badge-info bg-info',
    health::WARNING => 'badge-warning bg-warning text-dark',
    health::ERROR => 'badge-danger bg-danger',
];
foreach ($sections as $section => $items) {
    echo $OUTPUT->heading(get_string('healthsection_' . $section, 'tool_flexaccess'), 3);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable flexaccess-health';
    $table->head = [
        get_string('status'),
        get_string('healthcheck', 'tool_flexaccess'),
        get_string('healthdetail', 'tool_flexaccess'),
        get_string('healthrepair', 'tool_flexaccess'),
    ];
    // Items that can strand users are shown first.
    usort($items, static fn(\stdClass $a, \stdClass $b): int =>
        array_search($b->status, [health::OK, health::INFO, health::WARNING, health::ERROR], true)
            <=> array_search($a->status, [health::OK, health::INFO, health::WARNING, health::ERROR], true));
    foreach ($items as $item) {
        $detail = $item->detail;
        if (str_starts_with($detail, 'http')) {
            $detail = html_writer::link($detail, get_string('healthopen', 'tool_flexaccess'));
        } else {
            $detail = s($detail);
        }
        $action = '';
        if ($item->repair !== null && $canrepair) {
            $action = html_writer::link(
                new moodle_url($pageurl, ['repair' => $item->repair]),
                get_string('healthrepairpreview', 'tool_flexaccess')
            );
        }
        $table->data[] = [
            html_writer::span(get_string('healthstatus_' . $item->status, 'tool_flexaccess'), 'badge ' . $badges[$item->status]),
            s($item->label),
            $detail,
            $action,
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
