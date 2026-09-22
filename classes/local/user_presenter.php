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

namespace tool_flexaccess\local;

/**
 * One table for FlexAccess users, shared by the course view and the system view.
 *
 * Both scopes render account and enrolment state from the same recovery snapshot, so the two views
 * can never disagree about a user. The scope only decides which enrolments are shown (one course or
 * all) and which actions are offered.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_presenter {
    /**
     * Build the table for a set of snapshots.
     *
     * @param \stdClass[] $snapshots Recovery snapshots (keyed by user id).
     * @param int|null $courseid Course scope, or null for the system scope.
     * @param bool $selectable Whether rows get a selection checkbox (batch recovery).
     * @return \html_table
     */
    public static function table(array $snapshots, ?int $courseid, bool $selectable): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable flexaccess-users';
        $head = [];
        if ($selectable) {
            $head[] = \html_writer::tag('span', get_string('select'), ['class' => 'sr-only visually-hidden']);
        }
        $head = array_merge($head, [
            get_string('accountname', 'tool_flexaccess'),
            get_string('accounttype', 'tool_flexaccess'),
            get_string('accountstate', 'tool_flexaccess'),
            get_string('usersuspended', 'tool_flexaccess'),
            get_string('verificationpending', 'tool_flexaccess'),
            get_string('enrolmentstatus', 'tool_flexaccess'),
            get_string('enrolmentend', 'tool_flexaccess'),
            get_string('accessstatus', 'tool_flexaccess'),
            get_string('accountactions', 'tool_flexaccess'),
        ]);
        $table->head = $head;
        foreach ($snapshots as $snapshot) {
            $table->data[] = self::row($snapshot, $courseid, $selectable);
        }
        return $table;
    }

    /**
     * One table row.
     *
     * @param \stdClass $snapshot Snapshot.
     * @param int|null $courseid Course scope.
     * @param bool $selectable Whether to render the selection checkbox.
     * @return array
     */
    public static function row(\stdClass $snapshot, ?int $courseid, bool $selectable): array {
        $enrolstatus = [];
        $enrolend = [];
        $access = [];
        foreach ($snapshot->enrolments as $enrolment) {
            $prefix = $courseid === null ? format_string(get_course($enrolment->courseid)->shortname) . ': ' : '';
            $enrolstatus[] = $prefix . get_string(
                $enrolment->status === ENROL_USER_ACTIVE ? 'enrolactive' : 'enrolsuspended',
                'tool_flexaccess'
            );
            $enrolend[] = $prefix . ($enrolment->timeend > 0 ? userdate($enrolment->timeend) : get_string('never'));
            $access[] = self::status_badge(recovery::access_status($snapshot, $enrolment));
        }
        foreach ($snapshot->unenrolledcourses as $unenrolled) {
            $prefix = $courseid === null ? format_string(get_course($unenrolled)->shortname) . ': ' : '';
            $enrolstatus[] = $prefix . get_string('enrolremoved', 'tool_flexaccess');
            $enrolend[] = '-';
        }
        if (!$access) {
            $access[] = self::status_badge(recovery::access_status($snapshot, null));
        }
        $name = $snapshot->fullname !== '' ? s($snapshot->fullname) : s($snapshot->email);
        if ($snapshot->reference !== '') {
            $name .= \html_writer::div(
                get_string('accountreference', 'tool_flexaccess', s($snapshot->reference)),
                'small text-muted'
            );
        }
        $row = [];
        if ($selectable) {
            $row[] = \html_writer::checkbox(
                'userids[]',
                $snapshot->userid,
                false,
                \html_writer::tag('span', get_string('selectuser', 'tool_flexaccess', s($snapshot->fullname)), [
                    'class' => 'sr-only visually-hidden',
                ])
            );
        }
        $row[] = $name;
        $row[] = account_labels::type($snapshot->accounttype);
        $row[] = account_labels::state($snapshot->accountstate)
            . ($snapshot->accountstate === 'pendingcredential'
                ? \html_writer::div(get_string('credential_' . $snapshot->credentialstatus, 'tool_flexaccess'), 'small')
                : '');
        $row[] = get_string($snapshot->suspended ? 'yes' : 'no');
        $row[] = get_string($snapshot->verificationpending ? 'yes' : 'no');
        $row[] = implode(\html_writer::empty_tag('br'), $enrolstatus) ?: '-';
        $row[] = implode(\html_writer::empty_tag('br'), $enrolend) ?: '-';
        $row[] = implode(' ', $access);
        $row[] = self::actions($snapshot, $courseid);
        return $row;
    }

    /**
     * Action links for one user in the given scope.
     *
     * @param \stdClass $snapshot Snapshot.
     * @param int|null $courseid Course scope.
     * @return string HTML.
     */
    public static function actions(\stdClass $snapshot, ?int $courseid): string {
        $params = ['userid' => $snapshot->userid];
        if ($courseid !== null) {
            $params['courseid'] = $courseid;
        }
        $links = [\html_writer::link(
            new \moodle_url('/admin/tool/flexaccess/user.php', $params),
            get_string('userdetails', 'tool_flexaccess')
        )];
        if (recovery::can_recover($snapshot, $courseid)) {
            $links[] = \html_writer::link(
                new \moodle_url('/admin/tool/flexaccess/recover.php', $params),
                get_string('recoveraction', 'tool_flexaccess')
            );
        }
        if (
            $courseid === null && $snapshot->accounttype === 'temporary user'
                && has_capability('tool/flexaccess:convertaccounts', \context_system::instance())
        ) {
            $links[] = \html_writer::link(
                new \moodle_url('/admin/tool/flexaccess/convert.php', ['userid' => $snapshot->userid]),
                get_string('accountconvert', 'tool_flexaccess')
            );
        }
        return implode(' | ', $links);
    }

    /**
     * Badge for a combined access status.
     *
     * @param string $status Status code from {@see recovery::access_status()}.
     * @return string HTML.
     */
    public static function status_badge(string $status): string {
        $class = $status === 'ok' ? 'badge-success bg-success' : 'badge-warning bg-warning text-dark';
        return \html_writer::span(get_string('accessstatus_' . $status, 'tool_flexaccess'), 'badge ' . $class);
    }
}
