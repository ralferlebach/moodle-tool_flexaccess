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
 * Administrative accounts page for tool_flexaccess.
 *
 * Lists FlexAccess accounts via the auth facade and allows administrative conversion of a
 * temporary user to an authenticated user. All domain mutations go through the facade.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$query = optional_param('q', '', PARAM_NOTAGS);
$type = optional_param('type', '', PARAM_ALPHAEXT);
$state = optional_param('state', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$convert = optional_param('convert', 0, PARAM_INT);
$perpage = 50;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:viewaccounts', $context);

$typefilter = $type !== '' ? $type : null;
$statefilter = $state !== '' ? $state : null;

$baseurl = new moodle_url('/admin/tool/flexaccess/accounts.php',
    ['q' => $query, 'type' => $type, 'state' => $state]);

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('accounts', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

if ($convert > 0) {
    require_sesskey();
    require_capability('tool/flexaccess:convertaccounts', $context);
    $done = \auth_flexaccess\api::admin_convert($convert);
    $message = $done ? get_string('accountconverted', 'tool_flexaccess')
        : get_string('accountnotconverted', 'tool_flexaccess');
    redirect($baseurl, $message, null,
        $done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('accounts', 'tool_flexaccess'));

$total = \auth_flexaccess\api::count_accounts($query, $typefilter, $statefilter);
$accounts = \auth_flexaccess\api::search_accounts($query, $typefilter, $statefilter, $page, $perpage);
$canconvert = has_capability('tool/flexaccess:convertaccounts', $context);

$table = new html_table();
$table->head = [
    get_string('accountname', 'tool_flexaccess'),
    get_string('accountemail', 'tool_flexaccess'),
    get_string('accounttype', 'tool_flexaccess'),
    get_string('accountstate', 'tool_flexaccess'),
    get_string('accountactions', 'tool_flexaccess'),
];
foreach ($accounts as $account) {
    $fullname = trim($account->firstname . ' ' . $account->lastname);
    $action = '';
    if ($canconvert && $account->accounttype === \auth_flexaccess\local\account_type::TEMPORARY_USER) {
        $url = new moodle_url($baseurl, ['convert' => $account->userid, 'page' => $page, 'sesskey' => sesskey()]);
        $action = html_writer::link($url, get_string('accountconvert', 'tool_flexaccess'));
    }
    $table->data[] = [s($fullname), s($account->email), s($account->accounttype), s($account->accountstate), $action];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('accountsnone', 'tool_flexaccess'), 'info');
} else {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
