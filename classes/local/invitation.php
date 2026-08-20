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
 * Person-bound, single-use FlexAccess invitations.
 *
 * Unlike a shareable {@see campaign} link, an invitation is addressed to one email recipient, carries
 * a single-use token, can expire, and can be resent, reminded and revoked. Accepting an invitation
 * consumes it (it can never be accepted twice). Invitation mail is sent through the FlexAccess mail
 * queue, so it is subject to the same hourly rate limit as all other FlexAccess mail.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class invitation {
    /** Table name. */
    private const TABLE = 'tool_flexaccess_invite';

    /** Pending (not yet accepted or revoked). */
    public const STATUS_PENDING = 'pending';

    /** Accepted (consumed; the recipient registered). */
    public const STATUS_ACCEPTED = 'accepted';

    /** Revoked by an administrator. */
    public const STATUS_REVOKED = 'revoked';

    /**
     * Generate a random URL-safe invitation token.
     *
     * @return string
     */
    public static function generate_token(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Load an invitation by id.
     *
     * @param int $id Invitation id.
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Load an invitation by its token.
     *
     * @param string $token Invitation token.
     * @return \stdClass|null
     */
    public static function get_by_token(string $token): ?\stdClass {
        global $DB;
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        return $DB->get_record(self::TABLE, ['token' => $token]) ?: null;
    }

    /**
     * A page of invitations, newest first.
     *
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size (0 = no limit).
     * @return \stdClass[]
     */
    public static function all(int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timecreated DESC', '*', $limitfrom, $limitnum);
    }

    /**
     * Total number of invitations.
     *
     * @return int
     */
    public static function count_all(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }

    /**
     * Create a pending invitation for a recipient.
     *
     * @param int $courseid Target course id.
     * @param string $email Recipient email.
     * @param int $timeexpires Expiry time (0 = never).
     * @param int|null $campaignid Optional owning campaign.
     * @param int|null $now Current time.
     * @return int New invitation id.
     */
    public static function create(
        int $courseid,
        string $email,
        int $timeexpires = 0,
        ?int $campaignid = null,
        ?int $now = null
    ): int {
        global $DB, $USER;
        $now = $now ?? time();
        return (int) $DB->insert_record(self::TABLE, (object) [
            'courseid' => $courseid,
            'campaignid' => $campaignid,
            'email' => \core_text::strtolower(trim($email)),
            'token' => self::unique_token(),
            'status' => self::STATUS_PENDING,
            'timecreated' => $now,
            'timeexpires' => max(0, $timeexpires),
            'timesent' => 0,
            'timeaccepted' => 0,
            'timereminded' => 0,
            'remindercount' => 0,
            'usermodified' => (int) $USER->id,
        ]);
    }

    /**
     * Whether an invitation may still be accepted (pending, not expired, not revoked).
     *
     * @param \stdClass $invite Invitation record.
     * @param int|null $now Current time.
     * @return bool
     */
    public static function is_acceptable(\stdClass $invite, ?int $now = null): bool {
        $now = $now ?? time();
        if ($invite->status !== self::STATUS_PENDING) {
            return false;
        }
        return (int) $invite->timeexpires === 0 || $now <= (int) $invite->timeexpires;
    }

    /**
     * Queue the invitation email (initial send or resend) and mark it sent.
     *
     * @param int $id Invitation id.
     * @param int|null $now Current time.
     * @return bool Whether the mail was queued.
     */
    public static function send(int $id, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $invite = self::get($id);
        if (!$invite || $invite->status !== self::STATUS_PENDING) {
            return false;
        }
        self::queue_mail($invite, 'invite:emailsubject', 'invite:emailbody', $now);
        $DB->set_field(self::TABLE, 'timesent', $now, ['id' => $id]);
        return true;
    }

    /**
     * Queue a reminder for a pending, already-sent, unaccepted invitation.
     *
     * @param int $id Invitation id.
     * @param int|null $now Current time.
     * @return bool Whether a reminder was queued.
     */
    public static function remind(int $id, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $invite = self::get($id);
        if (!$invite || $invite->status !== self::STATUS_PENDING || (int) $invite->timesent === 0) {
            return false;
        }
        if ((int) $invite->timeexpires > 0 && $now > (int) $invite->timeexpires) {
            return false;
        }
        self::queue_mail($invite, 'invite:remindersubject', 'invite:reminderbody', $now);
        $DB->set_field(self::TABLE, 'timereminded', $now, ['id' => $id]);
        $DB->set_field(self::TABLE, 'remindercount', (int) $invite->remindercount + 1, ['id' => $id]);
        return true;
    }

    /**
     * Revoke an invitation so its token can no longer be accepted.
     *
     * @param int $id Invitation id.
     * @return void
     */
    public static function revoke(int $id): void {
        global $DB;
        $invite = self::get($id);
        if ($invite && $invite->status === self::STATUS_PENDING) {
            $DB->set_field(self::TABLE, 'status', self::STATUS_REVOKED, ['id' => $id]);
        }
    }

    /**
     * Atomically accept (consume) an invitation by token.
     *
     * A per-token lock plus a status guard guarantee single use: a second concurrent acceptance of
     * the same token fails. Returns the invitation on success, or null when it is not acceptable.
     *
     * @param string $token Invitation token.
     * @param int|null $now Current time.
     * @return \stdClass|null The consumed invitation, or null.
     */
    public static function accept(string $token, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $invite = self::get_by_token($token);
        if (!$invite) {
            return null;
        }
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_flexaccess_invite');
        $lock = $lockfactory->get_lock('invite_' . $invite->id, 10);
        if (!$lock) {
            return null;
        }
        try {
            $fresh = self::get($invite->id);
            if (!$fresh || !self::is_acceptable($fresh, $now)) {
                return null;
            }
            $DB->set_field(self::TABLE, 'status', self::STATUS_ACCEPTED, ['id' => $fresh->id]);
            $DB->set_field(self::TABLE, 'timeaccepted', $now, ['id' => $fresh->id]);
            $fresh->status = self::STATUS_ACCEPTED;
            $fresh->timeaccepted = $now;
            return $fresh;
        } finally {
            $lock->release();
        }
    }

    /**
     * Ids of invitations due for an automatic reminder: pending, already sent before the cutoff,
     * not yet reminded, and not expired.
     *
     * @param int $sentcutoff Only invitations sent at or before this time.
     * @param int $now Current time.
     * @param int $limit Maximum ids to return (0 = no limit).
     * @return int[]
     */
    public static function due_reminders(int $sentcutoff, int $now, int $limit = 0): array {
        global $DB;
        $select = "status = :status AND timesent > 0 AND timesent <= :cutoff AND remindercount = 0 "
            . "AND (timeexpires = 0 OR timeexpires > :now)";
        $params = ['status' => self::STATUS_PENDING, 'cutoff' => $sentcutoff, 'now' => $now];
        return array_map('intval', $DB->get_fieldset_select(self::TABLE, 'id', $select, $params, 0, $limit));
    }

    /**
     * Queue an invitation mail (rendered with the acceptance link) through the FlexAccess queue.
     *
     * Uses the auth_flexaccess public mail API rather than writing to its table directly, so the
     * cross-plugin coupling stays behind the sanctioned boundary and honours the queue's rate limit.
     *
     * @param \stdClass $invite Invitation record.
     * @param string $subjectkey Lang key for the subject.
     * @param string $bodykey Lang key for the body (receives the link).
     * @param int $now Current time.
     * @return void
     */
    private static function queue_mail(\stdClass $invite, string $subjectkey, string $bodykey, int $now): void {
        if (!class_exists('\auth_flexaccess\api')) {
            // The auth plugin (a declared dependency) is not available; nothing can be queued.
            return;
        }
        $link = (new \moodle_url('/admin/tool/flexaccess/invite.php', ['token' => $invite->token]))->out(false);
        $subject = get_string($subjectkey, 'tool_flexaccess');
        $body = get_string($bodykey, 'tool_flexaccess', $link);
        $bodyhtml = \html_writer::tag('p', get_string($bodykey, 'tool_flexaccess', \html_writer::link($link, $link)));
        \auth_flexaccess\api::queue_mail(null, $invite->email, $subject, $body, $bodyhtml, 'invite', $now);
    }

    /**
     * Produce a token guaranteed unique in the table.
     *
     * @return string
     */
    private static function unique_token(): string {
        global $DB;
        do {
            $token = self::generate_token();
        } while ($DB->record_exists(self::TABLE, ['token' => $token]));
        return $token;
    }
}
