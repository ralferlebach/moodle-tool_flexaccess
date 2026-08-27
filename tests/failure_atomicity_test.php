<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_flexaccess;

use tool_flexaccess\local\batch;
use tool_flexaccess\local\invitation;

/**
 * Failure atomicity: a half-finished operation must leave nothing behind that a retry cannot see.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
final class failure_atomicity_test extends \advanced_testcase {
    public function test_failed_member_leaves_no_orphaned_account(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $courseid = (int) $course->id;

        $before = $DB->count_records('user', ['auth' => 'flexaccess']);

        // Inject a failure after the account exists: enrolling into a course that does not exist
        // breaks exactly between account creation and the membership record.
        $result = batch::create('Atomic', $courseid, false, 1, 'kurs');
        $batchid = (int) $result['batchid'];
        $DB->set_field('tool_flexaccess_batch', 'courseid', -1, ['id' => $batchid]);

        try {
            batch::provision_members($batchid, 3, false, 'kurs');
            $this->fail('Provisioning should have failed for a missing course.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
        }

        // The decisive assertion: no account was left behind that the batch does not know about.
        $accounts = $DB->count_records('user', ['auth' => 'flexaccess', 'deleted' => 0]);
        $members = $DB->count_records('tool_flexaccess_batch_member', ['batchid' => $batchid]);
        $this->assertSame($before + $members, $accounts, 'An orphaned account was left behind.');
    }

    public function test_failed_resend_keeps_the_previously_delivered_link_valid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!method_exists('\auth_flexaccess\api', 'queue_deferred_mail')) {
            $this->markTestSkipped('auth_flexaccess predates the deferred queue.');
        }
        $course = $this->getDataGenerator()->create_course();
        $id = invitation::create((int) $course->id, 'person@example.com', 0, null, time());

        // First send, actually delivered: this token is the live one.
        invitation::send($id);
        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time());
        $messages = $sink->get_messages();
        $sink->close();
        preg_match('/token=([0-9a-f]{32})/', quoted_printable_decode((string) end($messages)->body), $m);
        $delivered = $m[1] ?? '';
        $this->assertNotEmpty($delivered);
        $this->assertNotNull(invitation::get_by_token($delivered));

        // A resend renders a new token but its mail never gets delivered.
        invitation::remind($id);
        \tool_flexaccess\local\invitation_mail_renderer::render_deferred_mail(
            ['invitationid' => $id, 'kind' => 'reminder'],
            time()
        );

        // The link the recipient already holds must still work: nothing replaced it yet.
        $this->assertNotNull(
            invitation::get_by_token($delivered),
            'A failed resend invalidated a link that was already delivered.'
        );
        $this->assertNotEmpty($DB->get_field('tool_flexaccess_invite', 'pendingtokenhash', ['id' => $id]));
    }

    public function test_delivered_resend_replaces_the_previous_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!method_exists('\auth_flexaccess\api', 'queue_deferred_mail')) {
            $this->markTestSkipped('auth_flexaccess predates the deferred queue.');
        }
        $course = $this->getDataGenerator()->create_course();
        $id = invitation::create((int) $course->id, 'person@example.com', 0, null, time());

        $first = $this->deliver($id, 'send');
        $second = $this->deliver($id, 'remind');

        $this->assertNotSame($first, $second);
        // Once the replacement really arrived, the older link stops working - as it should.
        $this->assertNull(invitation::get_by_token($first));
        $this->assertNotNull(invitation::get_by_token($second));
    }

    /**
     * Queue and deliver an invitation mail, returning the token it carried.
     *
     * @param int $id Invitation id.
     * @param string $action 'send' or 'remind'.
     * @return string
     */
    private function deliver(int $id, string $action): string {
        if ($action === 'send') {
            invitation::send($id);
        } else {
            invitation::remind($id);
        }
        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time());
        $messages = $sink->get_messages();
        $sink->close();
        preg_match('/token=([0-9a-f]{32})/', quoted_printable_decode((string) end($messages)->body), $m);
        return $m[1] ?? '';
    }

    public function test_member_insert_failure_after_enrolment_leaves_no_orphan(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Late', (int) $course->id, false, 1, 'kurs');
        $batchid = (int) $result['batchid'];

        $before = $DB->count_records('user', ['auth' => 'flexaccess', 'deleted' => 0]);

        // Break the LAST step only: enrolment succeeds, recording the membership does not. A too
        // long username violates the column and fails exactly at the insert.
        $overlong = str_repeat('x', 120);
        try {
            batch::provision_members($batchid, 1, false, $overlong);
            $this->fail('The member insert should have failed for an over-long username.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
        }

        $after = $DB->count_records('user', ['auth' => 'flexaccess', 'deleted' => 0]);
        $this->assertSame($before, $after, 'An account survived a failure at the membership insert.');
    }

    public function test_failed_acknowledge_does_not_resend_a_delivered_mail(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!method_exists('\auth_flexaccess\api', 'queue_deferred_mail')) {
            $this->markTestSkipped('auth_flexaccess predates the deferred queue.');
        }
        // Fixtures under tests/ are not autoloaded; Moodle only autoloads from classes/.
        require_once(__DIR__ . '/fixtures/failing_ack_renderer.php');

        // A renderer whose acknowledgement always fails: the mail goes out, the callback does not.
        \auth_flexaccess\api::queue_deferred_mail(
            null,
            'ack@example.com',
            'test',
            \tool_flexaccess\tests\fixtures\failing_ack_renderer::class,
            ['note' => 'x']
        );

        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time());
        $first = count($sink->get_messages());
        $sink->close();
        $this->assertSame(1, $first, 'The mail should have been delivered exactly once.');

        // The job must not be queued for another delivery - the mail cannot be un-sent.
        $this->assertSame(0, $DB->count_records('auth_flexaccess_mailqueue', ['status' => 'queued']));
        $this->assertSame(1, $DB->count_records('auth_flexaccess_mailqueue', ['status' => 'ackpending']));

        // A later run retries only the acknowledgement and sends nothing again.
        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time() + DAYSECS);
        $second = count($sink->get_messages());
        $sink->close();
        $this->assertSame(0, $second, 'A delivered mail was sent again because of a callback failure.');
    }
}
