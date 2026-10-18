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
use tool_flexaccess\local\recovery;

/**
 * Recovery reports success only when Core access is really restored (tool_flexaccess #10).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\recovery
 * @covers \tool_flexaccess\event\account_recovered
 */
final class recovery_postcondition_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass FlexAccess instance of the course. */
    private \stdClass $instance;

    /**
     * Course with an enabled FlexAccess instance; both halves of FlexAccess enabled.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        set_config('auth', 'flexaccess');
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $this->course->id);
        $this->instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
    }

    /**
     * A permanent FlexAccess account with a FlexAccess enrolment (with role) in the course.
     *
     * @param int $status Enrolment status.
     * @param int $timeend Enrolment end.
     * @return int User id.
     */
    private function member(int $status = ENROL_USER_SUSPENDED, int $timeend = 0): int {
        $userid = (int) $this->getDataGenerator()->create_user(['auth' => 'flexaccess'])->id;
        account_service::create_authenticated($userid, account_service::generate_unique_reference());
        enrol_get_plugin('flexaccess')->enrol_user(
            $this->instance,
            $userid,
            \enrol_flexaccess\local\participant_role::get_id(),
            0,
            $timeend,
            $status
        );
        return $userid;
    }

    /**
     * The user's FlexAccess enrolment id in the course.
     *
     * @param int $userid User id.
     * @return int
     */
    private function ueid(int $userid): int {
        global $DB;
        return (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $this->instance->id, 'userid' => $userid]);
    }

    /**
     * Mark the Moodle user as suspended by someone else (no FlexAccess provenance).
     *
     * @param int $userid User id.
     * @return void
     */
    private function foreign_lock(int $userid): void {
        global $DB;
        $DB->set_field('user', 'suspended', 1, ['id' => $userid]);
        $DB->set_field('auth_flexaccess_account', 'lockedby', 'admin', ['userid' => $userid]);
    }

    /**
     * Foreign lock + suspended enrolment: no false-positive recovery, the enrolment is not touched.
     */
    public function test_foreign_lock_with_suspended_enrolment(): void {
        global $DB;
        $userid = $this->member();
        $this->foreign_lock($userid);
        $ueid = $this->ueid($userid);

        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$ueid]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        $this->assertContains(recovery::ACCOUNT_BLOCKED_FOREIGN_LOCK, $result->outcomes);
        $this->assertContains(recovery::ENROLMENT_SKIPPED_ACCOUNT_BLOCKED, $result->outcomes);
        $this->assertNotContains(recovery::ENROLMENT_REACTIVATED, $result->outcomes);
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $DB->get_field('user_enrolments', 'status', ['id' => $ueid]));
        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $userid]));
        $this->assertSame('account', $result->blockers[0]->scope);
        $this->assertSame('suspended', $result->blockers[0]->code);
    }

    /**
     * Foreign lock + selected reactivation of an active enrolment: overall "access not restored".
     */
    public function test_foreign_lock_with_selected_reactivation_is_not_restored(): void {
        $userid = $this->member(ENROL_USER_ACTIVE, time() - 10);
        $this->foreign_lock($userid);
        foreach ([(int) $this->course->id, null] as $scope) {
            $result = recovery::recover($userid, $scope, ['reactivate' => [$this->ueid($userid)]]);
            $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
            $this->assertFalse($result->postcondition->met);
        }
    }

    /**
     * A disabled instance yields a clear blocker, never "reactivated access", and stays disabled.
     */
    public function test_disabled_instance_is_a_blocker(): void {
        global $DB;
        $userid = $this->member();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $this->instance->id]);
        $snapshot = recovery::snapshot($userid, (int) $this->course->id);
        $this->assertSame('instance_disabled', $snapshot->enrolments[0]->effective);
        $this->assertFalse($snapshot->enrolments[0]->instanceenabled);
        $this->assertSame('instancedisabled', recovery::access_status($snapshot, $snapshot->enrolments[0]));
        $this->assertSame('instance_disabled', recovery::predict_enrolment($snapshot, $snapshot->enrolments[0], true));

        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        $this->assertContains(recovery::ENROLMENT_BLOCKED_INSTANCE_DISABLED, $result->outcomes);
        $this->assertNotContains(recovery::ENROLMENT_REACTIVATED, $result->outcomes);
        $this->assertSame(ENROL_INSTANCE_DISABLED, (int) $DB->get_field('enrol', 'status', ['id' => $this->instance->id]));
        $blocker = $result->blockers[0];
        $this->assertSame(
            ['course', (int) $this->course->id, 'instance_disabled'],
            [$blocker->scope, $blocker->courseid, $blocker->code]
        );
        $this->assertStringContainsString('/enrol/instances.php', recovery::blocker_url($blocker, $userid)->out(false));
    }

    /**
     * A missing participant role never counts as "ok"; a selected reactivation repairs it.
     */
    public function test_missing_participant_role(): void {
        $userid = $this->member(ENROL_USER_ACTIVE);
        $context = \context_course::instance((int) $this->course->id);
        role_unassign(\enrol_flexaccess\local\participant_role::get_id(), $userid, $context->id);

        $result = recovery::recover($userid, (int) $this->course->id);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        $this->assertSame('participant_role_missing', $result->postcondition->courses[(int) $this->course->id]);

        $snapshot = recovery::snapshot($userid, (int) $this->course->id);
        $this->assertTrue($snapshot->enrolments[0]->reactivatable);
        $this->assertSame('rolemissing', recovery::access_status($snapshot, $snapshot->enrolments[0]));
        $repaired = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertContains(recovery::ENROLMENT_ROLE_REPAIRED, $repaired->outcomes);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $repaired->overall);
    }

    /**
     * Without the participant role definition a reactivation is refused and nothing is "ok".
     */
    public function test_missing_role_definition_is_not_ok(): void {
        $userid = $this->member();
        delete_role(\enrol_flexaccess\local\participant_role::get_id());
        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        $this->assertContains(recovery::ENROLMENT_BLOCKED_MISSING_ROLE, $result->outcomes);
    }

    /**
     * An expired end time is renewed by the reactivation, and the effective state is "ok".
     */
    public function test_expired_enrolment_is_effective_after_reactivation(): void {
        global $DB;
        $userid = $this->member(ENROL_USER_ACTIVE, time() - HOURSECS);
        $snapshot = recovery::snapshot($userid, (int) $this->course->id);
        $this->assertSame('expired', $snapshot->enrolments[0]->effective);
        $this->assertSame('ok', recovery::predict_enrolment($snapshot, $snapshot->enrolments[0], true));
        $this->assertSame('expired', recovery::predict_enrolment($snapshot, $snapshot->enrolments[0], false));

        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertContains(recovery::ENROLMENT_REACTIVATED, $result->outcomes);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $result->overall);
        $this->assertSame('ok', $result->postcondition->courses[(int) $this->course->id]);
        $timeend = (int) $DB->get_field('user_enrolments', 'timeend', ['id' => $this->ueid($userid)]);
        $this->assertTrue($timeend === 0 || $timeend > time());
    }

    /**
     * An enrolment that starts in the future is reported as such, not as restored access.
     */
    public function test_not_started_enrolment_is_distinguished(): void {
        global $DB;
        $userid = $this->member();
        $DB->set_field('user_enrolments', 'timestart', time() + DAYSECS, ['id' => $this->ueid($userid)]);
        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        $this->assertContains(recovery::ENROLMENT_NOT_STARTED, $result->outcomes);
        $this->assertSame('not_started', $result->postcondition->courses[(int) $this->course->id]);
    }

    /**
     * Several FlexAccess instances in the course lead to a review status, never to "restored".
     */
    public function test_multiple_instances_need_review(): void {
        global $DB;
        $userid = $this->member(ENROL_USER_ACTIVE);
        $second = clone $this->instance;
        unset($second->id);
        $DB->insert_record('enrol', $second);
        $result = recovery::recover($userid, (int) $this->course->id);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $result->overall);
        $this->assertSame('multiple_instances', $result->blockers[0]->code);
        $this->assertSame(1, \enrol_flexaccess\api::count_multiple_instances());
        $items = array_filter(
            local\health::check_invariants(time()),
            static fn(\stdClass $item): bool => $item->key === 'invariant_multipleinstances'
        );
        $this->assertCount(1, $items);
    }

    /**
     * The full happy path: suspended enrolment of a usable account becomes effective in Core.
     */
    public function test_happy_path_restores_effective_access(): void {
        $userid = $this->member();
        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $result->overall);
        $this->assertSame([], $result->blockers);
        $this->assertTrue(is_enrolled(\context_course::instance((int) $this->course->id), $userid, '', true));
    }

    /**
     * Course and system scope judge the same user with the same effective-access evaluation.
     */
    public function test_course_and_system_scope_agree(): void {
        global $DB;
        $userid = $this->member();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $this->instance->id]);
        $course = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $system = recovery::recover($userid, null, ['reactivate' => [$this->ueid($userid)]]);
        $this->assertSame($course->overall, $system->overall);
        $this->assertSame($course->postcondition->courses, $system->postcondition->courses);
    }

    /**
     * A batch gives every user exactly one unambiguous overall result.
     */
    public function test_batch_mixed_users_have_one_result_each(): void {
        global $DB;
        $ok = $this->member();
        $locked = $this->member();
        $this->foreign_lock($locked);
        $second = $this->member();
        $other = $this->getDataGenerator()->create_course();
        $otherid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $other->id);
        $results = recovery::recover_batch(
            [$ok, $locked, $second],
            null,
            ['reactivate' => [
                $ok => [$this->ueid($ok)],
                $locked => [$this->ueid($locked)],
                $second => [$this->ueid($second)],
            ]]
        );
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $results[$ok]->overall);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $results[$locked]->overall);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $results[$second]->overall);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $otherid]);
        $late = (int) $this->getDataGenerator()->create_user(['auth' => 'flexaccess'])->id;
        account_service::create_authenticated($late, account_service::generate_unique_reference());
        enrol_get_plugin('flexaccess')->enrol_user(
            $DB->get_record('enrol', ['id' => $otherid]),
            $late,
            \enrol_flexaccess\local\participant_role::get_id(),
            0,
            0,
            ENROL_USER_SUSPENDED
        );
        $lateue = (int) $DB->get_field('user_enrolments', 'id', ['enrolid' => $otherid, 'userid' => $late]);
        $mixed = recovery::recover_batch([$ok, $late], null, ['reactivate' => [$late => [$lateue]]]);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $mixed[$ok]->overall);
        $this->assertSame(recovery::EFFECTIVE_ACCESS_NOT_RESTORED, $mixed[$late]->overall);
        foreach ($mixed as $result) {
            $this->assertContains($result->overall, [recovery::EFFECTIVE_ACCESS_RESTORED, recovery::EFFECTIVE_ACCESS_NOT_RESTORED]);
        }
    }

    /**
     * The event records the technical actions and the final postcondition.
     */
    public function test_event_records_actions_and_postcondition(): void {
        $userid = $this->member();
        $sink = $this->redirectEvents();
        recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$this->ueid($userid)]]);
        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($e): bool => $e instanceof event\account_recovered
        ));
        $sink->close();
        $this->assertCount(1, $events);
        $other = $events[0]->other;
        $this->assertSame(recovery::EFFECTIVE_ACCESS_RESTORED, $other['overall']);
        $this->assertContains(recovery::ENROLMENT_REACTIVATED, $other['outcomes']);
        $this->assertSame('reactivated', $other['enrolments'][0]['outcome']);
        $this->assertSame('', $other['postcondition']['account']);
        $this->assertSame(['ok'], array_values($other['postcondition']['courses']));
    }
}
