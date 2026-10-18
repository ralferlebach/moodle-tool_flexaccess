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

if (!in_array($action, ['', 'review', 'approve', 'recheck', 'repoint', 'removebinding'], true)) {
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
if (in_array($action, ['approve', 'recheck', 'repoint', 'removebinding'], true)) {
    require_sesskey();
    require_capability('moodle/user:update', $context);
    $message = '';
    $type = \core\output\notification::NOTIFY_SUCCESS;
    if ($action === 'approve') {
        $verified = optional_param('ownershipverified', 0, PARAM_BOOL);
        if (\auth_flexaccess\api::approve_merge($fromid, $toid, (bool) $verified)) {
            $message = get_string('ltimergeapproved', 'tool_flexaccess');
        } else {
            $message = get_string('ltimergeapprovalrefused', 'tool_flexaccess');
            $type = \core\output\notification::NOTIFY_ERROR;
        }
    } else if ($action === 'recheck') {
        $result = \auth_flexaccess\api::recheck_merge($fromid, $toid);
        $message = get_string('ltimergerechecked', 'tool_flexaccess', s($result->status));
        $type = $result->reasons ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
    } else if ($action === 'repoint') {
        $result = \auth_flexaccess\api::repoint_merged_lti_identity($fromid, $toid);
        $message = get_string('ltimergerepointed', 'tool_flexaccess', (object) [
            'bindings' => $result->bindings,
            'ltiusers' => $result->ltiusers,
            'conflicts' => count($result->conflicts),
        ]);
        $type = $result->reasons ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
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

// Review step of one probable duplicate: both accounts side by side and the explicit confirmation.
if ($action === 'review' && $fromid > 0 && $toid > 0) {
    $assessment = \auth_flexaccess\api::assess_merge($fromid, $toid);
    $table = new html_table();
    $table->head = [
        get_string('ltimergefrom', 'tool_flexaccess'),
        get_string('ltimergeto', 'tool_flexaccess'),
    ];
    $table->data[] = [
        tool_flexaccess_ltiidentities_user($fromid) . tool_flexaccess_ltiidentities_facts($assessment->from),
        tool_flexaccess_ltiidentities_user($toid) . tool_flexaccess_ltiidentities_facts($assessment->to),
    ];
    echo html_writer::table($table);
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
        get_string('ltimergereasons', 'tool_flexaccess'),
        get_string('actions'),
    ];
    foreach ($open as $row) {
        $params = ['fromid' => $row->fromuserid, 'toid' => $row->touserid];
        $actions = [$button('recheck', $params, get_string('ltimergerecheck', 'tool_flexaccess'))];
        if (preg_match('/sourcebindings|sourceltiusers/', (string) $row->reasons)) {
            $actions[] = $button('repoint', $params, get_string('ltimergerepoint', 'tool_flexaccess'));
        }
        if ($row->status === 'approved' && $mergeusers) {
            $actions[] = html_writer::link(
                new moodle_url('/admin/tool/mergeusers/index.php'),
                get_string('ltimergeopenmergeusers', 'tool_flexaccess')
            );
        }
        $table->data[] = [
            tool_flexaccess_ltiidentities_user((int) $row->fromuserid),
            tool_flexaccess_ltiidentities_user((int) $row->touserid),
            get_string('ltimergestatus_' . $row->status, 'tool_flexaccess'),
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
