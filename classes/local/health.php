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
 * Cross-plugin readiness of the FlexAccess family: plugins, roles, invariants, tasks, policies, mail.
 *
 * Diagnosis is read-only. Repairs are offered only where they are deterministic and safe (they never
 * grant access, never re-enrol and never convert an identity); each one is previewed, capability- and
 * sesskey-protected on the page, and recorded in the event log.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class health {
    /** Status: fine. */
    public const OK = 'ok';
    /** Status: informational. */
    public const INFO = 'info';
    /** Status: degraded. */
    public const WARNING = 'warning';
    /** Status: broken. */
    public const ERROR = 'error';

    /** Repair: recreate/repair both FlexAccess roles. */
    public const REPAIR_ROLES = 'rolemodel';
    /** Repair: the safe account reconciliation (same service as the upgrade backfill). */
    public const REPAIR_RECONCILE = 'reconcile';
    /** Repair: remove restriction roles held without a FlexAccess account (explicit, per preview). */
    public const REPAIR_ORPHAN = 'orphanrestriction';

    /** Mismatch codes the safe reconciliation repairs (subject to its own rules). */
    private const RECONCILE_CODES = ['temporary_unrestricted', 'active_restricted', 'locked_unsuspended', 'overdue',
        'active_suspended', 'live_suspended'];

    /** Scheduled tasks FlexAccess depends on. */
    public const TASKS = [
        '\auth_flexaccess\task\process_mail_queue',
        '\auth_flexaccess\task\expire_accounts',
        '\enrol_flexaccess\task\expire_enrolments',
    ];

    /** A task whose next run lies this far in the past is overdue (cron not running). */
    private const TASK_OVERDUE = 2 * HOURSECS;

    /** Oldest queued mail age that indicates a stuck queue. */
    private const MAIL_STUCK = DAYSECS;

    /**
     * Run all checks.
     *
     * @param int|null $now Current time.
     * @return array<string, \stdClass[]> Section id => items (key, status, label, detail, repair, count).
     */
    public static function run(?int $now = null): array {
        $now = $now ?? time();
        $sections = ['plugins' => self::check_plugins()];
        if (!class_exists('\auth_flexaccess\api') || !class_exists('\enrol_flexaccess\api')) {
            return $sections;
        }
        $sections['roles'] = self::check_roles();
        $sections['invariants'] = self::check_invariants($now);
        $sections['tasks'] = self::check_tasks($now);
        $sections['policies'] = self::check_policies();
        $sections['mail'] = self::check_mail($now);
        $sections['reconciliation'] = self::check_reconciliation($now);
        return $sections;
    }

    /**
     * Worst status across all sections.
     *
     * @param array $sections Result of {@see self::run()}.
     * @return string
     */
    public static function overall(array $sections): string {
        $rank = [self::OK => 0, self::INFO => 0, self::WARNING => 1, self::ERROR => 2];
        $worst = self::OK;
        foreach ($sections as $items) {
            foreach ($items as $item) {
                if ($rank[$item->status] > $rank[$worst]) {
                    $worst = $item->status;
                }
            }
        }
        return $worst;
    }

    /**
     * HEALTH-001: installation, activation and dependency satisfaction of the four plugins.
     *
     * @return \stdClass[]
     */
    public static function check_plugins(): array {
        $items = [];
        $pluginman = \core_plugin_manager::instance();
        $components = [
            'auth_flexaccess' => true,
            'enrol_flexaccess' => true,
            'tool_flexaccess' => true,
            'mod_flexaccess' => false,
        ];
        foreach ($components as $component => $required) {
            $info = $pluginman->get_plugin_info($component);
            if (!$info || empty($info->versiondb)) {
                $items[] = self::item(
                    'installed_' . $component,
                    $required ? self::ERROR : self::INFO,
                    get_string('health_notinstalled', 'tool_flexaccess', $component)
                );
                continue;
            }
            if ((int) $info->versiondb !== (int) $info->versiondisk) {
                $items[] = self::item(
                    'upgrade_' . $component,
                    self::ERROR,
                    get_string('health_upgradepending', 'tool_flexaccess', $component)
                );
            }
            foreach ((array) $info->get_other_required_plugins() as $dependency => $minversion) {
                $dep = $pluginman->get_plugin_info($dependency);
                $depversion = $dep ? (int) $dep->versiondb : 0;
                if ($minversion !== ANY_VERSION && $depversion < (int) $minversion) {
                    $items[] = self::item(
                        'dependency_' . $component . '_' . $dependency,
                        self::ERROR,
                        get_string('health_dependency', 'tool_flexaccess', (object) [
                            'component' => $component,
                            'dependency' => $dependency,
                            'required' => $minversion,
                            'installed' => $depversion,
                        ])
                    );
                }
            }
        }
        $auth = is_enabled_auth('flexaccess');
        $enrol = enrol_is_enabled('flexaccess');
        $items[] = self::item('enabled_auth', $auth ? self::OK : self::ERROR, get_string(
            $auth ? 'health_authenabled' : 'health_authdisabled',
            'tool_flexaccess'
        ));
        $items[] = self::item('enabled_enrol', $enrol ? self::OK : ($auth ? self::WARNING : self::ERROR), get_string(
            $enrol ? 'health_enrolenabled' : 'health_enroldisabled',
            'tool_flexaccess'
        ));
        return $items;
    }

    /**
     * HEALTH-002: role model and context levels.
     *
     * @return \stdClass[]
     */
    public static function check_roles(): array {
        $problems = \enrol_flexaccess\api::role_model_problems();
        if (!$problems) {
            return [self::item('rolemodel', self::OK, get_string('health_rolesok', 'tool_flexaccess'))];
        }
        $items = [];
        foreach ($problems as $problem) {
            $items[] = self::item(
                'role_' . $problem,
                self::ERROR,
                get_string('health_role_' . $problem, 'tool_flexaccess'),
                '',
                self::REPAIR_ROLES
            );
        }
        return $items;
    }

    /**
     * HEALTH-003: account/role invariants and course-role consistency.
     *
     * @param int $now Current time.
     * @return \stdClass[]
     */
    public static function check_invariants(int $now): array {
        $counts = [];
        foreach (\auth_flexaccess\api::find_state_mismatches($now, 5000) as $mismatch) {
            $counts[$mismatch->code] = ($counts[$mismatch->code] ?? 0) + 1;
        }
        $items = [];
        foreach ($counts as $code => $count) {
            $critical = in_array($code, ['temporary_unrestricted', 'locked_unsuspended', 'type_state'], true);
            $repair = null;
            if (in_array($code, self::RECONCILE_CODES, true)) {
                $repair = self::REPAIR_RECONCILE;
            } else if ($code === 'orphan_restriction') {
                $repair = self::REPAIR_ORPHAN;
            }
            $items[] = self::item(
                'invariant_' . $code,
                $critical ? self::ERROR : self::WARNING,
                get_string('mismatch_' . $code, 'auth_flexaccess'),
                get_string('health_affected', 'tool_flexaccess', $count),
                $repair,
                $count
            );
        }
        $roles = \enrol_flexaccess\api::find_role_mismatches(1000);
        if ($roles['missingrole']) {
            $items[] = self::item(
                'invariant_courserole',
                self::WARNING,
                get_string('health_courserole', 'tool_flexaccess'),
                get_string('health_affected', 'tool_flexaccess', count($roles['missingrole'])),
                null,
                count($roles['missingrole'])
            );
        }
        if ($roles['systemparticipant'] > 0) {
            $items[] = self::item(
                'invariant_systemparticipant',
                self::ERROR,
                get_string('health_role_participantsystemassigned', 'tool_flexaccess'),
                get_string('health_affected', 'tool_flexaccess', $roles['systemparticipant']),
                self::REPAIR_ROLES,
                $roles['systemparticipant']
            );
        }
        if (!$items) {
            $items[] = self::item('invariants', self::OK, get_string('health_invariantsok', 'tool_flexaccess'));
        }
        return $items;
    }

    /**
     * HEALTH-004: presence, activation and punctuality of the scheduled tasks.
     *
     * @param int $now Current time.
     * @return \stdClass[]
     */
    public static function check_tasks(int $now): array {
        $items = [];
        foreach (self::TASKS as $classname) {
            $task = \core\task\manager::get_scheduled_task($classname);
            $name = ltrim($classname, '\\');
            if (!$task) {
                $items[] = self::item('task_' . $name, self::ERROR, get_string('health_taskmissing', 'tool_flexaccess', $name));
                continue;
            }
            $lastrun = (int) $task->get_last_run_time();
            $detail = get_string(
                'health_tasklastrun',
                'tool_flexaccess',
                $lastrun > 0 ? userdate($lastrun) : get_string('never')
            );
            if ($task->get_disabled()) {
                $label = get_string('health_taskdisabled', 'tool_flexaccess', $name);
                $items[] = self::item('task_' . $name, self::ERROR, $label, $detail);
            } else if ((int) $task->get_next_run_time() > 0 && (int) $task->get_next_run_time() < $now - self::TASK_OVERDUE) {
                $label = get_string('health_taskoverdue', 'tool_flexaccess', $name);
                $items[] = self::item('task_' . $name, self::WARNING, $label, $detail);
            } else {
                $items[] = self::item('task_' . $name, self::OK, get_string('health_taskok', 'tool_flexaccess', $name), $detail);
            }
        }
        return $items;
    }

    /**
     * HEALTH-005: course methods neutralised by a higher policy or the magic-login master switch.
     *
     * @return \stdClass[]
     */
    public static function check_policies(): array {
        $items = [];
        foreach (\enrol_flexaccess\api::policy_conflicts(null, 200) as $conflict) {
            $course = get_course($conflict->courseid);
            $items[] = self::item(
                'policy_' . $conflict->enrolid . '_' . $conflict->flag,
                self::WARNING,
                get_string('health_policy_' . $conflict->cause, 'tool_flexaccess', (object) [
                    'course' => format_string($course->fullname),
                    'method' => get_string('mode' . $conflict->flag, 'enrol_flexaccess'),
                ]),
                (new \moodle_url('/enrol/editinstance.php', [
                    'type' => 'flexaccess',
                    'courseid' => $conflict->courseid,
                    'id' => $conflict->enrolid,
                ]))->out(false)
            );
        }
        if (!$items) {
            $items[] = self::item('policies', self::OK, get_string('health_policiesok', 'tool_flexaccess'));
        }
        return $items;
    }

    /**
     * HEALTH-006: mail funnel; failures that strand users are listed first and as errors.
     *
     * @param int $now Current time.
     * @return \stdClass[]
     */
    public static function check_mail(int $now): array {
        $stats = \auth_flexaccess\api::mail_funnel_stats($now);
        $items = [];
        if ($stats['failedfunnel'] > 0) {
            $items[] = self::item(
                'mail_funnel',
                self::ERROR,
                get_string('health_mailfunnel', 'tool_flexaccess', $stats['failedfunnel'])
            );
        }
        $items[] = self::item(
            'mail_failed',
            $stats['failed'] > 0 ? self::WARNING : self::OK,
            get_string('health_mailfailed', 'tool_flexaccess', $stats['failed'])
        );
        $stuck = $stats['oldestqueuedage'] > self::MAIL_STUCK;
        $items[] = self::item(
            'mail_queued',
            $stuck ? self::WARNING : self::OK,
            get_string('health_mailqueued', 'tool_flexaccess', (object) [
                'count' => $stats['queued'],
                'age' => $stats['oldestqueuedage'] > 0 ? format_time($stats['oldestqueuedage']) : '-',
            ])
        );
        return $items;
    }

    /**
     * MIGRATION-006: result of the account reconciliation (last scan, figures, open review cases).
     *
     * @param int $now Current time.
     * @return \stdClass[]
     */
    public static function check_reconciliation(int $now): array {
        $state = reconciliation::state();
        $items = [];
        if ($state->status === 'never') {
            $items[] = self::item(
                'reconcile_never',
                self::WARNING,
                get_string('reconcile_never', 'tool_flexaccess'),
                '',
                self::REPAIR_RECONCILE
            );
        } else if ($state->status === 'running') {
            $items[] = self::item('reconcile_running', self::INFO, get_string('reconcile_running', 'tool_flexaccess', (object) [
                'checked' => $state->checked,
                'total' => $state->total,
            ]));
        } else {
            $items[] = self::item('reconcile_lastscan', self::OK, get_string(
                'reconcile_lastscan',
                'tool_flexaccess',
                userdate((int) $state->lastfullscan)
            ));
        }
        $items[] = self::item('reconcile_figures', self::INFO, get_string('reconcile_figures', 'tool_flexaccess', (object) [
            'checked' => $state->checked,
            'repaired' => $state->repairedaccounts,
            'roles' => $state->rolefixes,
            'core' => $state->corefixes,
        ]));
        $repairable = self::auto_repairable_count($now);
        $items[] = self::item(
            'reconcile_repairable',
            $repairable > 0 ? self::WARNING : self::OK,
            get_string('reconcile_repairable', 'tool_flexaccess', $repairable),
            '',
            $repairable > 0 ? self::REPAIR_RECONCILE : null,
            $repairable
        );
        $cases = reconciliation::open_case_counts();
        foreach ($cases as $code => $count) {
            $items[] = self::item(
                'reconcile_case_' . $code,
                self::WARNING,
                get_string('reconcile_' . $code, 'tool_flexaccess'),
                (new \moodle_url('/admin/tool/flexaccess/status.php', ['cases' => $code]))->out(false),
                null,
                $count
            );
        }
        if (!$cases) {
            $items[] = self::item('reconcile_nocases', self::OK, get_string('reconcile_nocases', 'tool_flexaccess'));
        }
        return $items;
    }

    /**
     * Number of accounts the safe reconciliation would still change (target: 0).
     *
     * @param int $now Current time.
     * @return int
     */
    public static function auto_repairable_count(int $now): int {
        $userids = [];
        foreach (\auth_flexaccess\api::find_state_mismatches($now, 5000) as $m) {
            if (in_array($m->code, self::RECONCILE_CODES, true)) {
                $userids[] = (int) $m->userid;
            }
        }
        $count = 0;
        foreach (array_chunk(array_values(array_unique($userids)), reconciliation::BATCH) as $chunk) {
            foreach (reconciliation::inspect_users($chunk, $now) as $snapshot) {
                if (reconciliation::evaluate($snapshot)['repairs']) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Items a repair would change (preview).
     *
     * @param string $repair Repair code.
     * @param int|null $now Current time.
     * @return array ->userids (int[]), ->problems (string[] for the role repair).
     */
    public static function preview(string $repair, ?int $now = null): array {
        if ($repair === self::REPAIR_ROLES) {
            return ['userids' => [], 'problems' => \enrol_flexaccess\api::role_model_problems(), 'report' => null];
        }
        if ($repair === self::REPAIR_RECONCILE) {
            // Inspect only: exactly the rules the safe repair would apply, and the review cases.
            $report = reconciliation::run(reconciliation::MODE_INSPECT, reconciliation::SOURCE_STATUS, 60, $now);
            $userids = [];
            foreach ($report->repairs as $ids) {
                $userids = array_merge($userids, array_filter($ids));
            }
            return ['userids' => array_values(array_unique($userids)), 'problems' => [], 'report' => $report];
        }
        $userids = [];
        if ($repair === self::REPAIR_ORPHAN) {
            foreach (\auth_flexaccess\api::find_state_mismatches($now, 5000) as $mismatch) {
                if ($mismatch->code === 'orphan_restriction') {
                    $userids[] = (int) $mismatch->userid;
                }
            }
        }
        return ['userids' => array_values(array_unique($userids)), 'problems' => [], 'report' => null];
    }

    /**
     * Execute a repair. The caller has checked capability and sesskey.
     *
     * @param string $repair Repair code.
     * @param int|null $now Current time.
     * @return int Number of items changed.
     */
    public static function repair(string $repair, ?int $now = null): int {
        $changed = 0;
        $userids = [];
        if ($repair === self::REPAIR_ROLES) {
            $preview = self::preview($repair, $now);
            if ($preview['problems'] || \enrol_flexaccess\api::find_role_mismatches(1)['systemparticipant'] > 0) {
                \enrol_flexaccess\api::repair_role_model();
                \enrol_flexaccess\api::remove_system_participant_assignments();
                $changed = 1;
            }
        } else if ($repair === self::REPAIR_RECONCILE) {
            // A fresh safe-repair run; too large for one request, it continues in the ad-hoc task.
            set_config('reconcile_state', '', 'tool_flexaccess');
            $state = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_STATUS, 60, $now);
            if ($state->status === 'running') {
                reconciliation::queue_continuation();
            }
            $changed = (int) $state->repairedaccounts;
        } else if ($repair === self::REPAIR_ORPHAN) {
            foreach (self::preview($repair, $now)['userids'] as $userid) {
                if (\auth_flexaccess\api::repair_state_mismatch($userid, 'orphan_restriction')) {
                    $changed++;
                    $userids[] = $userid;
                }
            }
        }
        \tool_flexaccess\event\health_repaired::create([
            'context' => \context_system::instance(),
            'other' => ['repair' => $repair, 'affected' => $changed, 'userids' => $userids],
        ])->trigger();
        return $changed;
    }

    /**
     * Known repair codes.
     *
     * @return string[]
     */
    public static function repairs(): array {
        return [self::REPAIR_ROLES, self::REPAIR_RECONCILE, self::REPAIR_ORPHAN];
    }

    /**
     * Build a check item.
     *
     * @param string $key Stable key.
     * @param string $status Status.
     * @param string $label Label.
     * @param string $detail Optional detail (plain text or URL).
     * @param string|null $repair Repair code, when a safe repair exists.
     * @param int $count Affected items.
     * @return \stdClass
     */
    private static function item(
        string $key,
        string $status,
        string $label,
        string $detail = '',
        ?string $repair = null,
        int $count = 0
    ): \stdClass {
        return (object) compact('key', 'status', 'label', 'detail', 'repair', 'count');
    }
}
