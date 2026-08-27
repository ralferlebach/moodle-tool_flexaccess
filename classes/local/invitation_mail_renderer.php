<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_flexaccess\local;

/**
 * Renders invitation mails inside the central FlexAccess mail queue worker.
 *
 * Invitations must pass through the shared queue so they are subject to the hourly send limit,
 * retry/backoff and queue monitoring - but their body contains a single-use bearer token, which
 * must never be stored at rest. Both requirements are met by queueing a *secret-free* row that
 * only names this renderer plus the invitation id: the token is minted here, milliseconds before
 * delivery, and exists only in the outgoing message.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class invitation_mail_renderer {
    /** Context kind for a first invitation mail. */
    public const KIND_INVITE = 'invite';

    /** Context kind for a reminder mail. */
    public const KIND_REMINDER = 'reminder';

    /**
     * Render subject and bodies for a queued invitation mail.
     *
     * Called by the auth mail worker immediately before sending. Issuing the token here also
     * rotates it, so a reminder invalidates any earlier link - the invitation keeps only the hash.
     *
     * @param array $context Queue context: invitationid and kind.
     * @param int $now Current time.
     * @return array{0:string,1:string,2:string} Subject, plain body, HTML body (empty on failure).
     */
    public static function render_deferred_mail(array $context, int $now): array {
        $id = (int) ($context['invitationid'] ?? 0);
        $kind = (string) ($context['kind'] ?? self::KIND_INVITE);
        $invite = $id ? invitation::get($id) : null;
        if (!$invite || $invite->status !== invitation::STATUS_PENDING) {
            // Accepted, revoked or deleted meanwhile: nothing to send, and no token is minted.
            return ['', '', ''];
        }
        if ((int) $invite->timeexpires > 0 && $now > (int) $invite->timeexpires) {
            return ['', '', ''];
        }

        $token = invitation::issue_token($id);
        $link = (new \moodle_url('/admin/tool/flexaccess/invite.php', ['token' => $token]))->out(false);
        $subjectkey = $kind === self::KIND_REMINDER ? 'inviteremindersubject' : 'inviteemailsubject';
        $bodykey = $kind === self::KIND_REMINDER ? 'invitereminderbody' : 'inviteemailbody';

        return [
            get_string($subjectkey, 'tool_flexaccess'),
            get_string($bodykey, 'tool_flexaccess', $link),
            \html_writer::tag('p', get_string($bodykey, 'tool_flexaccess', \html_writer::link($link, $link))),
        ];
    }

    /**
     * Record that the queued invitation mail was actually delivered.
     *
     * Keeps "sent" honest: the timestamps reflect real delivery, not the moment of queueing, so a
     * failed send never leaves an invitation claiming it was sent.
     *
     * @param array $context Queue context: invitationid and kind.
     * @param int $now Current time.
     * @return void
     */
    public static function deferred_mail_sent(array $context, int $now): void {
        global $DB;
        $id = (int) ($context['invitationid'] ?? 0);
        if (!$id || !invitation::get($id)) {
            return;
        }
        // The token that went out in this mail becomes the live one only now, on confirmed delivery.
        invitation::promote_pending_token($id);
        if ((string) ($context['kind'] ?? '') === self::KIND_REMINDER) {
            $invite = invitation::get($id);
            $DB->set_field('tool_flexaccess_invite', 'timereminded', $now, ['id' => $id]);
            $DB->set_field(
                'tool_flexaccess_invite',
                'remindercount',
                (int) $invite->remindercount + 1,
                ['id' => $id]
            );
            return;
        }
        $DB->set_field('tool_flexaccess_invite', 'timesent', $now, ['id' => $id]);
    }
}
