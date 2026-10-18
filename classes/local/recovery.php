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
    /** Overall: account and course access are effective again (postcondition met). */
    public const EFFECTIVE_ACCESS_RESTORED = 'effective_access_restored';
    /** Overall: the recovery ran, but account or course access is still not effective. */
    public const EFFECTIVE_ACCESS_NOT_RESTORED = 'effective_access_not_restored';
    /** Overall: the recovery was refused before anything changed (rule, scope or confirmation). */
    public const NOT_ELIGIBLE = 'noteligible';
    /** Overall: the whole recovery of this user failed and was rolled back. */
    public const FAILED = 'failed';

    /** Action: the account was recovered. */
    public const ACCOUNT_RECOVERED = 'account_recovered';
    /** Action: the account needed no change. */
    public const ACCOUNT_UNCHANGED = 'account_unchanged';
    /** Action: the account is suspended outside FlexAccess; nothing was changed. */
    public const ACCOUNT_BLOCKED_FOREIGN_LOCK = 'account_blocked_foreign_lock';
    /** Action: the account may not be recovered by rule (e.g. suspended by an administrator). */
    public const ACCOUNT_NOT_ELIGIBLE = 'account_not_eligible';
    /** Action: a suspended or expired enrolment was reactivated. */
    public const ENROLMENT_REACTIVATED = 'enrolment_reactivated';
    /** Action: the selected enrolment was already active. */
    public const ENROLMENT_ALREADY_ACTIVE = 'enrolment_already_active';
    /** Action: the missing FlexAccess participant role was put back into the course. */
    public const ENROLMENT_ROLE_REPAIRED = 'enrolment_role_repaired';
    /** Action: refused, the FlexAccess enrolment instance of the course is disabled. */
    public const ENROLMENT_BLOCKED_INSTANCE_DISABLED = 'enrolment_blocked_instance_disabled';
    /** Action: refused, the FlexAccess enrolment plugin is disabled site-wide. */
    public const ENROLMENT_BLOCKED_PLUGIN_DISABLED = 'enrolment_blocked_plugin_disabled';
    /** Action: refused, the FlexAccess participant role does not exist. */
    public const ENROLMENT_BLOCKED_MISSING_ROLE = 'enrolment_blocked_missing_role';
    /** Action: refused, the course has several FlexAccess enrolment instances. */
    public const ENROLMENT_BLOCKED_MULTIPLE_INSTANCES = 'enrolment_blocked_multiple_instances';
    /** Action/state: the enrolment is active but starts in the future. */
    public const ENROLMENT_NOT_STARTED = 'enrolment_not_started';
    /** Action: the user was re-enrolled after confirmation. */
    public const ENROLMENT_REENROLLED = 'enrolment_reenrolled';
    /** Action: an enrolment was removed earlier; re-enrolment needs its own confirmed action. */
    public const ENROLMENT_REENROL_REQUIRED = 'enrolment_reenrol_required';
    /** Action: enrolment changes were skipped because the account itself is blocked. */
    public const ENROLMENT_SKIPPED_ACCOUNT_BLOCKED = 'enrolment_skipped_account_blocked';

    /** @deprecated since 2026101700 - use {@see self::ACCOUNT_RECOVERED}. */
    public const RECOVERED = self::ACCOUNT_RECOVERED;
    /** @deprecated since 2026101700 - use {@see self::ACCOUNT_UNCHANGED}. */
    public const SKIPPED = self::ACCOUNT_UNCHANGED;
    /** @deprecated since 2026101700 - use {@see self::ENROLMENT_REENROL_REQUIRED}. */
    public const REENROL_REQUIRED = self::ENROLMENT_REENROL_REQUIRED;
    /** @deprecated since 2026101700 - use {@see self::ENROLMENT_REENROLLED}. */
    public const REENROLLED = self::ENROLMENT_REENROLLED;

    /** Effective-state problems with which Core does not let the user into the course at all. */
    private const CORE_INACTIVE = [
        'plugin_disabled',
        'user_enrolment_missing',
        'instance_disabled',
        'user_enrolment_suspended',
        'not_started',
        'expired',
    ];

    /** Enrolment outcomes of enrol_flexaccess mapped onto recovery actions. */
    private const ENROL_OUTCOMES = [
        'reactivated' => self::ENROLMENT_REACTIVATED,
        'alreadyactive' => self::ENROLMENT_ALREADY_ACTIVE,
        'instancedisabled' => self::ENROLMENT_BLOCKED_INSTANCE_DISABLED,
        'plugindisabled' => self::ENROLMENT_BLOCKED_PLUGIN_DISABLED,
        'rolemissing' => self::ENROLMENT_BLOCKED_MISSING_ROLE,
        'multipleinstances' => self::ENROLMENT_BLOCKED_MULTIPLE_INSTANCES,
        'enrolled' => self::ENROLMENT_REENROLLED,
    ];

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
        return self::snapshots([$userid], $courseid, $now)[$userid] ?? null;
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
        $accounts = \auth_flexaccess\api::get_accounts($userids);
        if (!$accounts) {
            return [];
        }
        // Everything a snapshot needs is read in a fixed number of queries for the whole list, not per
        // user: list pages must not grow their query count with the number of users shown.
        $ids = array_map('intval', array_keys($accounts));
        $facts = (object) [
            'enrolments' => \enrol_flexaccess\api::get_enrolments_for_users($ids, $now),
            'credential' => \auth_flexaccess\api::credential_statuses($ids, $now),
            'verification' => array_flip(\auth_flexaccess\api::verification_pending_userids($ids)),
            'mismatches' => [],
        ];
        foreach (\auth_flexaccess\api::find_state_mismatches($now, 0, $ids) as $mismatch) {
            $facts->mismatches[(int) $mismatch->userid][] = $mismatch->code;
        }
        $out = [];
        foreach ($accounts as $userid => $account) {
            $out[(int) $userid] = self::build_snapshot($account, $courseid, $now, $facts);
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
     *     noteligible, foreignlock.
     */
    public static function predict_account_action(\stdClass $snapshot, ?int $now = null): string {
        $now = $now ?? time();
        $state = $snapshot->accountstate;
        if (!$snapshot->liftable) {
            // A suspension FlexAccess did not set: recovery changes nothing (administrative decision).
            return 'foreignlock';
        }
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
     * Whether an account action makes the (global) account usable again.
     *
     * Such an action restores the user's access in every course where a FlexAccess enrolment is still
     * effective - not only in the course the recovery was started from.
     *
     * @param \stdClass $snapshot Snapshot.
     * @param string $action Predicted account action.
     * @return bool
     */
    public static function reenables_account(\stdClass $snapshot, string $action): bool {
        if (in_array($action, ['temporaryrevived', 'verificationresent', 'setpassword'], true)) {
            return true;
        }
        return $action === 'normalised' && (int) $snapshot->suspended === 1;
    }

    /**
     * Cross-course impact of a course-scoped recovery (empty when there is none).
     *
     * @param \stdClass $snapshot Snapshot built for the course scope.
     * @param int|null $courseid Course scope (null = system scope: no hidden impact by definition).
     * @return int[] Other courses in which the account would become usable again.
     */
    public static function cross_course_impact(\stdClass $snapshot, ?int $courseid): array {
        if ($courseid === null) {
            return [];
        }
        return self::reenables_account($snapshot, self::predict_account_action($snapshot)) ? $snapshot->othercourses : [];
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
     * Recover one user atomically and verify the result against Core semantics.
     *
     * The account is recovered first. If it stays blocked (a suspension FlexAccess did not set, an
     * administrative suspension), no enrolment is touched: access could not be restored anyway, and a
     * partial result must not look like success. Otherwise the explicitly selected enrolments are
     * reactivated or re-enrolled. Afterwards account and enrolment state are read again, and the overall
     * result is "effective access restored" only when the account is usable through its channel and every
     * targeted FlexAccess enrolment is effective in Core (plugin and instance enabled, enrolment active and
     * inside its time window, participant role present).
     *
     * @param int $userid User id.
     * @param int|null $courseid Course scope, or null for the system scope.
     * @param array $options ['reactivate' => int[] ueids, 'reenrol' => int[] courseids,
     *     'confirmcrosscourse' => bool: the cross-course impact was explicitly confirmed].
     * @param int|null $now Current time.
     * @return \stdClass ->userid, ->overall (one of EFFECTIVE_ACCESS_RESTORED, EFFECTIVE_ACCESS_NOT_RESTORED,
     *     NOT_ELIGIBLE, FAILED), ->outcomes (string[] technical actions), ->reason (refusal or failure),
     *     ->action (account action), ->postcondition (see {@see self::postcondition()}), ->blockers.
     */
    public static function recover(int $userid, ?int $courseid, array $options = [], ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $result = (object) [
            'userid' => $userid,
            'overall' => self::NOT_ELIGIBLE,
            'outcomes' => [],
            'reason' => '',
            'action' => '',
            'postcondition' => null,
            'blockers' => [],
        ];
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
        // A course-level action must not silently restore access in other courses. With such an
        // impact, only someone holding the site-wide recovery right may go ahead, and only after
        // explicitly confirming it; everyone else is referred to the site-wide recovery.
        $impact = self::cross_course_impact($snapshot, $courseid);
        if ($impact) {
            if (!has_capability('tool/flexaccess:recoversystem', \context_system::instance())) {
                $result->outcomes[] = self::NOT_ELIGIBLE;
                $result->reason = 'crosscourse';
                return $result;
            }
            if (empty($options['confirmcrosscourse'])) {
                $result->outcomes[] = self::NOT_ELIGIBLE;
                $result->reason = 'crosscourseconfirm';
                return $result;
            }
        }
        $reactivate = array_map('intval', (array) ($options['reactivate'] ?? []));
        $reenrol = array_map('intval', (array) ($options['reenrol'] ?? []));

        $changes = [];
        $reenrolled = [];
        $targets = [];
        $transaction = $DB->start_delegated_transaction();
        try {
            $account = \auth_flexaccess\api::recover_account($userid, null, $now);
            $result->action = $account->action;
            $blocked = $account->outcome === 'noteligible';
            if ($blocked) {
                $result->reason = $account->action;
                $result->outcomes[] = $account->action === 'foreignlock'
                    ? self::ACCOUNT_BLOCKED_FOREIGN_LOCK : self::ACCOUNT_NOT_ELIGIBLE;
            } else {
                $result->outcomes[] = $account->outcome === 'recovered' ? self::ACCOUNT_RECOVERED : self::ACCOUNT_UNCHANGED;
            }

            // Enrolments: only those explicitly selected, only within the scope, and only while the
            // account itself can be used again - otherwise nothing would really be restored.
            foreach ($snapshot->enrolments as $enrolment) {
                if (!in_array($enrolment->ueid, $reactivate, true)) {
                    continue;
                }
                $targets[] = $enrolment->courseid;
                if ($blocked) {
                    $result->outcomes[] = self::ENROLMENT_SKIPPED_ACCOUNT_BLOCKED;
                    continue;
                }
                $change = \enrol_flexaccess\api::reactivate_enrolment($enrolment->ueid, $courseid, $now);
                if ($change->outcome === 'notfound') {
                    continue;
                }
                $changes[] = $change;
                $result->outcomes[] = self::ENROL_OUTCOMES[$change->outcome] ?? self::FAILED;
                if (!empty($change->rolerepaired)) {
                    $result->outcomes[] = self::ENROLMENT_ROLE_REPAIRED;
                }
            }

            // Previously removed enrolments: never silently, only when confirmed and permitted.
            foreach ($snapshot->unenrolledcourses as $unenrolled) {
                if ($courseid !== null && $unenrolled !== $courseid) {
                    continue;
                }
                $allowed = has_capability('tool/flexaccess:reenrol', \context_course::instance($unenrolled));
                if (!in_array($unenrolled, $reenrol, true) || !$allowed) {
                    $result->outcomes[] = self::ENROLMENT_REENROL_REQUIRED;
                    continue;
                }
                $targets[] = $unenrolled;
                if ($blocked) {
                    $result->outcomes[] = self::ENROLMENT_SKIPPED_ACCOUNT_BLOCKED;
                    continue;
                }
                $restrict = $snapshot->accounttype === account_type::TEMPORARY_USER;
                $enrolled = \enrol_flexaccess\api::reenrol_user_outcome($unenrolled, $userid, $restrict, $now);
                $result->outcomes[] = self::ENROL_OUTCOMES[$enrolled->status] ?? self::FAILED;
                if ($enrolled->status === 'enrolled') {
                    $reenrolled[] = $unenrolled;
                }
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $rolledback) {
                // The rollback rethrows; the user is reported as failed with the original reason.
                $result->overall = self::FAILED;
                $result->outcomes = [self::FAILED];
                $result->reason = $e->getMessage();
                return $result;
            }
        }

        // Postcondition: read everything again and judge the result as Core and the login guard see it.
        if ($courseid !== null && ($snapshot->enrolments || in_array($courseid, $reenrolled, true))) {
            $targets[] = $courseid;
        }
        $result->postcondition = self::postcondition($userid, array_values(array_unique($targets)), $now);
        $result->blockers = $result->postcondition->blockers;
        $result->overall = $result->postcondition->met ? self::EFFECTIVE_ACCESS_RESTORED : self::EFFECTIVE_ACCESS_NOT_RESTORED;
        foreach ($result->postcondition->courses as $state) {
            if ($state === 'not_started') {
                $result->outcomes[] = self::ENROLMENT_NOT_STARTED;
            }
        }
        $result->outcomes = array_values(array_unique($result->outcomes));

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
                    'outcome' => $c->outcome,
                    'oldstatus' => $c->oldstatus,
                    'newstatus' => $c->newstatus,
                    'newtimeend' => $c->newtimeend,
                    'rolerepaired' => !empty($c->rolerepaired),
                ], $changes),
                'reenrolled' => $reenrolled,
                'outcomes' => $result->outcomes,
                'overall' => $result->overall,
                'postcondition' => [
                    'account' => $result->postcondition->account->reason,
                    'courses' => $result->postcondition->courses,
                ],
            ],
        ])->trigger();
        return $result;
    }

    /**
     * The verifiable postcondition of a recovery: is access effective now, as Core and the login guard see it?
     *
     * @param int $userid User id.
     * @param int[] $courseids Courses whose FlexAccess enrolment was targeted. When empty, the user's
     *     FlexAccess enrolments are considered and at least one must be effective (if there are any).
     * @param int|null $now Current time.
     * @return \stdClass ->met (bool), ->account (auth_flexaccess account_access result), ->courses
     *     (courseid => effective state), ->blockers (\stdClass[] scope 'account'|'course', courseid, code).
     */
    public static function postcondition(int $userid, array $courseids, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $account = \auth_flexaccess\api::account_access($userid, $now);
        $blockers = [];
        if (!$account->usable) {
            $blockers[] = (object) ['scope' => 'account', 'courseid' => 0, 'code' => $account->reason];
        }
        $courses = [];
        $any = $courseids === [];
        if ($any) {
            foreach (\enrol_flexaccess\api::get_enrolments_for_users([$userid], $now)[$userid] ?? [] as $enrolment) {
                $courses[$enrolment->courseid] = $enrolment->effective;
            }
        } else {
            foreach ($courseids as $courseid) {
                $courses[(int) $courseid] = \enrol_flexaccess\api::effective_enrolment_state($userid, (int) $courseid, $now)->state;
            }
        }
        $ok = array_filter($courses, static fn(string $state): bool => $state === 'ok');
        if ($any) {
            $coursesmet = !$courses || $ok;
        } else {
            $coursesmet = count($ok) === count($courses);
        }
        if (!$coursesmet) {
            foreach ($courses as $courseid => $state) {
                if ($state !== 'ok') {
                    $blockers[] = (object) ['scope' => 'course', 'courseid' => (int) $courseid, 'code' => $state];
                }
            }
        }
        return (object) [
            'met' => $account->usable && $coursesmet,
            'account' => $account,
            'courses' => $courses,
            'blockers' => $blockers,
        ];
    }

    /**
     * Expected effective state of one enrolment after the recovery, for the preview (nothing is changed).
     *
     * @param \stdClass $snapshot Snapshot.
     * @param \stdClass $enrolment One enrolment of the snapshot.
     * @param bool $selected Whether the enrolment is selected for reactivation.
     * @return string 'ok' or an effective-state code that will remain.
     */
    public static function predict_enrolment(\stdClass $snapshot, \stdClass $enrolment, bool $selected): string {
        $action = self::predict_account_action($snapshot);
        if (in_array($action, ['foreignlock', 'noteligible'], true)) {
            return $enrolment->effective;
        }
        if (!$selected) {
            return $enrolment->effective;
        }
        // Reactivation fixes a suspension, a passed end and a missing course role - nothing else.
        $fixable = ['user_enrolment_suspended', 'expired', 'participant_role_missing'];
        foreach ($enrolment->problems as $problem) {
            if (!in_array($problem, $fixable, true)) {
                return $problem;
            }
        }
        return 'ok';
    }

    /**
     * Where an administrator resolves a blocker that recovery must not resolve on its own.
     *
     * @param \stdClass $blocker Blocker (scope, courseid, code).
     * @param int $userid User the blocker belongs to.
     * @return \moodle_url|null
     */
    public static function blocker_url(\stdClass $blocker, int $userid): ?\moodle_url {
        if ($blocker->scope === 'account') {
            if (in_array($blocker->code, ['suspended', 'statemismatch'], true)) {
                return new \moodle_url('/admin/tool/flexaccess/lockreview.php');
            }
            return new \moodle_url('/admin/tool/flexaccess/user.php', ['userid' => $userid]);
        }
        if (in_array($blocker->code, ['instance_disabled', 'multiple_instances', 'user_enrolment_missing'], true)) {
            return new \moodle_url('/enrol/instances.php', ['id' => $blocker->courseid]);
        }
        if (in_array($blocker->code, ['plugin_disabled', 'participant_role_missing'], true)) {
            return new \moodle_url('/admin/tool/flexaccess/status.php');
        }
        return null;
    }

    /**
     * Recover many users, each in its own transaction, reporting one result per user.
     *
     * @param int[] $userids User ids.
     * @param int|null $courseid Course scope, or null for the system scope.
     * @param array $options ['reactivateall' => bool (reactivate every suspended/expired enrolment in
     *     scope), 'reactivate' => [userid => int[] ueids], 'reenrol' => [userid => int[] courseids],
     *     'confirmcrosscourse' => int[] user ids whose cross-course impact was explicitly confirmed].
     * @param int|null $now Current time.
     * @return \stdClass[] Keyed by user id.
     */
    public static function recover_batch(array $userids, ?int $courseid, array $options = [], ?int $now = null): array {
        $now = $now ?? time();
        $results = [];
        $confirmed = array_map('intval', (array) ($options['confirmcrosscourse'] ?? []));
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            $useroptions = [
                'reactivate' => (array) ($options['reactivate'][$userid] ?? []),
                'reenrol' => (array) ($options['reenrol'][$userid] ?? []),
                'confirmcrosscourse' => in_array($userid, $confirmed, true),
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
                    'overall' => self::FAILED,
                    'outcomes' => [self::FAILED],
                    'reason' => $e->getMessage(),
                    'action' => '',
                    'postcondition' => null,
                    'blockers' => [],
                ];
            }
        }
        return $results;
    }

    /**
     * Diagnosis label of account plus one enrolment, from the central effective states.
     *
     * The course side comes from enrol_flexaccess' effective state (Core semantics: plugin, instance,
     * enrolment, time window, participant role); nothing is recombined from individual fields here.
     *
     * @param \stdClass $snapshot Snapshot.
     * @param \stdClass|null $enrolment One enrolment of the snapshot, or null.
     * @return string accountexpired, enrolexpired, bothexpired, activeenrolsuspended, lockedenrolactive,
     *     pendingcredential, instancedisabled, plugindisabled, rolemissing, notstarted, multipleinstances, ok.
     */
    public static function access_status(\stdClass $snapshot, ?\stdClass $enrolment): string {
        $accountexpired = $snapshot->accountlapsed;
        $locked = !$accountexpired && ((int) $snapshot->suspended === 1 || $snapshot->accountstate === account_state::SUSPENDED);
        $effective = $enrolment !== null ? $enrolment->effective : null;
        $enrolexpired = $effective === 'expired';
        if ($accountexpired && $enrolexpired) {
            return 'bothexpired';
        }
        if ($accountexpired) {
            return 'accountexpired';
        }
        if ($enrolexpired) {
            return 'enrolexpired';
        }
        if ($locked && $effective === 'ok') {
            return 'lockedenrolactive';
        }
        if ($snapshot->accountstate === account_state::PENDING_CREDENTIAL) {
            return 'pendingcredential';
        }
        $labels = [
            'user_enrolment_suspended' => 'activeenrolsuspended',
            'instance_disabled' => 'instancedisabled',
            'plugin_disabled' => 'plugindisabled',
            'participant_role_missing' => 'rolemissing',
            'not_started' => 'notstarted',
            'multiple_instances' => 'multipleinstances',
        ];
        if ($effective !== null && isset($labels[$effective])) {
            return $labels[$effective];
        }
        return 'ok';
    }

    /**
     * Assemble a snapshot from an account row and the facts read for the whole list.
     *
     * @param \stdClass $account Account row joined with the user.
     * @param int|null $courseid Course scope.
     * @param int $now Current time.
     * @param \stdClass $facts Bulk facts (enrolments, credential, verification, mismatches), by user id.
     * @return \stdClass
     */
    private static function build_snapshot(\stdClass $account, ?int $courseid, int $now, \stdClass $facts): \stdClass {
        $userid = (int) $account->userid;
        $all = $facts->enrolments[$userid] ?? [];
        $enrolments = [];
        $othercourses = [];
        foreach ($all as $enrolment) {
            if ($courseid === null || $enrolment->courseid === $courseid) {
                // Selectable whenever a reactivation can fix something (suspension, passed end, lost
                // course role); blockers that need an administrator are shown, not offered.
                $enrolment->reactivatable = array_intersect(
                    $enrolment->problems,
                    ['user_enrolment_suspended', 'expired', 'participant_role_missing']
                ) && !array_intersect($enrolment->problems, ['plugin_disabled', 'instance_disabled', 'multiple_instances']);
                $enrolments[] = $enrolment;
            } else if (!array_intersect($enrolment->problems, self::CORE_INACTIVE)) {
                // Active in Core elsewhere (with or without its role, the course opens): an account
                // recovery started in this course restores that access too.
                $othercourses[] = $enrolment->courseid;
            }
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
        $suspended = (int) $account->suspended;
        return (object) [
            'userid' => $userid,
            'fullname' => trim($account->firstname . ' ' . $account->lastname),
            'email' => (string) $account->email,
            'reference' => (string) ($account->referencecode ?? ''),
            'accounttype' => (string) $account->accounttype,
            'accountstate' => (string) $account->accountstate,
            'timeexpires' => (int) ($account->timeexpires ?? 0),
            'accountlapsed' => $lapsed,
            'suspended' => $suspended,
            'lockedby' => $account->lockedby ?? null,
            'verificationpending' => isset($facts->verification[$userid]),
            'credentialstatus' => $facts->credential[$userid] ?? 'none',
            'mismatches' => $facts->mismatches[$userid] ?? [],
            'enrolments' => $enrolments,
            'unenrolledcourses' => $unenrolled,
            'othercourses' => array_values(array_unique($othercourses)),
            // Same rule as the lifecycle: only a suspension FlexAccess set is liftable.
            'liftable' => $suspended !== 1
                || ($account->lockedby ?? null) === \auth_flexaccess\local\lifecycle::LOCKED_BY_FLEXACCESS,
        ];
    }
}
