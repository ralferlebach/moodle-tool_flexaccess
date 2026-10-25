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

/**
 * Recovery, invitation, campaign, batch and reconciliation times read Moodle's clock (issue #13).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess;

use auth_flexaccess\local\account_service;
use auth_flexaccess\local\login_guard;
use tool_flexaccess\local\batch;
use tool_flexaccess\local\campaign;
use tool_flexaccess\local\invitation;
use tool_flexaccess\local\reconciliation;
use tool_flexaccess\local\recovery;
use tool_flexaccess\local\stopwatch;

/**
 * Boundaries at T-1, T and T+1 with a frozen clock; one reference time per operation with an
 * incrementing clock; run-time budgets measured monotonically, independent of a frozen clock.
 *
 * @package    tool_flexaccess
 * @covers \tool_flexaccess\local\invitation
 * @covers \tool_flexaccess\task\send_invitation_reminders
 * @covers \tool_flexaccess\local\campaign
 * @covers \tool_flexaccess\local\batch
 * @covers \tool_flexaccess\local\recovery
 * @covers \tool_flexaccess\local\reconciliation
 * @covers \tool_flexaccess\local\stopwatch
 */
final class clock_test extends \advanced_testcase {
    /** Reference time T (2033-05-18 03:33:20 UTC, a multiple of 100 s). */
    private const T = 2000000000;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        set_config('auth', 'flexaccess');
        set_config('allowwidening', 1, 'enrol_flexaccess');
        \core\plugin_manager::reset_caches();
    }

    /**
     * Offsets around a boundary.
     *
     * @return array
     */
    public static function offset_provider(): array {
        return ['T-1' => [-1], 'T' => [0], 'T+1' => [1]];
    }

    /**
     * An invitation is acceptable and remindable up to and including its expiry second, measured by the clock.
     *
     * @dataProvider offset_provider
     * @param int $offset Clock offset from T.
     */
    public function test_invitation_expiry_follows_clock(int $offset): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = invitation::create((int) $course->id, 'guest@example.com', self::T, null, self::T - DAYSECS);
        $DB->set_field('tool_flexaccess_invite', 'timesent', self::T - DAYSECS, ['id' => $id]);
        $this->mock_clock_with_frozen(self::T + $offset);

        $open = $offset <= 0;
        $this->assertSame($open, invitation::is_acceptable(invitation::get($id)));
        $this->assertSame($open, invitation::remind($id));
        // The reminder selection agrees with acceptability at the boundary.
        $this->assertSame($open ? [$id] : [], invitation::due_reminders(self::T, self::T + $offset));
    }

    /**
     * The reminder task sends once the reminder delay, measured by the clock, has passed - not before.
     */
    public function test_reminder_task_follows_clock(): void {
        global $DB;
        set_config('invitationreminderdays', 2, 'tool_flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $id = invitation::create((int) $course->id, 'guest@example.com', 0, null, self::T - 3 * DAYSECS);
        $DB->set_field('tool_flexaccess_invite', 'timesent', self::T - 2 * DAYSECS, ['id' => $id]);
        $jobs = fn(): int => $DB->count_records('auth_flexaccess_mailqueue', ['status' => 'queued']);

        $clock = $this->mock_clock_with_frozen(self::T - 1);
        (new task\send_invitation_reminders())->execute();
        $this->assertSame(0, $jobs(), 'One second before the delay has passed.');
        $clock->set_to(self::T);
        (new task\send_invitation_reminders())->execute();
        $this->assertSame(1, $jobs());
        $job = $DB->get_record('auth_flexaccess_mailqueue', ['status' => 'queued'], '*', MUST_EXIST);
        $this->assertSame(self::T, (int) $job->timecreated);
    }

    /**
     * Acceptance stamps the clock's time and uses one reference time for reservation and commit.
     */
    public function test_invitation_acceptance_uses_one_reference_time(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->mock_clock_with_incrementing(self::T);
        $id = invitation::create((int) $course->id, 'guest@example.com', self::T + DAYSECS);
        $this->assertSame(self::T, (int) $DB->get_field('tool_flexaccess_invite', 'timecreated', ['id' => $id]));
        $token = invitation::issue_token($id);
        invitation::promote_pending_token($id);
        $accepted = invitation::accept($token);
        $this->assertNotNull($accepted);
        $row = $DB->get_record('tool_flexaccess_invite', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame((int) $row->timereserved, (int) $row->timeaccepted);
        $this->assertGreaterThan(self::T, (int) $row->timeaccepted);
    }

    /**
     * Campaign window: from is inclusive, until is inclusive (the last redeemable second), by the clock.
     *
     * @return array
     */
    public static function campaign_provider(): array {
        $t = self::T;
        return [
            'from: T-1' => [$t, 0, -1, false],
            'from: T' => [$t, 0, 0, true],
            'from: T+1' => [$t, 0, 1, true],
            'until: T-1' => [0, $t, -1, true],
            'until: T' => [0, $t, 0, true],
            'until: T+1' => [0, $t, 1, false],
        ];
    }

    /**
     * Redeemability and redemption follow the clock at both window boundaries.
     *
     * @dataProvider campaign_provider
     * @param int $from timeavailablefrom.
     * @param int $until timeavailableuntil.
     * @param int $offset Clock offset from T.
     * @param bool $open Expected redeemability.
     */
    public function test_campaign_window_follows_clock(int $from, int $until, int $offset, bool $open): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->mock_clock_with_frozen(self::T + $offset);
        $id = campaign::create([
            'name' => 'Clock',
            'courseid' => (int) $course->id,
            'enabled' => 1,
            'timeavailablefrom' => $from,
            'timeavailableuntil' => $until,
            'maxredemptions' => 0,
            'gatemode' => 'none',
        ])['id'];
        $this->assertSame(self::T + $offset, (int) $DB->get_field('tool_flexaccess_campaign', 'timecreated', ['id' => $id]));
        $this->assertSame($open, campaign::is_redeemable(campaign::get($id)));
        $this->assertSame($open, campaign::redeem($id));
        $this->assertSame($open ? 1 : 0, (int) campaign::get($id)->redemptioncount);
        // An explicit time still takes precedence over the clock.
        $this->assertFalse(campaign::is_redeemable(campaign::get($id), $from > 0 ? $from - 1 : $until + 1));
        $this->assertTrue(campaign::is_redeemable(campaign::get($id), self::T));
    }

    /**
     * Temporary batch accounts expire at their expiry time as measured by the clock.
     *
     * @dataProvider offset_provider
     * @param int $offset Clock offset from T.
     */
    public function test_batch_account_expiry_follows_clock(int $offset): void {
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Expiry', (int) $course->id, false, 1, 'clk', 8, self::T, self::T - DAYSECS);
        $userid = (int) \core_user::get_user_by_username(array_key_first($result['credentials']))->id;
        $this->mock_clock_with_frozen(self::T + $offset);
        $expected = $offset < 0 ? login_guard::OK : login_guard::REASON_EXPIRED;
        $this->assertSame($expected, login_guard::evaluate($userid, login_guard::CHANNEL_ENTRY));
    }

    /**
     * An asynchronous batch is provisioned over several chunks with one reference time, even with a clock
     * that advances on every read; a retry is idempotent and leaves existing members untouched.
     */
    public function test_batch_chunks_use_one_reference_time(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $course->id);
        \enrol_flexaccess\local\instance_config::save($enrolid, ['enrolperiod' => 7 * DAYSECS]);
        $this->mock_clock_with_incrementing(self::T);
        $count = batch::PROVISION_CHUNK + 2;
        $result = batch::create('Chunks', (int) $course->id, false, $count, 'chk', 8, self::T + 30 * DAYSECS);
        $this->assertSame(batch::STATUS_QUEUED, $result['status']);
        $this->runAdhocTasks(task\provision_batch::class);

        $members = $DB->get_fieldset_select('tool_flexaccess_batch_member', 'userid', 'batchid = ?', [$result['batchid']]);
        $this->assertCount($count, $members);
        [$in, $params] = $DB->get_in_or_equal($members);
        $created = $DB->get_fieldset_select('auth_flexaccess_account', 'DISTINCT timecreated', "userid $in", $params);
        $this->assertCount(1, $created, 'All accounts of one run share one reference time.');
        $ends = $DB->get_fieldset_sql(
            "SELECT DISTINCT ue.timeend FROM {user_enrolments} ue WHERE ue.enrolid = ? AND ue.userid $in",
            array_merge([$enrolid], $params)
        );
        $this->assertSame([(int) reset($created) + 7 * DAYSECS], array_map('intval', $ends));

        // Retry (e.g. the ad-hoc task runs again): nothing is added, nothing is re-stamped.
        $before = $DB->get_records_select('auth_flexaccess_account', "userid $in", $params, 'userid', 'userid, timecreated');
        $this->assertSame([], batch::provision_members((int) $result['batchid'], $count, false, 'chk', 8, self::T + 30 * DAYSECS));
        $after = $DB->get_records_select('auth_flexaccess_account', "userid $in", $params, 'userid', 'userid, timecreated');
        $this->assertEquals($before, $after);
    }

    /**
     * A FlexAccess member of a fresh course with an active enrolment ending at $timeend.
     *
     * @param int $timeend Enrolment end.
     * @return array [userid, courseid, ueid]
     */
    private function member(int $timeend): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $course->id);
        $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        $userid = (int) $this->getDataGenerator()->create_user(['auth' => 'flexaccess'])->id;
        account_service::create_authenticated($userid, account_service::generate_unique_reference(), self::T - DAYSECS);
        enrol_get_plugin('flexaccess')->enrol_user(
            $instance,
            $userid,
            \enrol_flexaccess\local\participant_role::get_id(),
            self::T - DAYSECS,
            $timeend
        );
        $ueid = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $enrolid, 'userid' => $userid]);
        return [$userid, (int) $course->id, $ueid];
    }

    /**
     * The recovery postcondition is judged at the clock's time: an enrolment that ends at T counts as
     * effective one Core rounding step before T and is reported as a blocker from T on - never as restored.
     *
     * @return array
     */
    public static function postcondition_provider(): array {
        return [
            'T-100' => [-100, true],
            'T' => [0, false],
            'T+1' => [1, false],
        ];
    }

    /**
     * Postcondition and recovery outcome follow the clock without reactivation (no false positive).
     *
     * @dataProvider postcondition_provider
     * @param int $offset Clock offset from T.
     * @param bool $met Expected postcondition.
     */
    public function test_recovery_postcondition_follows_clock(int $offset, bool $met): void {
        [$userid, $courseid] = $this->member(self::T);
        $this->mock_clock_with_frozen(self::T + $offset);
        $post = recovery::postcondition($userid, [$courseid]);
        $this->assertSame(self::T + $offset, $post->asof);
        $this->assertSame($met, $post->met);
        $result = recovery::recover($userid, $courseid);
        $this->assertSame($met ? recovery::EFFECTIVE_ACCESS_RESTORED : recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        if (!$met) {
            $this->assertSame('expired', $result->blockers[0]->code);
        }
    }

    /**
     * Precheck, repair and postcondition of one recovery share one reference time, even when every clock
     * read returns a later second; the reactivated end is computed from that time.
     */
    public function test_recovery_uses_one_reference_time(): void {
        global $DB;
        [$userid, $courseid, $ueid] = $this->member(self::T - 10);
        \enrol_flexaccess\local\instance_config::save(
            (int) $DB->get_field('user_enrolments', 'enrolid', ['id' => $ueid]),
            ['enrolperiod' => DAYSECS]
        );
        $this->mock_clock_with_incrementing(self::T);
        $result = recovery::recover($userid, $courseid, ['reactivate' => [$ueid]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $result->overall);
        $this->assertSame(self::T, $result->postcondition->asof);
        $this->assertSame(self::T + DAYSECS, (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $ueid]));
        // An explicit time still wins over the clock.
        $this->assertSame(self::T + 5, recovery::snapshot($userid, $courseid, self::T + 5)->asof);
    }

    /**
     * The stopwatch measures real elapsed time while the clock is frozen.
     */
    public function test_stopwatch_is_independent_of_frozen_clock(): void {
        $clock = $this->mock_clock_with_frozen(self::T);
        $watch = stopwatch::instance();
        $mark = $watch->start();
        usleep(20000);
        $this->assertGreaterThan(0.01, $watch->seconds_since($mark));
        $this->assertSame(self::T, $clock->time());
        $this->assertTrue($watch->within($mark, 0), '0 means no budget.');
        $this->assertTrue($watch->within($mark, 60));
    }

    /**
     * The reconciliation's time limit is a monotonic run-time budget: with a frozen clock it still stops
     * after the batch in which the budget ran out, persists its cursor, and the next run completes the scan.
     */
    public function test_reconciliation_budget_is_monotonic(): void {
        for ($i = 0; $i <= reconciliation::BATCH; $i++) {
            $userid = (int) $this->getDataGenerator()->create_user(['auth' => 'flexaccess'])->id;
            account_service::create_authenticated($userid, account_service::generate_unique_reference());
        }
        $this->mock_clock_with_frozen(self::T);
        \core\di::set(stopwatch::class, new class extends stopwatch {
            /** @var int Calls so far. */
            public int $calls = 0;

            /**
             * Elapsed seconds: 10 s more on every check.
             *
             * @param int $mark Start mark.
             * @return float
             */
            public function seconds_since(int $mark): float {
                // Every check reports 10 s more: a frozen clock must not stop the budget from running out.
                return 10.0 * ++$this->calls;
            }
        });
        $state = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK, 5);
        $this->assertSame('running', $state->status);
        $this->assertSame(reconciliation::BATCH, (int) $state->checked);

        $report = reconciliation::run(reconciliation::MODE_INSPECT, reconciliation::SOURCE_STATUS, 5);
        $this->assertFalse($report->complete, 'Inspect mode honours the same budget.');

        \core\di::set(stopwatch::class, new stopwatch());
        $state = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK, 0);
        $this->assertSame('done', $state->status);
        $this->assertSame(reconciliation::BATCH + 1, (int) $state->checked);
        $this->assertSame(self::T, (int) $state->finished);
    }
}
