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
    /** Repair: add the missing restriction to live temporary/pending accounts. */
    public const REPAIR_RESTRICT = 'restricttemporary';
    /** Repair: remove a stale restriction from ACTIVE accounts. */
    public const REPAIR_UNRESTRICT = 'unrestrictactive';
    /** Repair: remove restriction roles held without a FlexAccess account. */
    public const REPAIR_ORPHAN = 'orphanrestriction';
    /** Repair: re-apply the suspension of EXPIRED/SUSPENDED accounts. */
    public const REPAIR_RELOCK = 'relocklocked';

    /** Mismatch code handled by each account repair. */
    private const REPAIR_CODES = [
        self::REPAIR_RESTRICT => 'temporary_unrestricted',
        self::REPAIR_UNRESTRICT => 'active_restricted',
        self::REPAIR_ORPHAN => 'orphan_restriction',
        self::REPAIR_RELOCK => 'locked_unsuspended',
    ];

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
        $repairs = array_flip(self::REPAIR_CODES);
        $items = [];
        foreach ($counts as $code => $count) {
            $critical = in_array($code, ['temporary_unrestricted', 'locked_unsuspended', 'type_state'], true);
            $items[] = self::item(
                'invariant_' . $code,
                $critical ? self::ERROR : self::WARNING,
                get_string('mismatch_' . $code, 'auth_flexaccess'),
                get_string('health_affected', 'tool_flexaccess', $count),
                $repairs[$code] ?? null,
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
     * Items a repair would change (preview).
     *
     * @param string $repair Repair code.
     * @param int|null $now Current time.
     * @return array ->userids (int[]), ->problems (string[] for the role repair).
     */
    public static function preview(string $repair, ?int $now = null): array {
        if ($repair === self::REPAIR_ROLES) {
            return ['userids' => [], 'problems' => \enrol_flexaccess\api::role_model_problems()];
        }
        if (!isset(self::REPAIR_CODES[$repair])) {
            return ['userids' => [], 'problems' => []];
        }
        $code = self::REPAIR_CODES[$repair];
        $userids = [];
        foreach (\auth_flexaccess\api::find_state_mismatches($now, 5000) as $mismatch) {
            if ($mismatch->code === $code) {
                $userids[] = (int) $mismatch->userid;
            }
        }
        return ['userids' => array_values(array_unique($userids)), 'problems' => []];
    }

    /**
     * Execute a repair. The caller has checked capability and sesskey.
     *
     * @param string $repair Repair code.
     * @param int|null $now Current time.
     * @return int Number of items changed.
     */
    public static function repair(string $repair, ?int $now = null): int {
        $preview = self::preview($repair, $now);
        $changed = 0;
        $userids = [];
        if ($repair === self::REPAIR_ROLES) {
            if ($preview['problems'] || \enrol_flexaccess\api::find_role_mismatches(1)['systemparticipant'] > 0) {
                \enrol_flexaccess\api::repair_role_model();
                $changed = 1;
            }
        } else if (isset(self::REPAIR_CODES[$repair])) {
            foreach ($preview['userids'] as $userid) {
                if (\auth_flexaccess\api::repair_state_mismatch($userid, self::REPAIR_CODES[$repair])) {
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
        return array_merge([self::REPAIR_ROLES], array_keys(self::REPAIR_CODES));
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
