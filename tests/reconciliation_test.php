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

use auth_flexaccess\local\account_service;
use auth_flexaccess\local\account_state;
use auth_flexaccess\local\account_type;
use enrol_flexaccess\local\participant_role;
use tool_flexaccess\local\reconciliation;

/**
 * Tests for the retroactive account reconciliation (issue tool#7).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\reconciliation
 * @covers \tool_flexaccess\task\reconcile_accounts
 */
final class reconciliation_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private $course;
    /** @var \stdClass FlexAccess instance. */
    private $instance;

    /**
     * Course with an enabled FlexAccess instance.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        participant_role::ensure();
        $this->course = $this->getDataGenerator()->create_course();
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $this->course->id);
        $this->instance = $DB->get_record('enrol', ['id' => $enrolid]);
    }

    /**
     * Create a legacy account state directly in the stores (as an older release could have left it).
     *
     * @param string $type Account type.
     * @param string $state Account state.
     * @param int $suspended user.suspended.
     * @param bool $restricted Restriction role held.
     * @param string|null $lockedby Recorded lock origin.
     * @param int|null $expires Account expiry.
     * @param int|null $sourcecourseid Origin course.
     * @return int User id.
     */
    private function legacy(
        string $type,
        string $state,
        int $suspended,
        bool $restricted,
        ?string $lockedby = null,
        ?int $expires = null,
        ?int $sourcecourseid = null
    ): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['auth' => 'flexaccess', 'suspended' => $suspended]);
        $DB->insert_record('auth_flexaccess_account', (object) [
            'userid' => $user->id,
            'accounttype' => $type,
            'accountstate' => $state,
            'referencecode' => account_service::generate_unique_reference(),
            'sourcecourseid' => $sourcecourseid,
            'timecreated' => time(),
            'timeexpires' => $expires,
            'timemodified' => time(),
            'lockedby' => $lockedby,
        ]);
        if ($restricted) {
            participant_role::restrict((int) $user->id);
        }
        return (int) $user->id;
    }

    /**
     * Enrol a user through the FlexAccess instance with the participant role.
     *
     * @param int $userid User id.
     * @param int $status Enrolment status.
     * @param int $timeend End time.
     * @param \stdClass|null $instance Instance (default: the course's).
     * @return void
     */
    private function enrol(int $userid, int $status = ENROL_USER_ACTIVE, int $timeend = 0, ?\stdClass $instance = null): void {
        enrol_get_plugin('flexaccess')->enrol_user(
            $instance ?? $this->instance,
            $userid,
            participant_role::get_id(),
            time(),
            $timeend,
            $status
        );
    }

    /**
     * Domain data that a reconciliation may change, for before/after comparisons.
     *
     * @return array
     */
    private function domain(): array {
        global $DB;
        return [
            'user' => $DB->get_records_menu('user', null, 'id', 'id, suspended'),
            'account' => $DB->get_records('auth_flexaccess_account', null, 'userid'),
            'roles' => $DB->get_records('role_assignments', null, 'id', 'id, roleid, userid, contextid, component'),
            'enrolments' => $DB->get_records('user_enrolments', null, 'id', 'id, userid, status, timeend'),
            'cases' => $DB->get_records('tool_flexaccess_reconcile', null, 'id'),
            'log' => $DB->count_records('tool_flexaccess_reconcile_log'),
        ];
    }

    /**
     * Whether the user holds the restriction role (any component).
     *
     * @param int $userid User id.
     * @return bool
     */
    private function restricted(int $userid): bool {
        return user_has_role_assignment($userid, participant_role::get_restriction_id(), \context_system::instance()->id);
    }

    /**
     * Legacy: ACTIVE + suspended, suspension set by FlexAccess -> unsuspended.
     *
     * @return void
     */
    public function test_active_with_flexaccess_lock_is_unsuspended(): void {
        global $DB;
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 1, false, 'flexaccess');
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([reconciliation::RULE_UNSUSPEND], $result->repairs);
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $userid]));
    }

    /**
     * Legacy: ACTIVE + suspended of unknown origin -> unchanged and a review case.
     *
     * @return void
     */
    public function test_unattributed_suspension_is_left_and_reviewed(): void {
        global $DB;
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 1, false, null);
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([], $result->repairs);
        $this->assertContains(reconciliation::REVIEW_ADMIN_SUSPENSION, $result->reviews);
        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $userid]));
        $this->assertSame([reconciliation::REVIEW_ADMIN_SUSPENSION], reconciliation::user_cases($userid));
    }

    /**
     * Legacy: ACTIVE + restriction role (also a component-less historical assignment) -> removed.
     *
     * @return void
     */
    public function test_active_restriction_is_removed_completely(): void {
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, true);
        // A second, historical assignment without component (older release / manual).
        role_assign(participant_role::get_restriction_id(), $userid, \context_system::instance()->id);
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([reconciliation::RULE_UNRESTRICT], $result->repairs);
        $this->assertFalse($this->restricted($userid));
    }

    /**
     * Legacy: live temporary account without restriction -> restriction added.
     *
     * @return void
     */
    public function test_live_temporary_gets_restriction(): void {
        $userid = $this->legacy(account_type::TEMPORARY_USER, account_state::EPHEMERAL, 0, false, null, time() + 3600);
        $this->enrol($userid);
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([reconciliation::RULE_RESTRICT], $result->repairs);
        $this->assertTrue($this->restricted($userid));
    }

    /**
     * Legacy: EXPIRED + suspended -> never reactivated (even with a FlexAccess lock).
     *
     * @return void
     */
    public function test_expired_is_not_reactivated(): void {
        global $DB;
        $userid = $this->legacy(account_type::TEMPORARY_USER, account_state::EXPIRED, 1, true, 'flexaccess', time() - 10);
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([], $result->repairs);
        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $userid]));
        $this->assertSame(account_state::EXPIRED, \auth_flexaccess\api::get_account($userid)->accountstate);
    }

    /**
     * Legacy: SUSPENDED state -> never reactivated.
     *
     * @return void
     */
    public function test_suspended_state_is_not_reactivated(): void {
        global $DB;
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::SUSPENDED, 1, false, null);
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([], $result->repairs);
        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $userid]));
        $this->assertSame(account_state::SUSPENDED, \auth_flexaccess\api::get_account($userid)->accountstate);
    }

    /**
     * Legacy: enrolment already removed -> no automatic re-enrolment, a review case.
     *
     * @return void
     */
    public function test_removed_enrolment_is_not_recreated(): void {
        global $DB;
        $userid = $this->legacy(
            account_type::TEMPORARY_USER,
            account_state::EPHEMERAL,
            0,
            true,
            null,
            time() + 3600,
            (int) $this->course->id
        );
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([], $result->repairs);
        $this->assertContains(reconciliation::REVIEW_ENROLMENT_REMOVED, $result->reviews);
        $this->assertFalse($DB->record_exists('user_enrolments', ['userid' => $userid]));
    }

    /**
     * Legacy: enrolment only suspended -> not changed (a course decision, not an inconsistency).
     *
     * @return void
     */
    public function test_suspended_enrolment_is_not_changed(): void {
        global $DB;
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, false);
        $this->enrol($userid, ENROL_USER_SUSPENDED);
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([], $result->repairs);
        $this->assertSame([], $result->reviews);
        $this->assertEquals(ENROL_USER_SUSPENDED, $DB->get_field('user_enrolments', 'status', ['userid' => $userid]));
    }

    /**
     * Legacy: competing enrolments in one course -> unchanged and a review case.
     *
     * @return void
     */
    public function test_competing_enrolments_are_reviewed(): void {
        global $DB;
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, false);
        $second = $DB->get_record('enrol', ['id' => enrol_get_plugin('flexaccess')->add_instance($this->course)]);
        $this->enrol($userid, ENROL_USER_ACTIVE, time() + 100);
        $this->enrol($userid, ENROL_USER_ACTIVE, time() + 99999, $second);
        $before = $DB->get_records('user_enrolments', ['userid' => $userid], 'id', 'id, status, timeend');
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertContains(reconciliation::REVIEW_COMPETING_ENROLMENTS, $result->reviews);
        $this->assertEquals($before, $DB->get_records('user_enrolments', ['userid' => $userid], 'id', 'id, status, timeend'));
    }

    /**
     * Legacy: already consistent user -> no change at all.
     *
     * @return void
     */
    public function test_consistent_user_is_untouched(): void {
        $userid = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, false);
        $this->enrol($userid);
        $before = $this->domain();
        $result = reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, 'test');
        $this->assertSame([], $result->repairs);
        $this->assertSame([], $result->reviews);
        $this->assertEquals($before, $this->domain());
    }

    /**
     * Full run: all accounts checked, safe repairs done, a second run changes nothing (idempotent).
     *
     * @return void
     */
    public function test_full_run_is_idempotent(): void {
        global $DB;
        $fix = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, true);
        $review = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 1, false, null);
        $overdue = $this->legacy(account_type::TEMPORARY_USER, account_state::EPHEMERAL, 0, true, null, time() - 5);
        // Historical system-level participant assignment.
        role_assign(participant_role::get_id(), $fix, \context_system::instance()->id);

        $state = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_UPGRADE);
        $this->assertSame('done', $state->status);
        $this->assertSame(3, $state->checked);
        $this->assertGreaterThan(0, $state->lastfullscan);
        $this->assertFalse($this->restricted($fix));
        $this->assertSame(account_state::EXPIRED, \auth_flexaccess\api::get_account($overdue)->accountstate);
        $this->assertSame(0, \enrol_flexaccess\api::find_role_mismatches(1)['systemparticipant']);
        $this->assertSame([reconciliation::REVIEW_ADMIN_SUSPENSION => 1], reconciliation::open_case_counts());
        // Nothing automatically repairable is left.
        $this->assertSame(0, local\health::auto_repairable_count(time()));
        // Every change has an audit row with source and rule.
        $this->assertTrue($DB->record_exists('tool_flexaccess_reconcile_log', [
            'userid' => $fix, 'rule' => reconciliation::RULE_UNRESTRICT, 'source' => 'upgrade',
        ]));

        $before = $this->domain();
        $again = reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_STATUS);
        $this->assertSame(0, $again->repairedaccounts);
        $this->assertEquals($before, $this->domain());
        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $review]));
    }

    /**
     * Inspect only changes nothing, but reports exactly what the safe repair would do.
     *
     * @return void
     */
    public function test_inspect_changes_nothing(): void {
        $fix = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, true);
        $before = $this->domain();
        $report = reconciliation::run(reconciliation::MODE_INSPECT, reconciliation::SOURCE_STATUS);
        $this->assertTrue($report->complete);
        $this->assertSame([$fix], $report->repairs[reconciliation::RULE_UNRESTRICT]);
        $this->assertEquals($before, $this->domain());
    }

    /**
     * A run interrupted mid-way resumes from its cursor; the task finishes it.
     *
     * @return void
     */
    public function test_resume_after_interruption(): void {
        $first = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, true);
        $second = $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 0, true);
        // Simulate a run that stopped after the first account.
        set_config('reconcile_state', json_encode((object) [
            'status' => 'running', 'source' => 'upgrade', 'cursor' => $first, 'started' => time(), 'finished' => 0,
            'lastfullscan' => 0, 'checked' => 1, 'repairedaccounts' => 0, 'rolefixes' => 0, 'corefixes' => 0, 'total' => 2,
        ]), 'tool_flexaccess');
        $this->expectOutputRegex('/2 accounts checked, status done/');
        (new task\reconcile_accounts())->execute();
        $state = reconciliation::state();
        $this->assertSame('done', $state->status);
        $this->assertSame(2, $state->checked);
        // Only the account after the cursor was processed by the continuation.
        $this->assertTrue($this->restricted($first));
        $this->assertFalse($this->restricted($second));
    }

    /**
     * The status shows the figures and open cases; the safe repair and the upgrade share the service.
     *
     * @return void
     */
    public function test_status_section(): void {
        $this->legacy(account_type::AUTHENTICATED_USER, account_state::ACTIVE, 1, false, null);
        $items = local\health::check_reconciliation(time());
        $this->assertSame('reconcile_never', $items[0]->key);
        local\health::repair(local\health::REPAIR_RECONCILE);
        $keys = array_map(static fn($i) => $i->key, local\health::check_reconciliation(time()));
        $this->assertContains('reconcile_lastscan', $keys);
        $this->assertContains('reconcile_case_' . reconciliation::REVIEW_ADMIN_SUSPENSION, $keys);
    }
}
