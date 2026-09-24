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
 * Retroactive reconciliation of existing FlexAccess accounts against the lifecycle invariants.
 *
 * One service for the upgrade backfill, the continuation task, the system status ("inspect only" and
 * "safe repair") and future maintenance. It builds a complete per-user snapshot across the FlexAccess
 * account, the Moodle user, the restriction role, the FlexAccess course enrolments with their course
 * role, pending processes and mail, and then:
 *
 *  - applies only deterministic repairs whose target follows unambiguously from the invariants;
 *  - never reactivates, re-enrols, merges or lifts a suspension FlexAccess did not set itself;
 *  - records every ambiguous case as an open review case, visible in the system status;
 *  - writes an audit row for every change;
 *  - is idempotent: a second run on unchanged data changes nothing.
 *
 * Accounts are processed in id-ordered batches with the progress persisted after every batch, so a
 * run can resume after a technical failure and large installations can continue in an ad-hoc task.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reconciliation {
    /** Mode: diagnose only, change nothing. */
    public const MODE_INSPECT = 'inspect';
    /** Mode: apply safe repairs and record review cases. */
    public const MODE_REPAIR = 'repair';

    /** Source: the plugin upgrade. */
    public const SOURCE_UPGRADE = 'upgrade';
    /** Source: the continuation task. */
    public const SOURCE_TASK = 'task';
    /** Source: the system status page. */
    public const SOURCE_STATUS = 'systemstatus';

    /** Repair: lift a suspension that FlexAccess itself set on a live/active account. */
    public const RULE_UNSUSPEND = 'unsuspend_flexaccess_lock';
    /** Repair: remove the restriction role from an active permanent account. */
    public const RULE_UNRESTRICT = 'unrestrict_active';
    /** Repair: add the missing restriction role to a temporary or pending account. */
    public const RULE_RESTRICT = 'restrict_temporary';
    /** Repair: suspend again the Moodle user of an expired/suspended account. */
    public const RULE_RELOCK = 'relock_locked';
    /** Repair: expire a temporary account whose expiry time has passed. */
    public const RULE_EXPIRE = 'expire_overdue';
    /** Repair: restore the FlexAccess role definitions. */
    public const RULE_ROLEMODEL = 'role_model';
    /** Repair: remove system-level assignments of the course-only participant role. */
    public const RULE_SYSTEMPARTICIPANT = 'system_participant';

    /** Review: the suspension cannot be attributed to FlexAccess; possibly administrative. */
    public const REVIEW_ADMIN_SUSPENSION = 'review_admin_suspension';
    /** Review: expired or suspended account that still has an active course enrolment. */
    public const REVIEW_EXPIRED_ACCOUNT = 'review_expired_account';
    /** Review: a live temporary account whose origin-course enrolment is gone. */
    public const REVIEW_ENROLMENT_REMOVED = 'review_enrolment_removed';
    /** Review: several FlexAccess enrolments in one course with different end times. */
    public const REVIEW_COMPETING_ENROLMENTS = 'review_competing_enrolments';
    /** Review: credential process in an inconsistent or stuck state. */
    public const REVIEW_CREDENTIAL_STATE = 'review_credential_state';
    /** Review: FlexAccess data on an identity that no longer uses FlexAccess (merge/relink). */
    public const REVIEW_IDENTITY_MERGE = 'review_identity_merge';
    /** Review: account type and state contradict each other. */
    public const REVIEW_STATE_CONTRADICTION = 'review_state_contradiction';
    /** Review: an active FlexAccess enrolment without its course role. */
    public const REVIEW_COURSE_ROLE = 'review_course_role';
    /** Review: restriction role held by a user without a FlexAccess account. */
    public const REVIEW_ORPHAN_RESTRICTION = 'review_orphan_restriction';

    /** Rules that change role assignments (for the status figures). */
    private const ROLE_RULES = [self::RULE_UNRESTRICT, self::RULE_RESTRICT, self::RULE_ROLEMODEL, self::RULE_SYSTEMPARTICIPANT];
    /** Rules that change the core user (for the status figures). */
    private const CORE_RULES = [self::RULE_UNSUSPEND, self::RULE_RELOCK, self::RULE_EXPIRE];

    /** Mismatch code (auth lifecycle) handled by each per-user repair rule. */
    private const RULE_CODES = [
        self::RULE_EXPIRE => ['overdue'],
        self::RULE_RELOCK => ['locked_unsuspended'],
        self::RULE_UNSUSPEND => ['active_suspended', 'live_suspended'],
        self::RULE_UNRESTRICT => ['active_restricted'],
        self::RULE_RESTRICT => ['temporary_unrestricted'],
    ];

    /** Accounts per batch. */
    public const BATCH = 200;
    /** Seconds the synchronous upgrade pass may take before handing over to the ad-hoc task. */
    private const UPGRADE_TIME = 60;
    /** Config key of the persisted run state. */
    private const STATE = 'reconcile_state';
    /** Table of review cases. */
    private const CASES = 'tool_flexaccess_reconcile';
    /** Table of the audit trail. */
    private const LOG = 'tool_flexaccess_reconcile_log';

    /**
     * Complete lifecycle snapshot of one FlexAccess user.
     *
     * @param int $userid User id.
     * @param int|null $now Current time.
     * @return \stdClass|null Null when the user has no FlexAccess account.
     */
    public static function inspect_user(int $userid, ?int $now = null): ?\stdClass {
        return self::inspect_users([$userid], $now)[$userid] ?? null;
    }

    /**
     * Snapshots for many users with a fixed number of queries (no N+1).
     *
     * @param int[] $userids User ids.
     * @param int|null $now Current time.
     * @return array<int, \stdClass> Keyed by user id; users without FlexAccess account are omitted.
     */
    public static function inspect_users(array $userids, ?int $now = null): array {
        $now = $now ?? time();
        $accounts = \auth_flexaccess\api::get_accounts($userids);
        if (!$accounts) {
            return [];
        }
        $ids = array_map('intval', array_keys($accounts));
        $mismatches = [];
        foreach (\auth_flexaccess\api::find_state_mismatches($now, count($ids) * 8 + 10, $ids) as $m) {
            $mismatches[(int) $m->userid][$m->code] = $m;
        }
        $enrolments = \enrol_flexaccess\api::get_enrolments_for_users($ids, $now);
        $facts = \auth_flexaccess\api::credential_facts($ids);
        $restricted = self::restricted_users($ids);

        $out = [];
        foreach ($accounts as $userid => $a) {
            $userid = (int) $userid;
            $lapsed = $a->accountstate === account_state::EXPIRED
                || ($a->accounttype === account_type::TEMPORARY_USER
                    && !empty($a->timeexpires) && (int) $a->timeexpires <= $now);
            $out[$userid] = (object) [
                'userid' => $userid,
                'user' => (object) [
                    'deleted' => 0,
                    'suspended' => (int) $a->suspended,
                    'confirmed' => (int) $a->confirmed,
                    'auth' => (string) $a->auth,
                ],
                'account' => (object) [
                    'type' => (string) $a->accounttype,
                    'state' => (string) $a->accountstate,
                    'timeactivated' => (int) ($a->timeactivated ?? 0),
                    'timeexpires' => (int) ($a->timeexpires ?? 0),
                    'lockedby' => $a->lockedby ?? null,
                    'batchcredential' => (int) ($a->batchcredential ?? 0),
                    'sourcecourseid' => (int) ($a->sourcecourseid ?? 0),
                    'lapsed' => $lapsed,
                ],
                'restricted' => isset($restricted[$userid]),
                'enrolments' => $enrolments[$userid] ?? [],
                'pending' => $facts[$userid],
                'mismatches' => $mismatches[$userid] ?? [],
            ];
        }
        return $out;
    }

    /**
     * Classify a snapshot: which safe repairs apply, which review cases exist.
     *
     * @param \stdClass $snapshot Snapshot.
     * @return array ['repairs' => string[] rules in order, 'reviews' => string[] review codes]
     */
    public static function evaluate(\stdClass $snapshot): array {
        $codes = array_keys($snapshot->mismatches);
        $account = $snapshot->account;
        $repairs = [];
        $reviews = [];

        // No automatic decision where the data itself is contradictory or the identity moved on.
        if (in_array('type_state', $codes, true)) {
            return ['repairs' => [], 'reviews' => [self::REVIEW_STATE_CONTRADICTION]];
        }
        if ($snapshot->user->auth !== 'flexaccess') {
            return ['repairs' => [], 'reviews' => [self::REVIEW_IDENTITY_MERGE]];
        }

        foreach (self::RULE_CODES as $rule => $rulecodes) {
            $hit = array_values(array_intersect($rulecodes, $codes));
            if (!$hit) {
                continue;
            }
            if ($rule === self::RULE_UNSUSPEND && $account->lockedby !== \auth_flexaccess\local\lifecycle::LOCKED_BY_FLEXACCESS) {
                // Suspension of unknown origin: possibly an independent administrative decision.
                $reviews[] = self::REVIEW_ADMIN_SUSPENSION;
                continue;
            }
            $repairs[] = $rule;
        }

        $locked = $account->lapsed || in_array($account->state, [account_state::EXPIRED, account_state::SUSPENDED], true);
        $percourse = [];
        foreach ($snapshot->enrolments as $enrolment) {
            $percourse[$enrolment->courseid][] = $enrolment;
            $live = $enrolment->status === ENROL_USER_ACTIVE && !$enrolment->expired;
            if ($locked && $live) {
                $reviews[] = self::REVIEW_EXPIRED_ACCOUNT;
            }
            if ($live && !$enrolment->hasrole) {
                $reviews[] = self::REVIEW_COURSE_ROLE;
            }
        }
        foreach ($percourse as $list) {
            $ends = array_unique(array_map(static fn(\stdClass $e): int => $e->timeend, $list));
            if (count($list) > 1 && count($ends) > 1) {
                $reviews[] = self::REVIEW_COMPETING_ENROLMENTS;
            }
        }
        if (
            !$locked && $account->type === account_type::TEMPORARY_USER && $account->sourcecourseid > 0
                && !isset($percourse[$account->sourcecourseid])
        ) {
            $reviews[] = self::REVIEW_ENROLMENT_REMOVED;
        }
        $facts = $snapshot->pending;
        if (
            $account->type === account_type::AUTHENTICATED_USER && $account->state === account_state::ACTIVE
                && !$facts->usablepassword && $facts->setpasswordissued && !$facts->setpasswordused
        ) {
            // Formally ACTIVE although the credential hand-over never completed (pre-1.1.0 conversions).
            $reviews[] = self::REVIEW_CREDENTIAL_STATE;
        }
        if ($account->state === account_state::PENDING_CREDENTIAL && $facts->failedfunnel > 0) {
            $reviews[] = self::REVIEW_CREDENTIAL_STATE;
        }
        return ['repairs' => $repairs, 'reviews' => array_values(array_unique($reviews))];
    }

    /**
     * Reconcile one user.
     *
     * @param int $userid User id.
     * @param string $mode MODE_INSPECT or MODE_REPAIR.
     * @param string $source Source for the audit trail.
     * @param int|null $now Current time.
     * @return \stdClass|null ->repairs (applied or planned rules), ->reviews, ->changed (bool); null
     *     when the user has no FlexAccess account.
     */
    public static function reconcile_user(int $userid, string $mode, string $source, ?int $now = null): ?\stdClass {
        $snapshots = self::inspect_users([$userid], $now);
        if (!isset($snapshots[$userid])) {
            return null;
        }
        return self::process($snapshots[$userid], $mode, $source, $now ?? time());
    }

    /**
     * Run (or continue) a full scan over all FlexAccess accounts.
     *
     * In repair mode the progress is persisted after every batch; a run interrupted by a time limit or
     * a technical error continues where it stopped. Inspect mode persists nothing and returns a report.
     *
     * @param string $mode MODE_INSPECT or MODE_REPAIR.
     * @param string $source Source for the audit trail.
     * @param int $timelimit Seconds after which to stop between batches (0 = no limit).
     * @param int|null $now Current time.
     * @return \stdClass Run state (status, cursor, counters) or, in inspect mode, the report.
     */
    public static function run(string $mode, string $source, int $timelimit = 0, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $started = microtime(true);
        if ($mode === self::MODE_INSPECT) {
            return self::inspect_all($now, $timelimit);
        }
        $state = self::state();
        if ($state->status !== 'running') {
            $state = self::fresh_state($source, $now);
            self::apply_site_repairs($source, $now, $state);
            self::save_state($state);
        }
        do {
            $userids = \auth_flexaccess\api::list_account_userids($state->cursor, self::BATCH);
            if (!$userids) {
                break;
            }
            foreach (self::inspect_users($userids, $now) as $snapshot) {
                $result = self::process($snapshot, self::MODE_REPAIR, $state->source, $now);
                $state->checked++;
                if ($result->repairs) {
                    $state->repairedaccounts++;
                }
                $state->rolefixes += count(array_intersect($result->repairs, self::ROLE_RULES));
                $state->corefixes += count(array_intersect($result->repairs, self::CORE_RULES));
            }
            $state->cursor = (int) end($userids);
            self::save_state($state);
        } while ($timelimit === 0 || microtime(true) - $started < $timelimit);

        if (!\auth_flexaccess\api::list_account_userids($state->cursor, 1)) {
            self::record_orphan_restrictions($now);
            $state->status = 'done';
            $state->finished = $now;
            $state->lastfullscan = $now;
            self::save_state($state);
        }
        return $state;
    }

    /**
     * Run the bounded first pass of the upgrade backfill; continue in an ad-hoc task if needed.
     *
     * @return \stdClass Run state.
     */
    public static function start_upgrade_backfill(): \stdClass {
        // A fresh run: the upgrade is the start of a new full scan.
        set_config(self::STATE, '', 'tool_flexaccess');
        $state = self::run(self::MODE_REPAIR, self::SOURCE_UPGRADE, self::UPGRADE_TIME);
        if ($state->status === 'running') {
            self::queue_continuation();
        }
        return $state;
    }

    /**
     * Queue the ad-hoc task that continues an unfinished run.
     *
     * @return void
     */
    public static function queue_continuation(): void {
        \core\task\manager::queue_adhoc_task(new \tool_flexaccess\task\reconcile_accounts(), true);
    }

    /**
     * Persisted run state.
     *
     * @return \stdClass status (never|running|done), source, cursor, started, finished, lastfullscan,
     *     checked, repairedaccounts, rolefixes, corefixes, total.
     */
    public static function state(): \stdClass {
        $raw = (string) get_config('tool_flexaccess', self::STATE);
        $state = $raw !== '' ? json_decode($raw) : null;
        if (!$state) {
            $state = self::fresh_state('', 0);
            $state->status = 'never';
        }
        return $state;
    }

    /**
     * Open review cases grouped by class.
     *
     * @return array<string, int>
     */
    public static function open_case_counts(): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT code, COUNT(1) AS n FROM {" . self::CASES . "} WHERE status = 'open' GROUP BY code ORDER BY code"
        );
        return array_map(static fn(\stdClass $r): int => (int) $r->n, $rows);
    }

    /**
     * Open review cases, optionally of one class, for the drill-down.
     *
     * @param string|null $code Review class.
     * @param int $limit Maximum rows.
     * @return \stdClass[] userid, code, timemodified.
     */
    public static function open_cases(?string $code = null, int $limit = 200): array {
        global $DB;
        $params = ['status' => 'open'];
        if ($code !== null) {
            $params['code'] = $code;
        }
        $fields = 'id, userid, code, timemodified';
        return array_values($DB->get_records(self::CASES, $params, 'code ASC, userid ASC', $fields, 0, $limit));
    }

    /**
     * Open review cases of one user.
     *
     * @param int $userid User id.
     * @return string[] Review codes.
     */
    public static function user_cases(int $userid): array {
        global $DB;
        return array_values($DB->get_fieldset_select(self::CASES, 'code', "userid = :u AND status = 'open'", ['u' => $userid]));
    }

    /**
     * Snapshot, evaluate and (in repair mode) apply and record for one user.
     *
     * @param \stdClass $snapshot Snapshot.
     * @param string $mode Mode.
     * @param string $source Source.
     * @param int $now Current time.
     * @return \stdClass ->repairs, ->reviews, ->changed.
     */
    private static function process(\stdClass $snapshot, string $mode, string $source, int $now): \stdClass {
        global $DB;
        $verdict = self::evaluate($snapshot);
        $result = (object) ['repairs' => $verdict['repairs'], 'reviews' => $verdict['reviews'], 'changed' => false];
        if ($mode !== self::MODE_REPAIR) {
            return $result;
        }
        $userid = $snapshot->userid;
        $applied = [];
        $transaction = $DB->start_delegated_transaction();
        // One rule at a time, re-reading the state in between: expiring an overdue account changes what
        // else is expected, and every audit row must describe the state that rule actually found.
        $tried = [];
        while ($pending = array_values(array_diff($verdict['repairs'], $tried))) {
            $rule = $pending[0];
            $tried[] = $rule;
            if (self::apply_rule($snapshot, $rule, $source, $now)) {
                $applied[] = $rule;
                $snapshot = self::inspect_users([$userid], $now)[$userid] ?? $snapshot;
                $verdict = self::evaluate($snapshot);
            }
        }
        self::store_cases($userid, $verdict['reviews'], $now);
        $transaction->allow_commit();
        $result->repairs = array_values(array_unique($applied));
        $result->reviews = $verdict['reviews'];
        $result->changed = (bool) $applied;
        return $result;
    }

    /**
     * Apply one per-user repair rule through the owning plugin and write the audit row.
     *
     * @param \stdClass $snapshot Snapshot before the change.
     * @param string $rule Rule.
     * @param string $source Source.
     * @param int $now Current time.
     * @return bool Whether something changed.
     */
    private static function apply_rule(\stdClass $snapshot, string $rule, string $source, int $now): bool {
        $userid = $snapshot->userid;
        $before = self::describe($snapshot);
        $changed = false;
        $changes = '';
        if ($rule === self::RULE_UNRESTRICT) {
            $removed = \enrol_flexaccess\api::unrestrict_completely($userid);
            $changed = $removed > 0;
            $changes = 'role flexaccessrestricted removed (' . $removed . ')';
        } else {
            foreach (self::RULE_CODES[$rule] as $code) {
                if (isset($snapshot->mismatches[$code]) && \auth_flexaccess\api::repair_state_mismatch($userid, $code)) {
                    $changed = true;
                }
            }
            $changes = [
                self::RULE_EXPIRE => 'accountstate -> expired, user.suspended -> 1',
                self::RULE_RELOCK => 'user.suspended 0 -> 1',
                self::RULE_UNSUSPEND => 'user.suspended 1 -> 0',
                self::RULE_RESTRICT => 'role flexaccessrestricted assigned',
            ][$rule];
        }
        if ($changed) {
            self::audit($userid, $rule, $source, $before, $changes, $now);
        }
        return $changed;
    }

    /**
     * Site-wide deterministic repairs of the role model, done once at the start of a run.
     *
     * @param string $source Source.
     * @param int $now Current time.
     * @param \stdClass $state Run state (counters are updated).
     * @return void
     */
    private static function apply_site_repairs(string $source, int $now, \stdClass $state): void {
        $problems = \enrol_flexaccess\api::role_model_problems();
        $definition = array_diff($problems, ['participantsystemassigned']);
        if ($definition) {
            \enrol_flexaccess\api::repair_role_model();
            self::audit(0, self::RULE_ROLEMODEL, $source, implode(',', $definition), 'role definitions restored', $now);
            $state->rolefixes++;
        }
        foreach (\enrol_flexaccess\api::remove_system_participant_assignments() as $userid) {
            self::audit(
                $userid,
                self::RULE_SYSTEMPARTICIPANT,
                $source,
                'participant role at system level',
                'system-level assignment removed',
                $now
            );
            $state->rolefixes++;
        }
    }

    /**
     * Record users holding the restriction role without any FlexAccess account as review cases.
     *
     * @param int $now Current time.
     * @return void
     */
    private static function record_orphan_restrictions(int $now): void {
        global $DB;
        $orphans = [];
        foreach (\auth_flexaccess\api::find_state_mismatches($now, 10000) as $m) {
            if ($m->code === 'orphan_restriction') {
                $orphans[(int) $m->userid] = true;
            }
        }
        foreach (array_keys($orphans) as $userid) {
            self::store_cases($userid, [self::REVIEW_ORPHAN_RESTRICTION], $now);
        }
        // Orphan cases whose restriction is gone are resolved.
        $open = $DB->get_fieldset_select(
            self::CASES,
            'userid',
            "code = :c AND status = 'open'",
            ['c' => self::REVIEW_ORPHAN_RESTRICTION]
        );
        foreach ($open as $userid) {
            if (!isset($orphans[(int) $userid])) {
                $DB->set_field(self::CASES, 'status', 'resolved', ['userid' => $userid, 'code' => self::REVIEW_ORPHAN_RESTRICTION]);
                $DB->set_field(self::CASES, 'timemodified', $now, ['userid' => $userid, 'code' => self::REVIEW_ORPHAN_RESTRICTION]);
            }
        }
    }

    /**
     * Upsert a user's review cases; write only where something changes (idempotence).
     *
     * @param int $userid User id.
     * @param string[] $reviews Current review codes.
     * @param int $now Current time.
     * @return void
     */
    private static function store_cases(int $userid, array $reviews, int $now): void {
        global $DB;
        $existing = $DB->get_records(self::CASES, ['userid' => $userid], '', 'id, code, status');
        $bycode = [];
        foreach ($existing as $row) {
            $bycode[$row->code] = $row;
        }
        foreach ($reviews as $code) {
            if (!isset($bycode[$code])) {
                $DB->insert_record(self::CASES, (object) [
                    'userid' => $userid, 'code' => $code, 'status' => 'open', 'timecreated' => $now, 'timemodified' => $now,
                ]);
            } else if ($bycode[$code]->status !== 'open') {
                $DB->update_record(self::CASES, (object) ['id' => $bycode[$code]->id, 'status' => 'open', 'timemodified' => $now]);
            }
        }
        foreach ($bycode as $code => $row) {
            // Orphan cases are managed by the site-wide pass, not per account.
            if ($row->status === 'open' && !in_array($code, $reviews, true) && $code !== self::REVIEW_ORPHAN_RESTRICTION) {
                $DB->update_record(self::CASES, (object) ['id' => $row->id, 'status' => 'resolved', 'timemodified' => $now]);
            }
        }
    }

    /**
     * Inspect-only report over the accounts (no data change).
     *
     * @param int $now Current time.
     * @param int $timelimit Seconds (0 = unlimited).
     * @return \stdClass ->checked, ->complete, ->repairs (rule => user ids), ->reviews (code => user ids).
     */
    private static function inspect_all(int $now, int $timelimit): \stdClass {
        $started = microtime(true);
        $report = (object) ['checked' => 0, 'complete' => false, 'repairs' => [], 'reviews' => []];
        $cursor = 0;
        do {
            $userids = \auth_flexaccess\api::list_account_userids($cursor, self::BATCH);
            if (!$userids) {
                $report->complete = true;
                break;
            }
            foreach (self::inspect_users($userids, $now) as $snapshot) {
                $verdict = self::evaluate($snapshot);
                foreach ($verdict['repairs'] as $rule) {
                    $report->repairs[$rule][] = $snapshot->userid;
                }
                foreach ($verdict['reviews'] as $code) {
                    $report->reviews[$code][] = $snapshot->userid;
                }
                $report->checked++;
            }
            $cursor = (int) end($userids);
        } while ($timelimit === 0 || microtime(true) - $started < $timelimit);
        $problems = array_diff(\enrol_flexaccess\api::role_model_problems(), ['participantsystemassigned']);
        if ($problems) {
            $report->repairs[self::RULE_ROLEMODEL][] = 0;
        }
        $system = \enrol_flexaccess\api::find_role_mismatches(1)['systemparticipant'];
        if ($system > 0) {
            $report->repairs[self::RULE_SYSTEMPARTICIPANT] = array_fill(0, $system, 0);
        }
        return $report;
    }

    /**
     * Users (of the given set) holding the restriction role at system level, any component.
     *
     * @param int[] $userids User ids.
     * @return array<int, bool>
     */
    private static function restricted_users(array $userids): array {
        global $DB;
        $roleid = \enrol_flexaccess\local\participant_role::get_restriction_id();
        if ($roleid === 0 || !$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['roleid'] = $roleid;
        $params['ctx'] = \context_system::instance()->id;
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid = :ctx AND userid $insql",
            $params
        );
        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Compact, non-sensitive description of the state before a change.
     *
     * @param \stdClass $snapshot Snapshot.
     * @return string
     */
    private static function describe(\stdClass $snapshot): string {
        return implode('/', [
            $snapshot->account->type,
            $snapshot->account->state,
            'suspended=' . $snapshot->user->suspended,
            'restricted=' . ($snapshot->restricted ? 1 : 0),
            'lockedby=' . ($snapshot->account->lockedby ?? '-'),
        ]);
    }

    /**
     * Write an audit row (and a log event outside the upgrade).
     *
     * @param int $userid Affected user (0 = site-wide).
     * @param string $rule Rule.
     * @param string $source Source.
     * @param string $before State before.
     * @param string $changes Changes.
     * @param int $now Current time.
     * @return void
     */
    private static function audit(int $userid, string $rule, string $source, string $before, string $changes, int $now): void {
        global $DB, $USER;
        $actorid = (!during_initial_install() && isloggedin() && !isguestuser()) ? (int) $USER->id : 0;
        $DB->insert_record(self::LOG, (object) [
            'userid' => $userid,
            'rule' => $rule,
            'source' => $source,
            'actorid' => $actorid,
            'beforestate' => \core_text::substr($before, 0, 255),
            'changes' => \core_text::substr($changes, 0, 255),
            'result' => 'repaired',
            'timecreated' => $now,
        ]);
    }

    /**
     * A new, empty run state.
     *
     * @param string $source Source.
     * @param int $now Current time.
     * @return \stdClass
     */
    private static function fresh_state(string $source, int $now): \stdClass {
        return (object) [
            'status' => 'running',
            'source' => $source,
            'cursor' => 0,
            'started' => $now,
            'finished' => 0,
            'lastfullscan' => (int) (self::stored_lastfullscan()),
            'checked' => 0,
            'repairedaccounts' => 0,
            'rolefixes' => 0,
            'corefixes' => 0,
            'total' => $now > 0 ? \auth_flexaccess\api::count_all_accounts() : 0,
        ];
    }

    /**
     * The time of the last complete scan from the stored state, if any.
     *
     * @return int
     */
    private static function stored_lastfullscan(): int {
        $raw = (string) get_config('tool_flexaccess', self::STATE);
        $state = $raw !== '' ? json_decode($raw) : null;
        return $state ? (int) ($state->lastfullscan ?? 0) : 0;
    }

    /**
     * Persist the run state.
     *
     * @param \stdClass $state State.
     * @return void
     */
    private static function save_state(\stdClass $state): void {
        set_config(self::STATE, json_encode($state), 'tool_flexaccess');
    }
}
