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
use tool_flexaccess\local\recovery;

/**
 * Tests for the recovery of frozen FlexAccess accounts (issue tool#3).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\recovery
 * @covers \tool_flexaccess\event\account_recovered
 */
final class recovery_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private $course;
    /** @var \stdClass FlexAccess instance. */
    private $instance;
    /** @var \stdClass Editing teacher of the course. */
    private $teacher;

    /**
     * Course with an enabled FlexAccess instance and a teacher.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        if (!class_exists('\auth_flexaccess\api') || !method_exists('\auth_flexaccess\api', 'recover_account')) {
            $this->markTestSkipped('Requires auth_flexaccess and enrol_flexaccess 1.1.0+.');
        }
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $this->course = $this->getDataGenerator()->create_course();
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $this->course->id);
        $this->instance = $DB->get_record('enrol', ['id' => $enrolid]);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * A temporary visitor of the course created through the real grant path.
     *
     * @param int $expires Account expiry.
     * @return int User id.
     */
    private function visitor(int $expires): int {
        return (int) \enrol_flexaccess\local\enrol_service::reserve_and_enrol(
            (int) $this->instance->id,
            fn(): int => \auth_flexaccess\api::create_temporary_user($expires, (int) $this->course->id),
            time()
        )->userid;
    }

    /**
     * The user's FlexAccess enrolment in the course.
     *
     * @param int $userid User id.
     * @return \stdClass|false
     */
    private function ue(int $userid) {
        global $DB;
        return $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id, 'userid' => $userid]);
    }

    /**
     * Case 1: PROVISIONAL -> EXPIRED -> recovery -> PROVISIONAL -> verified -> ACTIVE.
     *
     * @return void
     */
    public function test_provisional_expired_recovered_then_verified(): void {
        global $DB;
        $this->setAdminUser();
        $userid = $this->visitor(time() + 3600);
        $this->assertSame('verificationsent', \auth_flexaccess\api::request_persistence(
            $userid,
            'late.verifier@example.com',
            'Late',
            'Verifier',
            'Str0ng-Pass!23'
        ));
        $DB->set_field('auth_flexaccess_account', 'timeexpires', time() - 1, ['userid' => $userid]);
        account_service::expire_due();
        $this->assertSame(account_state::EXPIRED, \auth_flexaccess\api::get_account($userid)->accountstate);
        // The regular path cannot free it any more.
        $this->assertFalse(account_service::is_convertible($userid));

        $result = recovery::recover($userid, null);
        $this->assertContains(recovery::RECOVERED, $result->outcomes);
        $this->assertSame('verificationresent', $result->action);
        $account = \auth_flexaccess\api::get_account($userid);
        // Never straight to ACTIVE: verification is not bypassed.
        $this->assertSame(account_state::PROVISIONAL, $account->accountstate);
        $this->assertGreaterThan(time(), (int) $account->timeexpires);
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $userid]));

        // The fresh verification link completes the account.
        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time());
        $token = null;
        foreach ($sink->get_messages() as $message) {
            if (preg_match('/token=([A-Za-z0-9]+)/', quoted_printable_decode($message->body), $m)) {
                $token = $m[1];
            }
        }
        $sink->close();
        $this->assertNotNull($token);
        $this->assertSame('converted', \auth_flexaccess\api::confirm_persistence($token));
        $this->assertSame(account_state::ACTIVE, \auth_flexaccess\api::get_account($userid)->accountstate);
    }

    /**
     * Case 2: EXPIRED account with an active enrolment - account recovered, enrolment untouched.
     *
     * @return void
     */
    public function test_expired_with_active_enrolment(): void {
        $this->setAdminUser();
        $userid = $this->visitor(time() - 1);
        account_service::expire_due();
        $before = $this->ue($userid);
        $snapshot = recovery::snapshot($userid, (int) $this->course->id);
        $this->assertSame('accountexpired', recovery::access_status($snapshot, $snapshot->enrolments[0]));
        $result = recovery::recover($userid, (int) $this->course->id);
        $this->assertSame([recovery::RECOVERED], $result->outcomes);
        $this->assertEquals($before->status, $this->ue($userid)->status);
        $this->assertEquals($before->timeend, $this->ue($userid)->timeend);
    }

    /**
     * Case 3: EXPIRED account with a suspended enrolment - reactivated only when explicitly selected.
     *
     * @return void
     */
    public function test_expired_with_suspended_enrolment(): void {
        $this->setAdminUser();
        $userid = $this->visitor(time() - 1);
        account_service::expire_due();
        enrol_get_plugin('flexaccess')->update_user_enrol($this->instance, $userid, ENROL_USER_SUSPENDED);
        $snapshot = recovery::snapshot($userid, (int) $this->course->id);
        $this->assertSame('accountexpired', recovery::access_status($snapshot, $snapshot->enrolments[0]));
        $this->assertTrue($snapshot->enrolments[0]->reactivatable);

        // Not selected: the suspended enrolment stays suspended.
        recovery::recover($userid, (int) $this->course->id);
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($userid)->status);

        $ueid = (int) $this->ue($userid)->id;
        $result = recovery::recover($userid, (int) $this->course->id, ['reactivate' => [$ueid]]);
        $this->assertContains(recovery::ENROLMENT_REACTIVATED, $result->outcomes);
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($userid)->status);
    }

    /**
     * Case 4: an already unenrolled user is not silently re-enrolled; re-enrolment is explicit.
     *
     * @return void
     */
    public function test_unenrolled_user_requires_explicit_reenrol(): void {
        $this->setAdminUser();
        $userid = $this->visitor(time() - 1);
        account_service::expire_due();
        enrol_get_plugin('flexaccess')->unenrol_user($this->instance, $userid);

        $result = recovery::recover($userid, (int) $this->course->id);
        $this->assertContains(recovery::REENROL_REQUIRED, $result->outcomes);
        $this->assertFalse($this->ue($userid));

        $result = recovery::recover($userid, (int) $this->course->id, ['reenrol' => [(int) $this->course->id]]);
        $this->assertContains(recovery::REENROLLED, $result->outcomes);
        $this->assertNotFalse($this->ue($userid));
    }

    /**
     * Case 5: course batch with mixed states gives one unambiguous result per user; audit logged.
     *
     * @return void
     */
    public function test_course_batch_mixed(): void {
        $expired = $this->visitor(time() - 1);
        account_service::expire_due();
        $live = $this->visitor(time() + 3600);
        $foreign = (int) $this->getDataGenerator()->create_user()->id; // Not a FlexAccess user.
        $this->setUser($this->teacher);
        $sink = $this->redirectEvents();
        $results = recovery::recover_batch([$expired, $live, $foreign], (int) $this->course->id, ['reactivateall' => true]);
        $events = array_filter($sink->get_events(), static fn($e) => $e instanceof event\account_recovered);
        $sink->close();
        $this->assertContains(recovery::RECOVERED, $results[$expired]->outcomes);
        $this->assertSame([recovery::SKIPPED], $results[$live]->outcomes);
        $this->assertSame([recovery::NOT_ELIGIBLE], $results[$foreign]->outcomes);
        $this->assertCount(2, $events);
        $event = reset($events);
        $this->assertSame('course', $event->other['scope']);
        $this->assertSame((int) $this->teacher->id, (int) $event->userid);
        $this->assertArrayHasKey('oldstate', $event->other);
        $this->assertArrayHasKey('newstate', $event->other);
        $this->assertArrayHasKey('enrolments', $event->other);
    }

    /**
     * Case 6: a system batch with a partial failure rolls back only the failing user.
     *
     * @return void
     */
    public function test_system_batch_partial_failure(): void {
        global $DB;
        $this->setAdminUser();
        $ok = $this->visitor(time() - 1);
        $broken = $this->visitor(time() - 1);
        account_service::expire_due();
        // Corrupt one user so its recovery throws inside the transaction.
        $DB->set_field('auth_flexaccess_account', 'accountstate', account_state::EXPIRED, ['userid' => $broken]);
        $DB->delete_records('user', ['id' => $broken]);
        $results = recovery::recover_batch([$ok, $broken], null);
        $this->assertContains(recovery::RECOVERED, $results[$ok]->outcomes);
        $this->assertNotContains(recovery::RECOVERED, $results[$broken]->outcomes);
        $this->assertSame(account_state::EPHEMERAL, \auth_flexaccess\api::get_account($ok)->accountstate);
        $this->assertSame(account_state::EXPIRED, \auth_flexaccess\api::get_account($broken)->accountstate);
    }

    /**
     * Case 7: missing capability and course scope are enforced.
     *
     * @return void
     */
    public function test_capabilities_and_scope(): void {
        $userid = $this->visitor(time() - 1);
        account_service::expire_due();
        // A student may not recover.
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $this->assertSame([recovery::NOT_ELIGIBLE], recovery::recover($userid, (int) $this->course->id)->outcomes);
        // A teacher may in their course, but not system-wide and not in another course.
        $this->setUser($this->teacher);
        $this->assertSame([recovery::NOT_ELIGIBLE], recovery::recover($userid, null)->outcomes);
        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user((int) $this->teacher->id, (int) $other->id, 'editingteacher');
        $this->assertSame([recovery::NOT_ELIGIBLE], recovery::recover($userid, (int) $other->id)->outcomes);
        // Re-enrolment needs its own capability (not held by editing teachers by default).
        enrol_get_plugin('flexaccess')->unenrol_user($this->instance, $userid);
        $result = recovery::recover($userid, (int) $this->course->id, ['reenrol' => [(int) $this->course->id]]);
        $this->assertContains(recovery::REENROL_REQUIRED, $result->outcomes);
        $this->assertFalse($this->ue($userid));
        $this->assertSame(account_state::EPHEMERAL, \auth_flexaccess\api::get_account($userid)->accountstate);
    }

    /**
     * After any recovery, user.suspended and the FlexAccess state do not contradict each other.
     *
     * @return void
     */
    public function test_no_contradiction_after_recovery(): void {
        global $DB;
        $this->setAdminUser();
        $active = (int) $this->getDataGenerator()->create_user(['auth' => 'flexaccess'])->id;
        account_service::create_authenticated($active, account_service::generate_unique_reference());
        $DB->set_field('user', 'suspended', 1, ['id' => $active]);
        $this->assertSame('normalised', recovery::predict_account_action(recovery::snapshot($active)));
        recovery::recover($active, null);
        $this->assertSame([], \auth_flexaccess\api::find_state_mismatches(null, 10, [$active]));
    }
}
