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
 * The central, filterable FlexAccess user management (Users tab). It shares its table with the
 * course view, offers the recovery filters and site-wide batch recovery, and links each user to the
 * drill-down and to the administrative conversion. All domain reads/mutations go through the facades.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\navigation;
use tool_flexaccess\local\recovery;
use tool_flexaccess\local\user_presenter;

$query = optional_param('q', '', PARAM_NOTAGS);
$type = optional_param('type', '', PARAM_ALPHAEXT);
$state = optional_param('state', '', PARAM_ALPHA);
$filter = optional_param('filter', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:viewaccounts', $context);

// Filters of the recovery workflow. State filters map onto the account state; the others are
// conditions across the core user, the enrolments and the verification funnel.
$statefilters = ['provisional' => 'provisional', 'expired' => 'expired', 'pendingcredential' => 'pendingcredential'];
$conditionfilters = ['usersuspended', 'enrolsuspended', 'verificationpending'];
if (isset($statefilters[$filter])) {
    $state = $statefilters[$filter];
}
$condition = in_array($filter, $conditionfilters, true) ? $filter : null;

$typefilter = $type !== '' ? $type : null;
$statefilter = $state !== '' ? $state : null;
// An all-digit query is also matched against reference numbers exactly.
$reference = \tool_flexaccess\local\reference_query::normalise($query);
$referencefilter = $reference !== '' ? $reference : null;

$baseurl = new moodle_url(
    '/admin/tool/flexaccess/accounts.php',
    ['q' => $query, 'type' => $type, 'state' => $state, 'filter' => $filter]
);

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tabusers', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tabusers', 'tool_flexaccess'));
echo navigation::render_system(navigation::USERS);

// Search box (matches e-mail, name and reference number) and recovery filter.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-3']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'q',
    'value' => $query,
    'placeholder' => get_string('accountsearch', 'tool_flexaccess'),
    'aria-label' => get_string('accountsearch', 'tool_flexaccess'),
    'class' => 'form-control d-inline-block w-auto',
]);
$options = ['' => get_string('filterall', 'tool_flexaccess')];
foreach (array_merge(array_keys($statefilters), $conditionfilters) as $key) {
    $options[$key] = get_string('filter_' . $key, 'tool_flexaccess');
}
echo ' ' . html_writer::label(
    get_string('filter', 'tool_flexaccess'),
    'flexaccess-filter',
    false,
    ['class' => 'sr-only visually-hidden']
);
echo html_writer::select($options, 'filter', $filter, false, [
    'id' => 'flexaccess-filter',
    'class' => 'custom-select form-select d-inline-block w-auto',
]);
echo ' ' . html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$total = \auth_flexaccess\api::count_accounts($query, $typefilter, $statefilter, $referencefilter, $condition);
$accounts = \auth_flexaccess\api::search_accounts($query, $typefilter, $statefilter, $page, $perpage, $referencefilter, $condition);
$userids = array_map(static fn(\stdClass $a): int => (int) $a->userid, $accounts);
$snapshots = recovery::snapshots($userids, null);
$ordered = [];
foreach ($userids as $id) {
    if (isset($snapshots[$id])) {
        $ordered[$id] = $snapshots[$id];
    }
}
$canrecover = has_capability('tool/flexaccess:recoversystem', $context);

if (!$ordered) {
    echo $OUTPUT->notification(get_string('accountsnone', 'tool_flexaccess'), 'info');
} else {
    $table = user_presenter::table($ordered, null, $canrecover);
    if ($canrecover) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/admin/tool/flexaccess/recover.php'))->out(false),
        ]);
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
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
