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

namespace tool_flexaccess;

use enrol_flexaccess\local\participant_role;
use tool_flexaccess\local\reconciliation;

/**
 * The orphan pass of the reconciliation is set-based, idempotent and resumable (tool_flexaccess #17).
 *
 * Budget: a fixed number of queries and a fixed PHP memory whatever the number of orphans (the cases are brought
 * in line by set-based statements in the database); a second pass writes nothing; a pass interrupted between two
 * statements is completed by the next one; only a complete scan resolves cases.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\reconciliation
 */
final class reconciliation_scale_test extends \advanced_testcase {
    /** @var \core\clock Frozen clock. */
    private \core\clock $clock;

    /** Reads of one orphan pass besides the keyset blocks: role and context lookups and the two counts. */
    private const READ_BUDGET = 8;

    /** Runtime of one orphan pass in seconds, up to the documented capacity (generous for shared CI runners). */
    private const RUNTIME_BUDGET = 30.0;

    /** PHP memory of one orphan pass, whatever the number of orphans (bytes): at most one keyset block is held. */
    private const MEMORY_BUDGET = 2097152;

    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\auth_flexaccess\api') || !class_exists('\enrol_flexaccess\local\participant_role')) {
            $this->markTestSkipped('auth_flexaccess/enrol_flexaccess are not installed.');
        }
        $this->clock = $this->mock_clock_with_frozen();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        participant_role::ensure();
    }

    /**
     * Users holding the restriction role without a FlexAccess account, created in bulk.
     *
     * @param int $n Number of orphans.
     * @return int[] User ids.
     */
    private function orphans(int $n): array {
        global $CFG, $DB;
        if ($n === 0) {
            return [];
        }
        $prefix = 'orphan' . strtolower(random_string(6));
        $now = $this->clock->time();
        foreach (array_chunk(range(1, $n), 1000) as $chunk) {
            $DB->insert_records('user', array_map(static fn(int $i): array => [
                'username' => $prefix . $i, 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
                'email' => "$prefix$i@example.com", 'firstname' => 'Orphan', 'lastname' => (string) $i,
                'timecreated' => $now, 'timemodified' => $now,
            ], $chunk));
        }
        $ids = array_map('intval', $DB->get_fieldset_select('user', 'id', $DB->sql_like('username', ':p'), ['p' => "$prefix%"]));
        $roleid = participant_role::get_restriction_id();
        $contextid = \context_system::instance()->id;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $DB->insert_records('role_assignments', array_map(static fn(int $id): array => [
                'roleid' => $roleid, 'contextid' => $contextid, 'userid' => $id, 'timemodified' => $now,
                'modifierid' => 0, 'component' => '', 'itemid' => 0, 'sortorder' => 0,
            ], $chunk));
        }
        sort($ids);
        $this->analyze();
        return $ids;
    }

    /**
     * Fresh planner statistics after a bulk fixture, as autovacuum keeps them in operation (PostgreSQL only; MySQL and
     * MariaDB keep InnoDB statistics up to date themselves, and ANALYZE TABLE would commit the test transaction there).
     * Without them PostgreSQL plans for empty tables and the orphan query becomes quadratic - see
     * docs/reconciliation-capacity.md.
     */
    private function analyze(): void {
        global $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            return;
        }
        foreach (['user', 'role_assignments', 'auth_flexaccess_account', 'tool_flexaccess_reconcile'] as $table) {
            $DB->execute('ANALYZE {' . $table . '}');
        }
    }

    /**
     * One orphan pass with its query counts and the PHP memory it needed.
     *
     * @param callable|null $afterstep Fault injection.
     * @return array{result:array, reads:int, writes:int, memory:int, seconds:float}
     */
    private function pass(?callable $afterstep = null): array {
        global $DB;
        // Class loading and cache filling are one-off costs of the first call, not memory of the pass.
        // Statistics as autovacuum keeps them after the previous pass or fixture.
        $this->analyze();
        // Caches (roles, contexts) are filled by an equivalent query first.
        \auth_flexaccess\api::count_orphan_restrictions();
        class_exists(reconciliation::class);
        $watch = \tool_flexaccess\local\stopwatch::instance();
        $watch->seconds_since($watch->start());
        gc_collect_cycles();
        $base = memory_get_usage();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $reads = $DB->perf_get_reads();
        $writes = $DB->perf_get_writes();
        // A duration, measured with the monotonic stopwatch (the business clock is frozen in this test).
        $mark = $watch->start();
        $result = reconciliation::record_orphan_restrictions($this->clock->time(), $afterstep);
        $seconds = $watch->seconds_since($mark);
        // Without memory_reset_peak_usage() (PHP 8.1) only the retained memory can be measured.
        $memory = (function_exists('memory_reset_peak_usage') ? memory_get_peak_usage() : memory_get_usage()) - $base;
        return [
            'result' => $result,
            'reads' => $DB->perf_get_reads() - $reads,
            'writes' => $DB->perf_get_writes() - $writes,
            'memory' => $memory,
            'seconds' => $seconds,
        ];
    }

    /**
     * Open orphan cases.
     *
     * @return int[] User ids.
     */
    private function open_cases(): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select('tool_flexaccess_reconcile', 'userid', 'code = :c AND status = :s', [
            'c' => reconciliation::REVIEW_ORPHAN_RESTRICTION, 's' => 'open',
        ]));
        sort($ids);
        return $ids;
    }

    /**
     * Sizes of the query and memory budget.
     *
     * @return array
     */
    public static function sizes(): array {
        $sizes = ['0' => [0], '100' => [100], '1000' => [1000], '10000' => [10000]];
        // The documented capacity check (docs/reconciliation-capacity.md) adds 100000; too slow for every CI run.
        if (getenv('FLEXACCESS_SCALE_LARGE')) {
            $sizes['100000'] = [100000];
        }
        return $sizes;
    }

    /**
     * A fixed number of queries and a fixed PHP memory budget whatever the number of orphans; a second pass
     * writes nothing.
     *
     * @dataProvider sizes
     * @param int $n Number of orphans.
     */
    public function test_query_and_memory_budget_and_idempotence(int $n): void {
        $orphans = $this->orphans($n);

        $first = $this->pass();
        $this->assertSame(['opened' => $n, 'reopened' => 0, 'resolved' => 0], $first['result']);
        $this->assertSame($orphans, $this->open_cases());
        // Reads: at most three per keyset block of ORPHAN_CHUNK orphans (next block, existing cases, driver lookups
        // of the bulk insert) and the counts.
        $blocks = intdiv($n, reconciliation::ORPHAN_CHUNK) + 1;
        $this->assertLessThanOrEqual(3 * $blocks + self::READ_BUDGET, $first['reads'], "reads for $n orphans");
        // Writes: the bulk inserts of the blocks, split by the driver (PostgreSQL 100, MySQL/MariaDB 50 rows per
        // statement); none when there is nothing to insert.
        $this->assertLessThanOrEqual((int) ceil($n / 50), $first['writes'], "writes for $n orphans");
        $this->assertSame($n > 0, $first['writes'] > 0);
        $this->assertLessThan(self::MEMORY_BUDGET, $first['memory'], "PHP memory for $n orphans");
        $this->assertLessThan(self::RUNTIME_BUDGET, $first['seconds'], "runtime for $n orphans");

        // The second pass writes nothing.
        $second = $this->pass();
        $this->assertSame(['opened' => 0, 'reopened' => 0, 'resolved' => 0], $second['result']);
        $this->assertSame(0, $second['writes']);
        $this->assertLessThanOrEqual(3 * $blocks + self::READ_BUDGET, $second['reads']);
        $this->assertLessThan(self::MEMORY_BUDGET, $second['memory'], "PHP memory for $n orphans, second pass");
    }

    public function test_resolving_and_reopening_set_status_and_time_together(): void {
        global $DB;
        $orphans = $this->orphans(1200);
        $this->pass();

        // 700 restrictions are lifted: their cases are resolved by one statement that sets status and time together.
        $lifted = array_slice($orphans, 0, 700);
        [$insql, $params] = $DB->get_in_or_equal($lifted, SQL_PARAMS_NAMED);
        $DB->delete_records_select('role_assignments', "userid $insql", $params);
        $this->clock->bump(60);
        $resolve = $this->pass();
        $this->assertSame(['opened' => 0, 'reopened' => 0, 'resolved' => 700], $resolve['result']);
        $this->assertSame(1, $resolve['writes']);
        $this->assertSame(array_slice($orphans, 700), $this->open_cases());
        $this->assertSame(700, $DB->count_records('tool_flexaccess_reconcile', [
            'code' => reconciliation::REVIEW_ORPHAN_RESTRICTION, 'status' => 'resolved', 'timemodified' => $this->clock->time(),
        ]));

        // Restricted again: the same cases are reopened, not duplicated.
        $roleid = participant_role::get_restriction_id();
        foreach (array_slice($lifted, 0, 3) as $userid) {
            role_assign($roleid, $userid, \context_system::instance()->id);
        }
        $this->clock->bump(60);
        $reopen = $this->pass();
        $this->assertSame(['opened' => 0, 'reopened' => 3, 'resolved' => 0], $reopen['result']);
        $this->assertSame(1, $reopen['writes']);
        $code = reconciliation::REVIEW_ORPHAN_RESTRICTION;
        $this->assertSame(1200, $DB->count_records('tool_flexaccess_reconcile', ['code' => $code]));
        $this->assertSame(0, $this->pass()['writes']);
    }

    public function test_a_pass_interrupted_between_statements_is_completed_by_the_next(): void {
        global $DB;
        $orphans = $this->orphans(1200);
        $this->pass();
        // 700 restrictions lifted (to be resolved) and 300 new orphans (to be opened).
        [$insql, $params] = $DB->get_in_or_equal(array_slice($orphans, 0, 700), SQL_PARAMS_NAMED);
        $DB->delete_records_select('role_assignments', "userid $insql", $params);
        $new = $this->orphans(300);
        $this->clock->bump(60);
        $steps = [];
        try {
            $this->pass(function (string $step) use (&$steps): void {
                $steps[] = $step;
                throw new \RuntimeException("Injected failure after the step '$step'.");
            });
            $this->fail('The injected failure must surface.');
        } catch (\RuntimeException $e) {
            $this->assertSame(['opened'], $steps);
        }
        // A consistent intermediate state: the new cases complete, the lifted ones still open.
        $this->assertCount(1500, $this->open_cases());
        $this->assertSame(300, $DB->count_records('tool_flexaccess_reconcile', [
            'timecreated' => $this->clock->time(), 'timemodified' => $this->clock->time(),
        ]));

        $resume = $this->pass();
        $this->assertSame(['opened' => 0, 'reopened' => 0, 'resolved' => 700], $resume['result']);
        $expected = array_merge(array_slice($orphans, 700), $new);
        sort($expected);
        $this->assertSame($expected, $this->open_cases());
        $this->assertSame(0, $this->pass()['writes']);
    }

    public function test_only_a_complete_scan_resolves_cases(): void {
        global $DB;
        // An open orphan case whose restriction is gone, and more accounts than one scan batch.
        $gone = (int) $this->getDataGenerator()->create_user()->id;
        $DB->insert_record('tool_flexaccess_reconcile', (object) [
            'userid' => $gone, 'code' => reconciliation::REVIEW_ORPHAN_RESTRICTION, 'status' => 'open',
            'timecreated' => $this->clock->time(), 'timemodified' => $this->clock->time(),
        ]);
        for ($i = 0; $i <= reconciliation::BATCH; $i++) {
            \auth_flexaccess\api::create_temporary_user($this->clock->time() + DAYSECS);
        }

        // A run that stops after its first batch (time budget used up) is partial: nothing is resolved.
        $expired = new class extends \tool_flexaccess\local\stopwatch {
            /**
             * The time budget is always used up.
             *
             * @param int $mark Start.
             * @param int $budget Budget.
             * @return bool
             */
            public function within(int $mark, int $budget): bool {
                return false;
            }
        };
        \core\di::set(\tool_flexaccess\local\stopwatch::class, $expired);
        set_config('reconcile_state', '', 'tool_flexaccess');
        $state = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK, 1);
        $this->assertSame('running', $state->status);
        $this->assertSame([$gone], $this->open_cases());

        // The continuation completes the scan and only then resolves the case.
        \core\di::set(\tool_flexaccess\local\stopwatch::class, new \tool_flexaccess\local\stopwatch());
        $state = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK);
        $this->assertSame('done', $state->status);
        $this->assertSame([], $this->open_cases());
        $this->assertSame(1, $state->orphans->resolved);
    }

    public function test_inspection_report_counts_exactly_and_lists_a_bounded_number(): void {
        $n = reconciliation::REPORT_IDS + 50;
        for ($i = 0; $i < $n; $i++) {
            // A temporary account without the restriction role: one repairable inconsistency each.
            $userid = \auth_flexaccess\api::create_temporary_user($this->clock->time() + DAYSECS);
            role_unassign_all(['userid' => $userid, 'roleid' => participant_role::get_restriction_id()]);
        }
        $report = reconciliation::run(reconciliation::MODE_INSPECT, reconciliation::SOURCE_STATUS);
        $this->assertSame($n, $report->repaircounts[reconciliation::RULE_RESTRICT]);
        $this->assertCount(reconciliation::REPORT_IDS, $report->repairs[reconciliation::RULE_RESTRICT]);
        $this->assertSame($n, $report->affected);
        $preview = \tool_flexaccess\local\health::preview(\tool_flexaccess\local\health::REPAIR_RECONCILE);
        $this->assertSame($n, $preview['total']);
        $this->assertCount(reconciliation::REPORT_IDS, $preview['userids']);
    }

    public function test_orphan_repair_runs_in_batches_with_a_bounded_list(): void {
        global $DB;
        $this->setAdminUser();
        $n = reconciliation::BATCH * 2 + 50;
        $this->orphans($n);
        $preview = \tool_flexaccess\local\health::preview(\tool_flexaccess\local\health::REPAIR_ORPHAN);
        $this->assertSame($n, $preview['total']);
        $this->assertCount(reconciliation::REPORT_IDS, $preview['userids']);

        $sink = $this->redirectEvents();
        $this->assertSame($n, \tool_flexaccess\local\health::repair(\tool_flexaccess\local\health::REPAIR_ORPHAN));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_flexaccess\event\health_repaired));
        $sink->close();
        $this->assertSame(0, \auth_flexaccess\api::count_orphan_restrictions());
        // The audit event records the exact number and a bounded list.
        $this->assertCount(1, $events);
        $other = $events[0]->other;
        $this->assertSame($n, $other['affected']);
        $this->assertCount(reconciliation::REPORT_IDS, $other['userids']);
    }
}
