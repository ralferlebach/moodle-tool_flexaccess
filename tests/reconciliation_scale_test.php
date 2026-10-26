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
 * Query budget: a fixed number of reads whatever the number of orphans, and writes in chunks; a second
 * pass writes nothing; a pass interrupted between chunks is completed by the next one; only a complete
 * scan resolves cases.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\reconciliation
 */
final class reconciliation_scale_test extends \advanced_testcase {
    /** @var \core\clock Frozen clock. */
    private \core\clock $clock;

    /** Reads of one orphan pass, whatever the number of orphans: role ids, the orphan list, the cases. */
    private const READ_BUDGET = 6;

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
        return $ids;
    }

    /**
     * One orphan pass with its query counts.
     *
     * @param callable|null $afterchunk Fault injection.
     * @return array{result:array, reads:int, writes:int}
     */
    private function pass(?callable $afterchunk = null): array {
        global $DB;
        $reads = $DB->perf_get_reads();
        $writes = $DB->perf_get_writes();
        $result = reconciliation::record_orphan_restrictions($this->clock->time(), $afterchunk);
        return ['result' => $result, 'reads' => $DB->perf_get_reads() - $reads, 'writes' => $DB->perf_get_writes() - $writes];
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
     * Sizes of the query budget.
     *
     * @return array
     */
    public static function sizes(): array {
        return ['0' => [0], '100' => [100], '1000' => [1000], '10000' => [10000]];
    }

    /**
     * A fixed number of reads, chunked writes and no writes on the second pass, for 0 to 10000 orphans.
     *
     * @dataProvider sizes
     * @param int $n Number of orphans.
     */
    public function test_query_budget_and_idempotence(int $n): void {
        $orphans = $this->orphans($n);

        $first = $this->pass();
        $this->assertSame(['opened' => $n, 'reopened' => 0, 'resolved' => 0], $first['result']);
        $this->assertSame($orphans, $this->open_cases());
        $this->assertLessThanOrEqual(self::READ_BUDGET, $first['reads'], "reads for $n orphans");
        // Bulk inserts: one statement per insert chunk of the driver (PostgreSQL 100 rows, MySQL/MariaDB 50 rows),
        // not one per orphan.
        $this->assertLessThanOrEqual((int) ceil($n / 50) + 1, $first['writes'], "writes for $n orphans");

        // The second pass writes nothing.
        $second = $this->pass();
        $this->assertSame(['opened' => 0, 'reopened' => 0, 'resolved' => 0], $second['result']);
        $this->assertSame(0, $second['writes']);
        $this->assertLessThanOrEqual(self::READ_BUDGET, $second['reads']);
    }

    public function test_resolving_and_reopening_set_status_and_time_together(): void {
        global $DB;
        $orphans = $this->orphans(1200);
        $this->pass();

        // 700 restrictions are lifted: their cases are resolved, in chunks, with status and time in one statement.
        $lifted = array_slice($orphans, 0, 700);
        [$insql, $params] = $DB->get_in_or_equal($lifted, SQL_PARAMS_NAMED);
        $DB->delete_records_select('role_assignments', "userid $insql", $params);
        $this->clock->bump(60);
        $resolve = $this->pass();
        $this->assertSame(['opened' => 0, 'reopened' => 0, 'resolved' => 700], $resolve['result']);
        $this->assertSame((int) ceil(700 / reconciliation::CHUNK), $resolve['writes']);
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

    public function test_a_pass_interrupted_between_chunks_is_completed_by_the_next(): void {
        global $DB;
        $orphans = $this->orphans(1200);
        $chunks = 0;
        try {
            $this->pass(function () use (&$chunks): void {
                if (++$chunks === 1) {
                    throw new \RuntimeException('Injected failure after the first chunk.');
                }
            });
            $this->fail('The injected failure must surface.');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $chunks);
        }
        // A consistent intermediate state: exactly the first chunk, every row complete.
        $this->assertCount(reconciliation::CHUNK, $this->open_cases());
        $this->assertSame(0, $DB->count_records_select('tool_flexaccess_reconcile', 'timemodified <> :t OR timecreated <> :t2', [
            't' => $this->clock->time(), 't2' => $this->clock->time(),
        ]));

        $resume = $this->pass();
        $this->assertSame(['opened' => 1200 - reconciliation::CHUNK, 'reopened' => 0, 'resolved' => 0], $resume['result']);
        $this->assertSame($orphans, $this->open_cases());
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
}
