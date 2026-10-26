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
 * LTI 1.3 identities next to FlexAccess: merges to review, probable duplicates, bindings, provisioning modes.
 *
 * The reviewed merge workflow of auth_flexaccess #13: an administrator confirms that two durable
 * accounts belong to the same person (never by e-mail alone), the merge itself runs in tool_mergeusers
 * (in the supported direction: the LTI account into the FlexAccess account), and FlexAccess checks its
 * postconditions inside that merge. Merges whose postconditions fail are listed here with what is
 * missing, and can be repaired (repoint LTI bindings and memberships) and checked again.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\navigation;

$action = optional_param('action', '', PARAM_ALPHA);
$fromid = optional_param('fromid', 0, PARAM_INT);
$toid = optional_param('toid', 0, PARAM_INT);
$bindingid = optional_param('bindingid', 0, PARAM_INT);
$evidenceid = optional_param('evidenceid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:recoversystem', $context);

$pageurl = new moodle_url('/admin/tool/flexaccess/ltiidentities.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tabltiidentities', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

if (!in_array($action, ['', 'review', 'approve', 'acknowledge', 'discardenrolment', 'recheck', 'repoint', 'removebinding'], true)) {
    throw new moodle_exception('invalidrequest', 'error');
}

/**
 * A user's name, linked to their profile, or the id when the user is gone.
 *
 * @param int $userid User id.
 * @return string HTML.
 */
function tool_flexaccess_ltiidentities_user(int $userid): string {
    $user = core_user::get_user($userid);
    if (!$user || $user->deleted) {
        return get_string('ltiidentitiesdeleteduser', 'tool_flexaccess', $userid);
    }
    return html_writer::link(new moodle_url('/user/profile.php', ['id' => $userid]), s(fullname($user)))
        . html_writer::div(s($user->auth) . ' · #' . $userid, 'small text-muted');
}

/**
 * The merge-relevant facts of one identity as a short list.
 *
 * @param stdClass $identity Facts from auth_flexaccess merge_guard::identity().
 * @return string HTML.
 */
function tool_flexaccess_ltiidentities_facts(stdClass $identity): string {
    $facts = [
        get_string('ltiidentitiesfactauth', 'tool_flexaccess', s($identity->auth)),
        get_string('ltiidentitiesfactstate', 'tool_flexaccess', $identity->accountstate !== ''
            ? \tool_flexaccess\local\account_labels::state($identity->accountstate) : '-'),
        get_string('ltiidentitiesfactpassword', 'tool_flexaccess', $identity->haspassword ? get_string('yes') : get_string('no')),
        get_string('ltiidentitiesfactbindings', 'tool_flexaccess', $identity->bindings),
        get_string('ltiidentitiesfactltiusers', 'tool_flexaccess', $identity->ltiusers),
        get_string('ltiidentitiesfactgrades', 'tool_flexaccess', $identity->grades),
        get_string('ltiidentitiesfactenrolments', 'tool_flexaccess', $identity->enrolments),
    ];
    if ($identity->suspended) {
        $facts[] = get_string('ltiidentitiesfactsuspended', 'tool_flexaccess');
    }
    return html_writer::alist($facts, ['class' => 'list-unstyled small mb-0']);
}

/**
 * Localised reasons of a registry row.
 *
 * @param string|null $reasons Comma-separated reason codes.
 * @return string HTML.
 */
function tool_flexaccess_ltiidentities_reasons(?string $reasons): string {
    $out = [];
    foreach (array_filter(explode(',', (string) $reasons)) as $code) {
        $key = 'ltimergereason_' . $code;
        $out[] = get_string_manager()->string_exists($key, 'tool_flexaccess') ? get_string($key, 'tool_flexaccess') : s($code);
    }
    return $out ? html_writer::alist($out, ['class' => 'mb-0']) : '-';
}

// Actions change identities: POST, sesskey and the user-update capability on top of the page's capability.
if (in_array($action, ['approve', 'acknowledge', 'discardenrolment', 'recheck', 'repoint', 'removebinding'], true)) {
    // Identity changes: POST only, before any side effect, then sesskey and capability (tool #14).
    \tool_flexaccess\local\request::require_post();
    require_sesskey();
    require_capability('moodle/user:update', $context);
    $message = '';
    $type = \core\output\notification::NOTIFY_SUCCESS;
    if ($action === 'approve') {
        $verified = optional_param('ownershipverified', 0, PARAM_BOOL);
        $proof = optional_param('prooftype', '', PARAM_ALPHA);
        $evidence = optional_param('proofevidence', '', PARAM_TEXT);
        if (\auth_flexaccess\api::approve_merge($fromid, $toid, (bool) $verified, $proof, $evidence)) {
            $message = get_string('ltimergeapproved', 'tool_flexaccess');
        } else {
            $message = get_string('ltimergeapprovalrefused', 'tool_flexaccess');
            $type = \core\output\notification::NOTIFY_ERROR;
        }
    } else if ($action === 'acknowledge') {
        $result = \auth_flexaccess\api::acknowledge_merge_conflicts($fromid, $toid);
        $message = get_string('ltimergeacknowledged', 'tool_flexaccess', (object) [
            'count' => $result->acknowledged,
            'status' => s($result->status),
        ]);
        $type = $result->reasons ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
    } else if ($action === 'discardenrolment') {
        // One recorded enrolment, with its own reason; nothing else of the pair is confirmed by this.
        $reason = optional_param('decisionreason', '', PARAM_TEXT);
        $result = \auth_flexaccess\api::discard_merge_enrolment($evidenceid, $fromid, $toid, $reason);
        if ($result->problem === null) {
            $message = get_string('ltimergeenrolmentdiscarded', 'tool_flexaccess', s($result->status));
            $type = $result->reasons ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
        } else {
            $message = get_string('ltimergeenrolmentnotdiscarded_' . $result->problem, 'tool_flexaccess');
            $type = \core\output\notification::NOTIFY_ERROR;
        }
        redirect(new moodle_url($pageurl, ['action' => 'review', 'fromid' => $fromid, 'toid' => $toid]), $message, null, $type);
    } else if ($action === 'recheck') {
        $result = \auth_flexaccess\api::recheck_merge($fromid, $toid);
        $message = get_string('ltimergerechecked', 'tool_flexaccess', s($result->status));
        $type = $result->reasons ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
    } else if ($action === 'repoint') {
        $verified = (bool) optional_param('ownershipverified', 0, PARAM_BOOL);
        $result = \auth_flexaccess\api::repoint_merged_lti_identity($fromid, $toid, $verified);
        if ($result->status === 'repointed' || $result->status === 'nothingtodo') {
            $message = get_string('ltimergerepointed', 'tool_flexaccess', (object) [
                'bindings' => $result->bindings,
                'ltiusers' => $result->ltiusers,
            ]);
            $type = $result->reasons ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
        } else {
            // Refused or conflicting: nothing was changed.
            $message = get_string('ltimergerepointrefused', 'tool_flexaccess', implode('; ', array_map(
                static fn(string $code): string =>
                    get_string_manager()->string_exists('ltirepointreason_' . $code, 'tool_flexaccess')
                    ? get_string('ltirepointreason_' . $code, 'tool_flexaccess') : s($code),
                $result->reasons
            )));
            $type = \core\output\notification::NOTIFY_ERROR;
        }
    } else if ($action === 'removebinding') {
        $removed = \auth_flexaccess\api::remove_lti_binding($bindingid);
        $message = get_string($removed ? 'ltibindingremoved' : 'ltibindingnotremoved', 'tool_flexaccess');
        $type = $removed ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR;
    }
    redirect($pageurl, $message, null, $type);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tabltiidentities', 'tool_flexaccess'));
echo navigation::render_system(navigation::STATUS, 'ltiidentities');
echo html_writer::tag('p', get_string('ltiidentitiesintro', 'tool_flexaccess'));

$mergeusers = core_component::get_component_directory('tool_mergeusers') !== null;
$button = static function (
    string $action,
    array $params,
    string $label,
    string $class = 'btn btn-secondary btn-sm'
) use ($pageurl): string {
    $html = html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'd-inline']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    foreach ($params as $name => $value) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => $label, 'class' => $class]);
    return $html . html_writer::end_tag('form');
};

// Review step of one pair: both accounts side by side, origins, login routes, conflicts and the approval.
if ($action === 'review' && $fromid > 0 && $toid > 0) {
    $assessment = \auth_flexaccess\api::assess_merge($fromid, $toid);
    $table = new html_table();
    $table->head = [
        get_string('ltimergefrom', 'tool_flexaccess'),
        get_string('ltimergeto', 'tool_flexaccess'),
    ];
    $origin = static fn(string $code): string => get_string(
        'ltimergeorigin',
        'tool_flexaccess',
        get_string('ltimergeorigin_' . $code, 'tool_flexaccess')
    );
    $table->data[] = [
        tool_flexaccess_ltiidentities_user($fromid) . html_writer::div($origin($assessment->origins['from']), 'small')
            . tool_flexaccess_ltiidentities_facts($assessment->from),
        tool_flexaccess_ltiidentities_user($toid) . html_writer::div($origin($assessment->origins['to']), 'small')
            . tool_flexaccess_ltiidentities_facts($assessment->to),
    ];
    echo html_writer::table($table);
    if (!$assessment->involved) {
        echo $OUTPUT->notification(get_string('ltimergenotinvolved', 'tool_flexaccess'), 'info');
        echo $OUTPUT->footer();
        exit;
    }
    // What the merge does to the person's logins: the survivor's route stays, the source's ends.
    $route = static fn(string $code): string => get_string('ltimergeroute_' . $code, 'tool_flexaccess');
    echo html_writer::tag('p', get_string('ltimergeroutekept', 'tool_flexaccess', $route($assessment->keptroute)));
    foreach ($assessment->lostroutes as $lost) {
        echo $OUTPUT->notification(get_string('ltimergeroutelost', 'tool_flexaccess', $route($lost)), 'warning');
    }
    if ($assessment->conflicts) {
        $items = [];
        foreach ($assessment->conflicts as $conflicttable => $keys) {
            $items[] = get_string('ltimergeconflict', 'tool_flexaccess', (object) [
                'table' => s($conflicttable),
                'keys' => s(implode(', ', $keys)),
            ]);
        }
        echo html_writer::tag('p', get_string('ltimergeconflictsintro', 'tool_flexaccess'))
            . html_writer::alist($items);
    }
    // Evidence captured during the merge (both users' records before tool_mergeusers resolved them).
    $captured = \auth_flexaccess\api::merge_conflict_evidence($fromid, $toid);
    if ($captured) {
        echo $OUTPUT->heading(get_string('ltimergeevidencecaptured', 'tool_flexaccess'), 4);
        $evidencetable = new html_table();
        $evidencetable->head = [
            get_string('ltimergeevidencetable', 'tool_flexaccess'),
            get_string('ltimergefrom', 'tool_flexaccess'),
            get_string('ltimergeto', 'tool_flexaccess'),
            get_string('ltimergestatus', 'tool_flexaccess'),
        ];
        // Archived columns only (never credentials); rows of earlier versions are reduced again for display.
        $rows = static fn(\stdClass $record, string $side): string => html_writer::tag(
            'pre',
            s((string) json_encode(\auth_flexaccess\local\merge_evidence::rows($record, $side), JSON_PRETTY_PRINT)),
            ['class' => 'small mb-0']
        );
        foreach ($captured as $record) {
            $evidencetable->data[] = [
                s($record->tablename . ' ' . ($record->keyfields ?? '') . ' = ' . ($record->keyvalues ?? '')),
                $rows($record, 'sourcerows'),
                $rows($record, 'targetrows'),
                (int) $record->timeacknowledged > 0
                    ? get_string('ltimergeevidenceacknowledged', 'tool_flexaccess', userdate((int) $record->timeacknowledged))
                    : get_string('ltimergeevidenceopen', 'tool_flexaccess'),
            ];
        }
        echo html_writer::table($evidencetable);
    }
    // Recorded FlexAccess enrolments without a decision: each one is merged by a re-check once the survivor's
    // enrolment exists again, or discarded on its own with a reason - never by the general confirmation.
    $openenrolments = \auth_flexaccess\api::merge_open_enrolments($fromid, $toid);
    if ($openenrolments) {
        echo $OUTPUT->heading(get_string('ltimergeenrolments', 'tool_flexaccess'), 4);
        echo html_writer::tag('p', get_string('ltimergeenrolmentsintro', 'tool_flexaccess'));
        $state = static function (?\stdClass $row): string {
            if (!$row) {
                return get_string('ltimergeenrolmentnone', 'tool_flexaccess');
            }
            return get_string('ltimergeenrolmentstate', 'tool_flexaccess', (object) [
                'status' => (int) $row->status === 0 ? get_string('active') : get_string('suspended'),
                'start' => (int) $row->timestart ? userdate((int) $row->timestart) : '-',
                'end' => (int) $row->timeend ? userdate((int) $row->timeend)
                    : get_string('ltimergeenrolmentnoend', 'tool_flexaccess'),
            ]);
        };
        $enroltable = new html_table();
        $enroltable->head = [
            get_string('ltimergeenrolmentinstance', 'tool_flexaccess'),
            get_string('ltimergefrom', 'tool_flexaccess'),
            get_string('ltimergeto', 'tool_flexaccess'),
            get_string('actions'),
        ];
        foreach ($openenrolments as $record) {
            $source = \auth_flexaccess\local\merge_evidence::rows($record, 'sourcerows')[0] ?? null;
            $target = \auth_flexaccess\local\merge_evidence::rows($record, 'targetrows')[0] ?? null;
            if (!$record->pending) {
                $decision = html_writer::span(get_string('ltimergeenrolmentwaiting', 'tool_flexaccess'), 'small');
            } else {
                $formid = 'discard-' . (int) $record->id;
                $decision = html_writer::start_tag('form', [
                    'method' => 'post',
                    'action' => $pageurl->out(false),
                    'data-evidenceid' => (int) $record->id,
                ]);
                $hidden = [
                    'sesskey' => sesskey(),
                    'action' => 'discardenrolment',
                    'evidenceid' => (int) $record->id,
                    'fromid' => $fromid,
                    'toid' => $toid,
                ];
                foreach ($hidden as $name => $value) {
                    $decision .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                }
                $decision .= html_writer::span(get_string('ltimergeenrolmentpending', 'tool_flexaccess'), 'small d-block');
                $decision .= html_writer::label(get_string('ltimergeenrolmentreason', 'tool_flexaccess'), $formid, true);
                $decision .= html_writer::tag('textarea', '', [
                    'name' => 'decisionreason',
                    'id' => $formid,
                    'rows' => 2,
                    'cols' => 40,
                    'required' => 'required',
                    'minlength' => \auth_flexaccess\local\merge_registry::EVIDENCE_MIN_LENGTH,
                    'class' => 'form-control mb-1',
                ]);
                $decision .= html_writer::empty_tag('input', [
                    'type' => 'submit',
                    'value' => get_string('ltimergeenrolmentdiscard', 'tool_flexaccess'),
                    'class' => 'btn btn-secondary btn-sm',
                ]);
                $decision .= html_writer::end_tag('form');
            }
            $enroltable->data[] = [(int) $record->conflictkey, $state($source), $state($target), $decision];
        }
        echo html_writer::table($enroltable);
    }
    foreach ($assessment->warnings as $warning) {
        echo $OUTPUT->notification(get_string('ltimergereason_' . $warning, 'tool_flexaccess'), 'warning');
    }
    $other = array_diff($assessment->blockers, ['needsapproval']);
    if ($other) {
        foreach ($other as $blocker) {
            echo $OUTPUT->notification(get_string('ltimergereason_' . $blocker, 'tool_flexaccess'), 'error');
        }
    } else {
        echo html_writer::tag('p', get_string('ltimergereviewintro', 'tool_flexaccess'));
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approve']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fromid', 'value' => $fromid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'toid', 'value' => $toid]);
        $proofs = [];
        foreach (\auth_flexaccess\local\merge_registry::PROOFS as $proof) {
            $proofs[$proof] = get_string('ltimergeproof_' . $proof, 'tool_flexaccess');
        }
        echo html_writer::div(
            html_writer::label(get_string('ltimergeproof', 'tool_flexaccess'), 'id_prooftype', true, ['class' => 'd-block'])
                . html_writer::select($proofs, 'prooftype', '', ['' => 'choosedots'], [
                    'id' => 'id_prooftype',
                    'required' => 'required',
                ]),
            'mb-2'
        );
        echo html_writer::div(
            html_writer::label(
                get_string('ltimergeproofevidence', 'tool_flexaccess'),
                'id_proofevidence',
                true,
                ['class' => 'd-block']
            )
                . html_writer::tag('textarea', '', [
                    'name' => 'proofevidence',
                    'id' => 'id_proofevidence',
                    'rows' => 3,
                    'cols' => 60,
                    'required' => 'required',
                    'minlength' => \auth_flexaccess\local\merge_registry::EVIDENCE_MIN_LENGTH,
                    'class' => 'form-control',
                ])
                . html_writer::div(get_string('ltimergeproofevidencehelp', 'tool_flexaccess'), 'form-text text-muted'),
            'mb-2'
        );
        echo html_writer::div(html_writer::checkbox(
            'ownershipverified',
            1,
            false,
            get_string('ltimergeownership', 'tool_flexaccess')
        ), 'mb-2');
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => get_string('ltimergeapprove', 'tool_flexaccess'),
        ]);
        echo ' ' . html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
    }
    echo $OUTPUT->footer();
    exit;
}

// 0. Review any pair of accounts (FlexAccess, local, LTI or institutional) before merging them.
echo $OUTPUT->heading(get_string('ltimergepair', 'tool_flexaccess'), 3);
echo html_writer::tag('p', get_string('ltimergepairintro', 'tool_flexaccess'));
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out(false), 'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'review']);
foreach (['fromid' => 'ltimergepairfrom', 'toid' => 'ltimergepairto'] as $name => $label) {
    echo html_writer::label(get_string($label, 'tool_flexaccess'), 'id_pair' . $name, true, ['class' => 'mr-1 me-1']);
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => 1,
        'name' => $name,
        'id' => 'id_pair' . $name,
        'required' => 'required',
        'class' => 'form-control mr-2 me-2',
    ]);
}
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-secondary',
    'value' => get_string('ltimergereview', 'tool_flexaccess'),
]);
echo html_writer::end_tag('form');

// 1. Registered merges that need an administrator.
echo $OUTPUT->heading(get_string('ltiidentitiesmerges', 'tool_flexaccess'), 3);
$open = \auth_flexaccess\api::open_merges(0);
if (!$open) {
    echo html_writer::tag('p', get_string('ltiidentitiesnomerges', 'tool_flexaccess'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('ltimergefrom', 'tool_flexaccess'),
        get_string('ltimergeto', 'tool_flexaccess'),
        get_string('ltimergestatus', 'tool_flexaccess'),
        get_string('ltimergeevidence', 'tool_flexaccess'),
        get_string('ltimergereasons', 'tool_flexaccess'),
        get_string('actions'),
    ];
    foreach ($open as $row) {
        $params = ['fromid' => $row->fromuserid, 'toid' => $row->touserid];
        $actions = [$button('recheck', $params, get_string('ltimergerecheck', 'tool_flexaccess'))];
        $evidence = \auth_flexaccess\api::merge_evidence((int) $row->fromuserid, (int) $row->touserid);
        if (str_contains((string) $row->reasons, 'conflictsrecorded')) {
            // Conflicting course work was kept as evidence: the administrator confirms having reviewed it.
            $conflicts = \auth_flexaccess\api::merge_conflict_evidence((int) $row->fromuserid, (int) $row->touserid);
            $actions[] = html_writer::span(get_string('ltimergeconflictsrecorded', 'tool_flexaccess', count($conflicts)), 'small')
                . ' ' . $button('acknowledge', $params, get_string('ltimergeacknowledge', 'tool_flexaccess'));
        }
        if (str_contains((string) $row->reasons, 'enrolmentspending')) {
            // Recorded enrolments that could not be merged: decided one by one on the review page.
            $actions[] = html_writer::link(
                new moodle_url($pageurl, $params + ['action' => 'review']),
                get_string('ltimergeenrolmentsdecide', 'tool_flexaccess'),
                ['class' => 'btn btn-secondary btn-sm']
            );
        }
        if (preg_match('/sourcebindings|sourceltiusers/', (string) $row->reasons)) {
            // Re-pointing moves data of the merged-away identity: only offered for a merge tool_mergeusers
            // confirmed, with the explicit ownership confirmation (auth_flexaccess #14).
            $blockers = \auth_flexaccess\api::lti_repoint_blockers((int) $row->fromuserid, (int) $row->touserid);
            if (!$blockers) {
                $form = html_writer::start_tag('form', [
                    'method' => 'post',
                    'action' => $pageurl->out(false),
                    'class' => 'd-inline',
                ]);
                foreach (['sesskey' => sesskey(), 'action' => 'repoint'] + $params as $name => $value) {
                    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                }
                $checkboxid = 'repoint-' . (int) $row->id;
                $form .= html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'name' => 'ownershipverified',
                    'value' => 1,
                    'id' => $checkboxid,
                    'required' => 'required',
                ]);
                $form .= ' ' . html_writer::label(get_string('ltirepointownership', 'tool_flexaccess'), $checkboxid) . ' ';
                $form .= html_writer::empty_tag('input', [
                    'type' => 'submit',
                    'value' => get_string('ltimergerepoint', 'tool_flexaccess'),
                    'class' => 'btn btn-secondary btn-sm',
                ]);
                $actions[] = $form . html_writer::end_tag('form');
            } else {
                $actions[] = html_writer::span(get_string('ltimergerepointunavailable', 'tool_flexaccess', implode('; ', array_map(
                    static fn(string $code): string => get_string('ltirepointreason_' . $code, 'tool_flexaccess'),
                    $blockers
                ))), 'text-muted small');
            }
        }
        if ($row->status === 'preapproved' && $mergeusers) {
            $actions[] = html_writer::link(
                new moodle_url('/admin/tool/mergeusers/index.php'),
                get_string('ltimergeopenmergeusers', 'tool_flexaccess')
            );
        }
        $table->data[] = [
            tool_flexaccess_ltiidentities_user((int) $row->fromuserid),
            tool_flexaccess_ltiidentities_user((int) $row->touserid),
            get_string('ltimergestatus_' . $row->status, 'tool_flexaccess'),
            $evidence ? get_string('ltimergeevidenceconfirmed', 'tool_flexaccess', (object) [
                'logid' => $evidence->logid,
                'time' => userdate($evidence->timeconfirmed),
            ]) : get_string('ltimergeevidencenone', 'tool_flexaccess'),
            tool_flexaccess_ltiidentities_reasons($row->reasons),
            implode(' ', $actions),
        ];
    }
    echo html_writer::table($table);
}

// 2. Probable duplicates (shared e-mail address: a hint for review, never a reason to merge on its own).
echo $OUTPUT->heading(get_string('ltiidentitiesduplicates', 'tool_flexaccess'), 3);
$candidates = \auth_flexaccess\api::lti_duplicate_candidates(0);
if (!$candidates) {
    echo html_writer::tag('p', get_string('ltiidentitiesnoduplicates', 'tool_flexaccess'));
} else {
    echo html_writer::tag('p', get_string('ltiidentitiesduplicatesintro', 'tool_flexaccess'));
    if (!$mergeusers) {
        echo $OUTPUT->notification(get_string('ltiidentitiesnomergeusers', 'tool_flexaccess'), 'info');
    }
    $table = new html_table();
    $table->head = [
        get_string('ltimergefrom', 'tool_flexaccess'),
        get_string('ltimergeto', 'tool_flexaccess'),
        get_string('actions'),
    ];
    foreach ($candidates as $candidate) {
        $review = new moodle_url($pageurl, [
            'action' => 'review',
            'fromid' => $candidate->ltiuserid,
            'toid' => $candidate->flexaccessuserid,
        ]);
        $table->data[] = [
            tool_flexaccess_ltiidentities_user((int) $candidate->ltiuserid),
            tool_flexaccess_ltiidentities_user((int) $candidate->flexaccessuserid),
            html_writer::link($review, get_string('ltimergereview', 'tool_flexaccess'), ['class' => 'btn btn-secondary btn-sm']),
        ];
    }
    echo html_writer::table($table);
}

// 3. Bindings to non-durable FlexAccess identities and to deleted users.
echo $OUTPUT->heading(get_string('ltiidentitiesbindings', 'tool_flexaccess'), 3);
$bindings = array_merge(
    array_map(
        static fn(stdClass $b): stdClass => (object) ((array) $b + ['kind' => 'nondurable']),
        \auth_flexaccess\api::lti_non_durable_bindings(0)
    ),
    array_map(
        static fn(stdClass $b): stdClass => (object) ((array) $b + ['kind' => 'stale', 'reason' => 'deleted']),
        \auth_flexaccess\api::lti_stale_bindings(0)
    )
);
if (!$bindings) {
    echo html_writer::tag('p', get_string('ltiidentitiesnobindings', 'tool_flexaccess'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('ltibindinguser', 'tool_flexaccess'),
        get_string('ltibindingissuer', 'tool_flexaccess'),
        get_string('ltibindingreason', 'tool_flexaccess'),
        get_string('actions'),
    ];
    foreach ($bindings as $binding) {
        $key = 'ltibindingreason_' . $binding->reason;
        $reason = get_string_manager()->string_exists($key, 'tool_flexaccess')
            ? get_string($key, 'tool_flexaccess') : s($binding->reason);
        $table->data[] = [
            tool_flexaccess_ltiidentities_user((int) $binding->userid),
            s($binding->issuer),
            $reason,
            $button('removebinding', ['bindingid' => $binding->bindingid], get_string('ltibindingremove', 'tool_flexaccess')),
        ];
    }
    echo html_writer::table($table);
}

// 4. Published resources whose learner provisioning creates duplicate accounts.
echo $OUTPUT->heading(get_string('ltiidentitiesprovisioning', 'tool_flexaccess'), 3);
$resources = \auth_flexaccess\api::lti_auto_provisioning_resources(0);
if (!$resources) {
    echo html_writer::tag('p', get_string('ltiidentitiesnoautoonly', 'tool_flexaccess'));
} else {
    echo html_writer::tag('p', get_string('health_lti_autoonly', 'tool_flexaccess'));
    $items = [];
    foreach ($resources as $resource) {
        $items[] = html_writer::link(
            new moodle_url('/enrol/lti/index.php', ['courseid' => $resource->courseid]),
            \tool_flexaccess\local\user_presenter::course_name((int) $resource->courseid)
        );
    }
    echo html_writer::alist(array_values(array_unique($items)));
}

echo $OUTPUT->footer();
