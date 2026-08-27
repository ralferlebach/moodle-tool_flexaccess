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

    /** Reserved: a registration attempt is in progress (reserve-before-grant). */
    public const STATUS_RESERVED = 'reserved';

    /** Seconds after which a stale reservation may be reclaimed (crashed/abandoned attempt). */
    private const RESERVE_TIMEOUT = 600;

    /**
     * Generate a random URL-safe invitation token (plaintext, never stored).
     *
     * @return string
     */
    public static function generate_token(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Hash a plaintext token for storage/lookup. Only the hash is ever persisted.
     *
     * @param string $token Plaintext token.
     * @return string
     */
    private static function hash_token(string $token): string {
        return hash('sha256', trim($token));
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
     * Load an invitation by its plaintext token (matched against the stored hash).
     *
     * @param string $token Plaintext invitation token.
     * @return \stdClass|null
     */
    public static function get_by_token(string $token): ?\stdClass {
        global $DB;
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        return $DB->get_record(self::TABLE, ['tokenhash' => self::hash_token($token)]) ?: null;
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
            'tokenhash' => self::unique_tokenhash(),
            'status' => self::STATUS_PENDING,
            'timecreated' => $now,
            'timeexpires' => max(0, $timeexpires),
            'timesent' => 0,
            'timereserved' => 0,
            'timeaccepted' => 0,
            'timereminded' => 0,
            'remindercount' => 0,
            'usermodified' => (int) $USER->id,
        ]);
    }

    /**
     * Issue a fresh single-use token for an invitation, persisting only its hash, and return the
     * plaintext for immediate delivery. Re-issuing invalidates any previously sent link.
     *
     * @param int $id Invitation id.
     * @return string Plaintext token (to render into the outgoing mail).
     */
    public static function issue_token(int $id): string {
        global $DB;
        do {
            $token = self::generate_token();
            $hash = self::hash_token($token);
        } while (
            $DB->record_exists(self::TABLE, ['tokenhash' => $hash])
                || $DB->record_exists(self::TABLE, ['pendingtokenhash' => $hash])
        );
        // Parked, not activated: a resend must not invalidate a link that already works. The new
        // token only replaces the live one once the mail has actually been delivered - otherwise a
        // failed resend would leave the recipient with a link that stopped working for nothing, and
        // several queued jobs would each kill the previous mail's link (P0-2).
        $DB->set_field(self::TABLE, 'pendingtokenhash', $hash, ['id' => $id]);
        return $token;
    }

    /**
     * Activate the parked token after its mail was actually delivered.
     *
     * From this moment the previously delivered link stops working - which is correct, because the
     * recipient now holds a newer one.
     *
     * @param int $id Invitation id.
     * @return void
     */
    public static function promote_pending_token(int $id): void {
        global $DB;
        $invite = self::get($id);
        if (!$invite || empty($invite->pendingtokenhash)) {
            return;
        }
        $DB->set_field(self::TABLE, 'tokenhash', $invite->pendingtokenhash, ['id' => $id]);
        $DB->set_field(self::TABLE, 'pendingtokenhash', null, ['id' => $id]);
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
        // Queued secret-free through the central FlexAccess mail queue: the token is minted by
        // invitation_mail_renderer at delivery, and timesent is stamped only on real delivery.
        return self::queue_mail($invite, invitation_mail_renderer::KIND_INVITE, $now);
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
        // Reminder counters are stamped by the worker once the mail has actually gone out.
        return self::queue_mail($invite, invitation_mail_renderer::KIND_REMINDER, $now);
    }

    /**
     * Revoke an invitation so its token can no longer be accepted.
     *
     * Only a PENDING invitation can be revoked. A RESERVED one is mid-acceptance and an ACCEPTED /
     * already-REVOKED one has nothing to revoke, so the caller is told (false) rather than shown a
     * misleading success.
     *
     * @param int $id Invitation id.
     * @return bool Whether the invitation was revoked.
     */
    public static function revoke(int $id): bool {
        global $DB;
        $invite = self::get($id);
        if ($invite && $invite->status === self::STATUS_PENDING) {
            $DB->set_field(self::TABLE, 'status', self::STATUS_REVOKED, ['id' => $id]);
            return true;
        }
        return false;
    }

    /**
     * Reserve an invitation for a registration attempt (reserve-before-grant).
     *
     * Under a per-invitation lock, an acceptable invitation is moved to RESERVED so concurrent
     * attempts cannot use it. A reservation older than {@see RESERVE_TIMEOUT} (a crashed/abandoned
     * attempt) may be reclaimed. On success the caller must {@see commit_acceptance()} or, if the
     * registration fails, {@see release_reservation()} so the invitation returns to PENDING.
     *
     * @param string $token Plaintext invitation token.
     * @param int|null $now Current time.
     * @return \stdClass|null The reserved invitation, or null if it is not usable.
     */
    public static function reserve(string $token, ?int $now = null): ?\stdClass {
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
            if (!$fresh) {
                return null;
            }
            $stalereservation = $fresh->status === self::STATUS_RESERVED
                && (int) $fresh->timereserved > 0
                && $now - (int) $fresh->timereserved > self::RESERVE_TIMEOUT
                && ((int) $fresh->timeexpires === 0 || $now <= (int) $fresh->timeexpires);
            if (!self::is_acceptable($fresh, $now) && !$stalereservation) {
                return null;
            }
            $DB->set_field(self::TABLE, 'status', self::STATUS_RESERVED, ['id' => $fresh->id]);
            $DB->set_field(self::TABLE, 'timereserved', $now, ['id' => $fresh->id]);
            $fresh->status = self::STATUS_RESERVED;
            $fresh->timereserved = $now;
            return $fresh;
        } finally {
            $lock->release();
        }
    }

    /**
     * Finalise a reserved invitation as accepted (registration succeeded).
     *
     * @param int $id Invitation id.
     * @param int|null $now Current time.
     * @return void
     */
    public static function commit_acceptance(int $id, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $invite = self::get($id);
        if ($invite && $invite->status === self::STATUS_RESERVED) {
            $DB->set_field(self::TABLE, 'status', self::STATUS_ACCEPTED, ['id' => $id]);
            $DB->set_field(self::TABLE, 'timeaccepted', $now, ['id' => $id]);
        }
    }

    /**
     * Return a reserved invitation to PENDING (registration failed), so it can be used again.
     *
     * @param int $id Invitation id.
     * @return void
     */
    public static function release_reservation(int $id): void {
        global $DB;
        $invite = self::get($id);
        if ($invite && $invite->status === self::STATUS_RESERVED) {
            $DB->set_field(self::TABLE, 'status', self::STATUS_PENDING, ['id' => $id]);
            $DB->set_field(self::TABLE, 'timereserved', 0, ['id' => $id]);
        }
    }

    /**
     * Atomically reserve and accept an invitation in one step (single-use convenience).
     *
     * @param string $token Plaintext invitation token.
     * @param int|null $now Current time.
     * @return \stdClass|null The accepted invitation, or null.
     */
    public static function accept(string $token, ?int $now = null): ?\stdClass {
        $now = $now ?? time();
        $reserved = self::reserve($token, $now);
        if ($reserved === null) {
            return null;
        }
        self::commit_acceptance((int) $reserved->id, $now);
        $reserved->status = self::STATUS_ACCEPTED;
        $reserved->timeaccepted = $now;
        return $reserved;
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
     * Queue an invitation mail through the central FlexAccess mail queue, secret-free.
     *
     * The queue row names only the renderer and the invitation id - never the token. That keeps the
     * bearer secret out of persistent storage while still routing the mail through the shared
     * hourly send limit, retry/backoff and queue monitoring.
     *
     * @param \stdClass $invite Invitation record.
     * @param string $kind invitation_mail_renderer::KIND_INVITE or KIND_REMINDER.
     * @param int $now Current time.
     * @return bool Whether the mail was queued.
     */
    private static function queue_mail(\stdClass $invite, string $kind, int $now): bool {
        if (!method_exists('\auth_flexaccess\api', 'queue_deferred_mail')) {
            // The auth plugin (a declared dependency, pinned by version) is missing or older than
            // the secret-free deferred queue. Falling back to a plain queued mail is not an option:
            // that would persist the token. Nothing is sent and the caller is told.
            return false;
        }
        // De-duplicate: an identical job still waiting in the queue would send a second mail and,
        // worse, park a second token - each delivery invalidating the previous link.
        $jobcontext = ['invitationid' => (int) $invite->id, 'kind' => $kind];
        if (
            method_exists('\auth_flexaccess\api', 'deferred_mail_queued')
                && \auth_flexaccess\api::deferred_mail_queued(invitation_mail_renderer::class, $jobcontext)
        ) {
            return true;
        }
        return (bool) \auth_flexaccess\api::queue_deferred_mail(
            null,
            $invite->email,
            'invite',
            invitation_mail_renderer::class,
            $jobcontext,
            $now,
            $now
        );
    }

    /**
     * Produce a token guaranteed unique in the table.
     *
     * @return string
     */
    /**
     * Produce a token hash guaranteed unique in the table (the plaintext is discarded; a fresh
     * usable token is issued on send).
     *
     * @return string
     */
    private static function unique_tokenhash(): string {
        global $DB;
        do {
            $hash = self::hash_token(self::generate_token());
        } while ($DB->record_exists(self::TABLE, ['tokenhash' => $hash]));
        return $hash;
    }
}
