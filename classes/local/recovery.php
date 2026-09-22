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

use auth_flexaccess\local\account_state;
use auth_flexaccess\local\account_type;

/**
 * Controlled recovery of "frozen" FlexAccess users, for one user or in batches, per course or site-wide.
 *
 * The account and the enrolment lifecycle stay separate: the account is recovered through
 * auth_flexaccess (which never bypasses e-mail verification), enrolments are reactivated through
 * enrol_flexaccess and only when explicitly selected. An enrolment that was already removed is
 * never silently re-created; re-enrolment is a separate, confirmed action with its own capability.
 * Each user is processed atomically, and every recovery is recorded in the event log.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recovery {
    /** Outcome: the account was recovered. */
    public const RECOVERED = 'recovered';
    /** Outcome: nothing to do for the account. */
    public const SKIPPED = 'skipped';
    /** Outcome: the account may not be recovered (by rule or out of scope). */
    public const NOT_ELIGIBLE = 'noteligible';
    /** Outcome: a suspended enrolment was reactivated. */
    public const ENROLMENT_REACTIVATED = 'enrolmentreactivated';
    /** Outcome: an enrolment was removed earlier; re-enrolment needs its own confirmed action. */
    public const REENROL_REQUIRED = 'reenrolrequired';
    /** Outcome: the user was re-enrolled after confirmation. */
    public const REENROLLED = 'reenrolled';
    /** Outcome: the whole recovery of this user failed and was rolled back. */
    public const FAILED = 'failed';

    /**
     * Joint account + enrolment snapshot of one user, optionally scoped to a course.
     *
     * @param int $userid User id.
     * @param int|null $courseid Course scope, or null for all courses.
     * @param int|null $now Current time.
     * @return \stdClass|null Null when the user has no FlexAccess account.
     */
    public static function snapshot(int $userid, ?int $courseid = null, ?int $now = null): ?\stdClass {
        $now = $now ?? time();
        $accounts = \auth_flexaccess\api::get_accounts([$userid]);
        if (!isset($accounts[$userid])) {
            return null;
        }
        return self::build_snapshot($accounts[$userid], $courseid, $now);
    }

    /**
     * Snapshots for many users at once (one account query), scoped like {@see self::snapshot()}.
     *
     * @param int[] $userids User ids.
     * @param int|null $courseid Course scope.
     * @param int|null $now Current time.
     * @return array<int, \stdClass> Keyed by user id; users without FlexAccess account are omitted.
     */
    public static function snapshots(array $userids, ?int $courseid = null, ?int $now = null): array {
        $now = $now ?? time();
        $out = [];
        foreach (\auth_flexaccess\api::get_accounts($userids) as $userid => $account) {
            $out[(int) $userid] = self::build_snapshot($account, $courseid, $now);
        }
        return $out;
    }

    /**
     * Users that belong to a course from FlexAccess' point of view.
     *
     * Everyone with a FlexAccess enrolment in the course, plus accounts that originated in the course
     * (so that a user whose enrolment was already removed can still be found for re-enrolment).
     *
     * @param int $courseid Course id.
     * @return int[]
     */
    public static function course_userids(int $courseid): array {
        $ids = array_merge(
            \enrol_flexaccess\api::get_course_userids($courseid),
            \auth_flexaccess\api::get_source_course_userids($courseid)
        );
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Predict what an account recovery would do, without changing anything (for the preview).
     *
     * @param \stdClass $snapshot Snapshot.
     * @param int|null $now Current time.
     * @return string Action code: temporaryrevived, verificationresent, normalised, setpassword, none,
     *     noteligible.
     */
    public static function predict_account_action(\stdClass $snapshot, ?int $now = null): string {
        $now = $now ?? time();
        $state = $snapshot->accountstate;
        if ($snapshot->accounttype === account_type::TEMPORARY_USER) {
            if ($state === account_state::SUSPENDED) {
                return 'noteligible';
            }
            if ($snapshot->accountlapsed) {
                return $snapshot->verificationpending ? 'verificationresent' : 'temporaryrevived';
            }
            return $snapshot->mismatches ? 'normalised' : 'none';
        }
        if ($state === account_state::PENDING_CREDENTIAL) {
            return 'setpassword';
        }
        if ($state === account_state::ACTIVE) {
            return $snapshot->mismatches ? 'normalised' : 'none';
        }
        return 'noteligible';
    }

    /**
     * Whether the current user may recover this user in the given scope.
     *
     * @param \stdClass $snapshot Snapshot (already scoped).
     * @param int|null $courseid Course scope, or null for the system scope.
     * @return bool
     */
    public static function can_recover(\stdClass $snapshot, ?int $courseid): bool {
        if ($courseid === null) {
            return has_capability('tool/flexaccess:recoversystem', \context_system::instance());
        }
        if (!has_capability('tool/flexaccess:recovercourse', \context_course::instance($courseid))) {
            return false;
        }
        // A teacher may only touch users that belong to their course.
        return !empty($snapshot->enrolments) || in_array($courseid, $snapshot->unenrolledcourses, true);
    }

    /**
     * Recover one user atomically.
     *
     * @param int $userid User id.
     * @param int|null $courseid Course scope, or null for the system scope.
     * @param array $options ['reactivate' => int[] ueids, 'reenrol' => int[] courseids].
     * @param int|null $now Current time.
     * @return \stdClass ->userid, ->outcomes (string[]), ->reason (for failed/noteligible), ->action.
     */
    public static function recover(int $userid, ?int $courseid, array $options = [], ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $result = (object) ['userid' => $userid, 'outcomes' => [], 'reason' => '', 'action' => ''];
        $snapshot = self::snapshot($userid, $courseid, $now);
        if ($snapshot === null) {
            $result->outcomes[] = self::NOT_ELIGIBLE;
            $result->reason = 'noaccount';
            return $result;
        }
        if (!self::can_recover($snapshot, $courseid)) {
            $result->outcomes[] = self::NOT_ELIGIBLE;
            $result->reason = 'nopermission';
            return $result;
        }
        $reactivate = array_map('intval', (array) ($options['reactivate'] ?? []));
        $reenrol = array_map('intval', (array) ($options['reenrol'] ?? []));

        $changes = [];
        $reenrolled = [];
        $transaction = $DB->start_delegated_transaction();
        try {
            $account = \auth_flexaccess\api::recover_account($userid, null, $now);
            $result->outcomes[] = $account->outcome;
            $result->action = $account->action;
            if ($account->outcome === self::NOT_ELIGIBLE) {
                $result->reason = $account->action;
            }

            // Enrolments: only those explicitly selected, only within the scope.
            foreach ($snapshot->enrolments as $enrolment) {
                if (!in_array($enrolment->ueid, $reactivate, true)) {
                    continue;
                }
                $change = \enrol_flexaccess\api::reactivate_enrolment($enrolment->ueid, $courseid, $now);
                if ($change !== null) {
                    $changes[] = $change;
                    $result->outcomes[] = self::ENROLMENT_REACTIVATED;
                }
            }

            // Previously removed enrolments: never silently, only when confirmed and permitted.
            foreach ($snapshot->unenrolledcourses as $unenrolled) {
                if ($courseid !== null && $unenrolled !== $courseid) {
                    continue;
                }
                $allowed = has_capability('tool/flexaccess:reenrol', \context_course::instance($unenrolled));
                if (in_array($unenrolled, $reenrol, true) && $allowed) {
                    $restrict = $snapshot->accounttype === account_type::TEMPORARY_USER;
                    if (\enrol_flexaccess\api::reenrol_user($unenrolled, $userid, $restrict, $now)) {
                        $reenrolled[] = $unenrolled;
                        $result->outcomes[] = self::REENROLLED;
                    }
                } else {
                    $result->outcomes[] = self::REENROL_REQUIRED;
                }
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $rolledback) {
                // The rollback rethrows; the user is reported as failed with the original reason.
                $result->outcomes = [self::FAILED];
                $result->reason = $e->getMessage();
                return $result;
            }
        }

        $after = self::snapshot($userid, $courseid, $now);
        \tool_flexaccess\event\account_recovered::create([
            'context' => $courseid !== null ? \context_course::instance($courseid) : \context_system::instance(),
            'relateduserid' => $userid,
            'other' => [
                'scope' => $courseid !== null ? 'course' : 'system',
                'courseid' => $courseid ?? 0,
                'oldstate' => $snapshot->accountstate,
                'newstate' => $after ? $after->accountstate : $snapshot->accountstate,
                'action' => $result->action,
                'enrolments' => array_map(static fn(\stdClass $c): array => [
                    'ueid' => $c->ueid,
                    'courseid' => $c->courseid,
                    'oldstatus' => $c->oldstatus,
                    'newstatus' => $c->newstatus,
                    'newtimeend' => $c->newtimeend,
                ], $changes),
                'reenrolled' => $reenrolled,
                'outcomes' => array_values(array_unique($result->outcomes)),
            ],
        ])->trigger();
        $result->outcomes = array_values(array_unique($result->outcomes));
        return $result;
    }

    /**
     * Recover many users, each in its own transaction, reporting one result per user.
     *
     * @param int[] $userids User ids.
     * @param int|null $courseid Course scope, or null for the system scope.
     * @param array $options ['reactivateall' => bool (reactivate every suspended/expired enrolment in
     *     scope), 'reactivate' => [userid => int[] ueids], 'reenrol' => [userid => int[] courseids]].
     * @param int|null $now Current time.
     * @return \stdClass[] Keyed by user id.
     */
    public static function recover_batch(array $userids, ?int $courseid, array $options = [], ?int $now = null): array {
        $now = $now ?? time();
        $results = [];
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            $useroptions = [
                'reactivate' => (array) ($options['reactivate'][$userid] ?? []),
                'reenrol' => (array) ($options['reenrol'][$userid] ?? []),
            ];
            if (!empty($options['reactivateall'])) {
                $snapshot = self::snapshot($userid, $courseid, $now);
                foreach ($snapshot ? $snapshot->enrolments : [] as $enrolment) {
                    if ($enrolment->reactivatable) {
                        $useroptions['reactivate'][] = $enrolment->ueid;
                    }
                }
            }
            try {
                $results[$userid] = self::recover($userid, $courseid, $useroptions, $now);
            } catch (\Throwable $e) {
                $results[$userid] = (object) [
                    'userid' => $userid,
                    'outcomes' => [self::FAILED],
                    'reason' => $e->getMessage(),
                    'action' => '',
                ];
            }
        }
        return $results;
    }

    /**
     * Combine account and enrolment state into one of the diagnosis labels.
     *
     * @param \stdClass $snapshot Snapshot.
     * @param \stdClass|null $enrolment One enrolment of the snapshot, or null.
     * @return string accountexpired, enrolexpired, bothexpired, activeenrolsuspended,
     *     lockedenrolactive, pendingcredential, ok.
     */
    public static function access_status(\stdClass $snapshot, ?\stdClass $enrolment): string {
        $accountexpired = $snapshot->accountlapsed;
        $locked = !$accountexpired && ((int) $snapshot->suspended === 1 || $snapshot->accountstate === account_state::SUSPENDED);
        $enrolexpired = $enrolment !== null && $enrolment->expired;
        $enrolsuspended = $enrolment !== null && $enrolment->status !== ENROL_USER_ACTIVE;
        if ($accountexpired && $enrolexpired) {
            return 'bothexpired';
        }
        if ($accountexpired) {
            return 'accountexpired';
        }
        if ($enrolexpired) {
            return 'enrolexpired';
        }
        if ($locked && $enrolment !== null && !$enrolsuspended) {
            return 'lockedenrolactive';
        }
        if ($snapshot->accountstate === account_state::PENDING_CREDENTIAL) {
            return 'pendingcredential';
        }
        if ($enrolsuspended) {
            return 'activeenrolsuspended';
        }
        return 'ok';
    }

    /**
     * Assemble a snapshot from an account row.
     *
     * @param \stdClass $account Account row joined with the user.
     * @param int|null $courseid Course scope.
     * @param int $now Current time.
     * @return \stdClass
     */
    private static function build_snapshot(\stdClass $account, ?int $courseid, int $now): \stdClass {
        $userid = (int) $account->userid;
        $enrolments = \enrol_flexaccess\api::get_user_enrolments($userid, $courseid, $now);
        foreach ($enrolments as $enrolment) {
            $enrolment->reactivatable = $enrolment->status !== ENROL_USER_ACTIVE || $enrolment->expired;
        }
        $enrolledcourses = array_map(static fn(\stdClass $e): int => $e->courseid, $enrolments);
        $unenrolled = [];
        $source = (int) ($account->sourcecourseid ?? 0);
        if ($source > 0 && !in_array($source, $enrolledcourses, true) && ($courseid === null || $courseid === $source)) {
            $unenrolled[] = $source;
        }
        $lapsed = $account->accountstate === account_state::EXPIRED
            || ($account->accounttype === account_type::TEMPORARY_USER
                && !empty($account->timeexpires) && (int) $account->timeexpires <= $now);
        return (object) [
            'userid' => $userid,
            'fullname' => trim($account->firstname . ' ' . $account->lastname),
            'email' => (string) $account->email,
            'reference' => (string) ($account->referencecode ?? ''),
            'accounttype' => (string) $account->accounttype,
            'accountstate' => (string) $account->accountstate,
            'timeexpires' => (int) ($account->timeexpires ?? 0),
            'accountlapsed' => $lapsed,
            'suspended' => (int) $account->suspended,
            'verificationpending' => \auth_flexaccess\api::verification_pending($userid),
            'credentialstatus' => \auth_flexaccess\api::credential_status($userid, $now),
            'mismatches' => array_map(
                static fn(\stdClass $m): string => $m->code,
                \auth_flexaccess\api::find_state_mismatches($now, 20, [$userid])
            ),
            'enrolments' => $enrolments,
            'unenrolledcourses' => $unenrolled,
        ];
    }
}
