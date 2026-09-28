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
 * Decide, in batches, where suspensions of unknown origin come from.
 *
 * Lists every FlexAccess account whose Moodle suspension has no recorded origin (legacy data, or an
 * account suspended outside FlexAccess). An administrator selects accounts and decides for the
 * selection: the suspension comes from FlexAccess (optionally recover right away), or it is an
 * administrative decision that stays. Preview, confirmation, one result per account, audit trail.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\navigation;
use tool_flexaccess\local\reconciliation;
use tool_flexaccess\local\recovery;

$page = optional_param('page', 0, PARAM_INT);
$selectall = optional_param('selectall', 0, PARAM_BOOL);
$decision = optional_param('decision', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$reactivateall = optional_param('reactivateall', 0, PARAM_BOOL);
$userids = array_values(array_unique(array_filter(optional_param_array('userids', [], PARAM_INT))));
$perpage = 100;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:recoversystem', $context);

$pageurl = new moodle_url('/admin/tool/flexaccess/lockreview.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tablockreview', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$decisions = ['flexaccess', 'flexaccessrecover', 'admin'];
if ($decision !== '' && !in_array($decision, $decisions, true)) {
    throw new moodle_exception('invalidrequest', 'error');
}

/**
 * Render the hidden fields that carry the selection and decision into the next step.
 *
 * @param int[] $userids Selected users.
 * @param string $decision Decision.
 * @param int $reactivateall Whether to reactivate suspended enrolments.
 * @return string HTML.
 */
function tool_flexaccess_lockreview_hidden(array $userids, string $decision, int $reactivateall): string {
    $html = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'decision', 'value' => $decision]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reactivateall', 'value' => $reactivateall]);
    foreach ($userids as $id) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids[]', 'value' => $id]);
    }
    return $html;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tablockreview', 'tool_flexaccess'));
echo navigation::render_system(navigation::STATUS, 'lockreview');

// Step 3: execute the confirmed decision.
if ($confirm && $decision !== '' && $userids) {
    require_sesskey();
    $results = reconciliation::decide_lock_origin(
        $userids,
        $decision === 'admin' ? reconciliation::DECIDE_ADMIN : reconciliation::DECIDE_FLEXACCESS,
        $decision === 'flexaccessrecover',
        (bool) $reactivateall
    );
    $snapshots = recovery::snapshots(array_keys($results), null);
    $table = new html_table();
    $table->head = [get_string('accountname', 'tool_flexaccess'), get_string('recoveroutcome', 'tool_flexaccess')];
    foreach ($results as $id => $outcome) {
        $name = isset($snapshots[$id]) ? s($snapshots[$id]->fullname) : (string) $id;
        $table->data[] = [$name, get_string('lockreviewresult_' . $outcome, 'tool_flexaccess')];
    }
    echo html_writer::table($table);
    echo $OUTPUT->continue_button($pageurl);
    echo $OUTPUT->footer();
    exit;
}

// Step 2: preview of the selection and the decision, then an explicit confirmation.
if ($decision !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    if (!$userids) {
        echo $OUTPUT->notification(get_string('recovernoselection', 'tool_flexaccess'), 'warning');
        echo $OUTPUT->continue_button($pageurl);
        echo $OUTPUT->footer();
        exit;
    }
    $snapshots = recovery::snapshots($userids, null);
    echo html_writer::tag('p', get_string('lockreviewdecision_' . $decision . '_desc', 'tool_flexaccess'));
    if ($decision === 'flexaccessrecover' && $reactivateall) {
        echo html_writer::tag('p', get_string('lockreviewreactivateall', 'tool_flexaccess'));
    }
    $names = [];
    foreach ($userids as $id) {
        $names[] = isset($snapshots[$id]) ? s($snapshots[$id]->fullname) : (string) $id;
    }
    echo html_writer::alist($names);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo tool_flexaccess_lockreview_hidden($userids, $decision, (int) $reactivateall);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('lockreviewconfirm', 'tool_flexaccess', count($userids)),
    ]);
    echo ' ' . html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// Step 1: the list of undecided accounts with the batch decision.
$all = reconciliation::lock_review_userids();
$total = count($all);
echo html_writer::tag('p', get_string('lockreviewintro', 'tool_flexaccess'));
if (!$total) {
    echo $OUTPUT->notification(get_string('lockreviewnone', 'tool_flexaccess'), 'success');
    echo $OUTPUT->footer();
    exit;
}
$pageids = array_slice($all, $page * $perpage, $perpage);
$snapshots = recovery::snapshots($pageids, null);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$table = new html_table();
$table->attributes['class'] = 'generaltable flexaccess-lockreview';
$table->head = [
    html_writer::tag('span', get_string('select'), ['class' => 'sr-only visually-hidden']),
    get_string('accountname', 'tool_flexaccess'),
    get_string('accounttype', 'tool_flexaccess'),
    get_string('accountstate', 'tool_flexaccess'),
    get_string('accountexpires', 'tool_flexaccess'),
    get_string('enrolmentstatus', 'tool_flexaccess'),
];
foreach ($pageids as $id) {
    if (!isset($snapshots[$id])) {
        continue;
    }
    $snapshot = $snapshots[$id];
    $table->data[] = [
        html_writer::checkbox(
            'userids[]',
            $id,
            (bool) $selectall,
            html_writer::tag('span', get_string('selectuser', 'tool_flexaccess', s($snapshot->fullname)), [
                'class' => 'sr-only visually-hidden',
            ])
        ),
        html_writer::link(
            new moodle_url('/admin/tool/flexaccess/user.php', ['userid' => $id]),
            $snapshot->fullname !== '' ? s($snapshot->fullname) : s($snapshot->email)
        ),
        \tool_flexaccess\local\account_labels::type($snapshot->accounttype),
        \tool_flexaccess\local\account_labels::state($snapshot->accountstate),
        $snapshot->timeexpires > 0 ? userdate($snapshot->timeexpires) : get_string('never'),
        get_string('lockreviewcourses', 'tool_flexaccess', count($snapshot->enrolments)),
    ];
}
echo html_writer::div(
    html_writer::link(new moodle_url($pageurl, ['page' => $page, 'selectall' => 1]), get_string('selectall')) . ' | ' .
    html_writer::link(new moodle_url($pageurl, ['page' => $page]), get_string('deselectall')),
    'mb-2'
);
echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);

// The decision for the whole selection.
echo html_writer::start_tag('fieldset', ['class' => 'border p-2 mb-3']);
echo html_writer::tag('legend', get_string('lockreviewdecision', 'tool_flexaccess'), ['class' => 'w-auto h6']);
foreach ($decisions as $i => $option) {
    echo html_writer::div(
        html_writer::empty_tag('input', [
            'type' => 'radio',
            'name' => 'decision',
            'value' => $option,
            'id' => 'lockdecision' . $i,
            'required' => 'required',
        ]) . ' ' . html_writer::label(get_string('lockreviewdecision_' . $option, 'tool_flexaccess'), 'lockdecision' . $i),
        'form-check'
    );
}
echo html_writer::div(
    html_writer::checkbox(
        'reactivateall',
        1,
        false,
        get_string('lockreviewreactivateoption', 'tool_flexaccess')
    ),
    'mt-2'
);
echo html_writer::end_tag('fieldset');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('lockreviewpreview', 'tool_flexaccess'),
]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
