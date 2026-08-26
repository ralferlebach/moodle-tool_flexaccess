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
 * Administrative management of person-bound FlexAccess invitations.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\invitation;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:manageinvitations', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/invitations.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('invitations', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$returnurl = new moodle_url('/admin/tool/flexaccess/invitations.php');

if (in_array($action, ['send', 'remind', 'revoke'], true) && $id > 0 && confirm_sesskey()) {
    if ($action === 'send') {
        invitation::send($id);
        redirect(
            $returnurl,
            get_string('invite:sent', 'tool_flexaccess'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else if ($action === 'remind') {
        $done = invitation::remind($id);
        redirect(
            $returnurl,
            get_string($done ? 'invite:reminded' : 'invite:remindfailed', 'tool_flexaccess'),
            null,
            $done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
        );
    } else {
        $revoked = invitation::revoke($id);
        redirect(
            $returnurl,
            get_string($revoked ? 'invite:revoked' : 'invite:revokefailed', 'tool_flexaccess'),
            null,
            $revoked ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
        );
    }
}

$form = new \tool_flexaccess\form\invitation_form(
    new moodle_url($returnurl, ['action' => 'new'])
);

if ($action === 'new') {
    if ($form->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $form->get_data()) {
        $now = time();
        $expiry = (int) ($data->expiry ?? 0);
        $timeexpires = $expiry > 0 ? $now + $expiry : 0;
        $created = 0;
        $seen = [];
        foreach (preg_split('/[\s,;]+/', (string) $data->emails, -1, PREG_SPLIT_NO_EMPTY) as $email) {
            $email = \core_text::strtolower(trim($email));
            if (!validate_email($email) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $inviteid = invitation::create((int) $data->courseid, $email, $timeexpires, null, $now);
            if (!empty($data->sendnow)) {
                invitation::send($inviteid, $now);
            }
            $created++;
        }
        redirect(
            $returnurl,
            get_string('invite:created', 'tool_flexaccess', $created),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('invite:create', 'tool_flexaccess'));
    $form->display();
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('invitations', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('invitations_intro', 'tool_flexaccess'));
echo $OUTPUT->single_button(
    new moodle_url($returnurl, ['action' => 'new']),
    get_string('invite:create', 'tool_flexaccess'),
    'get'
);

$total = invitation::count_all();
$invites = invitation::all($page * $perpage, $perpage);
if ($invites) {
    // Look up display names only for the courses shown on this page.
    $courseids = array_values(array_unique(array_map(static fn($i) => (int) $i->courseid, $invites)));
    $courses = $courseids
        ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname')
        : [];
    $statuslabels = [
        invitation::STATUS_PENDING => get_string('invite:status_pending', 'tool_flexaccess'),
        invitation::STATUS_ACCEPTED => get_string('invite:status_accepted', 'tool_flexaccess'),
        invitation::STATUS_REVOKED => get_string('invite:status_revoked', 'tool_flexaccess'),
        invitation::STATUS_RESERVED => get_string('invite:status_reserved', 'tool_flexaccess'),
    ];
    $table = new html_table();
    $table->head = [
        get_string('invite:recipient', 'tool_flexaccess'),
        get_string('invite:course', 'tool_flexaccess'),
        get_string('invite:statuscol', 'tool_flexaccess'),
        get_string('invite:sentcol', 'tool_flexaccess'),
        get_string('invite:actions', 'tool_flexaccess'),
    ];
    foreach ($invites as $invite) {
        $actions = [];
        if ($invite->status === invitation::STATUS_PENDING) {
            $label = (int) $invite->timesent === 0
                ? get_string('invite:send', 'tool_flexaccess')
                : get_string('invite:resend', 'tool_flexaccess');
            $actions[] = html_writer::link(
                new moodle_url($returnurl, ['action' => 'send', 'id' => $invite->id, 'sesskey' => sesskey()]),
                $label
            );
            if ((int) $invite->timesent > 0) {
                $actions[] = html_writer::link(
                    new moodle_url($returnurl, ['action' => 'remind', 'id' => $invite->id, 'sesskey' => sesskey()]),
                    get_string('invite:remind', 'tool_flexaccess')
                );
            }
            $actions[] = html_writer::link(
                new moodle_url($returnurl, ['action' => 'revoke', 'id' => $invite->id, 'sesskey' => sesskey()]),
                get_string('invite:revoke', 'tool_flexaccess')
            );
        }
        $sent = (int) $invite->timesent > 0 ? userdate((int) $invite->timesent) : '-';
        $table->data[] = [
            s($invite->email),
            isset($courses[$invite->courseid])
                ? format_string($courses[$invite->courseid]->fullname)
                : ('#' . $invite->courseid),
            $statuslabels[$invite->status] ?? $invite->status,
            $sent,
            implode(' · ', $actions),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $returnurl);
} else {
    echo html_writer::tag('p', get_string('invitations_none', 'tool_flexaccess'));
}

echo $OUTPUT->footer();
