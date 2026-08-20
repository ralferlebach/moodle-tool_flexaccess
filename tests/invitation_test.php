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

use tool_flexaccess\local\invitation;

/**
 * Tests for the person-bound FlexAccess invitation service.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flexaccess\local\invitation
 */
final class invitation_test extends \advanced_testcase {
    /**
     * Create an invitation for a fresh course.
     *
     * @param string $email Recipient.
     * @param int $expiry Expiry time (0 = never).
     * @param int|null $now Current time.
     * @return int Invitation id.
     */
    private function make(string $email = 'guest@example.com', int $expiry = 0, ?int $now = null): int {
        $course = $this->getDataGenerator()->create_course();
        return invitation::create((int) $course->id, $email, $expiry, null, $now);
    }

    /**
     * Create, look up by token, and acceptability of a fresh invitation.
     *
     * @return void
     */
    public function test_create_and_acceptable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = $this->make('Person@Example.com');
        $invite = invitation::get($id);
        $this->assertNotNull($invite);
        $this->assertSame('person@example.com', $invite->email);
        $this->assertSame(invitation::STATUS_PENDING, $invite->status);
        $this->assertSame($invite->id, invitation::get_by_token($invite->token)->id);
        $this->assertTrue(invitation::is_acceptable($invite));
    }

    /**
     * Expiry and revocation both make an invitation unacceptable.
     *
     * @return void
     */
    public function test_expiry_and_revoke(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = 5000000;
        $expired = invitation::get($this->make('a@example.com', $now - 10, $now));
        $this->assertFalse(invitation::is_acceptable($expired, $now));

        $id = $this->make('b@example.com');
        invitation::revoke($id);
        $this->assertFalse(invitation::is_acceptable(invitation::get($id)));
    }

    /**
     * send queues mail and stamps timesent; remind requires a prior send.
     *
     * @return void
     */
    public function test_send_and_remind(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEmails();
        $id = $this->make('c@example.com');

        // A reminder before any send is refused.
        $this->assertFalse(invitation::remind($id));

        $this->assertTrue(invitation::send($id));
        $this->assertGreaterThan(0, (int) invitation::get($id)->timesent);
        // One job queued for the recipient.
        $this->assertSame(1, $DB->count_records('auth_flexaccess_mailqueue', ['recipient' => 'c@example.com']));

        $this->assertTrue(invitation::remind($id));
        $this->assertSame(1, (int) invitation::get($id)->remindercount);
        $this->assertSame(2, $DB->count_records('auth_flexaccess_mailqueue', ['recipient' => 'c@example.com']));
        $sink->close();
    }

    /**
     * accept is single-use: the second acceptance of the same token fails.
     *
     * @return void
     */
    public function test_accept_is_single_use(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $invite = invitation::get($this->make('d@example.com'));

        $first = invitation::accept($invite->token);
        $this->assertNotNull($first);
        $this->assertSame(invitation::STATUS_ACCEPTED, $first->status);
        // Second attempt is refused; a revoked/accepted invite is not acceptable.
        $this->assertNull(invitation::accept($invite->token));
    }

    /**
     * due_reminders returns only pending, sent, unreminded, unexpired invitations past the cutoff.
     *
     * @return void
     */
    public function test_due_reminders(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = 10000000;
        $cutoff = $now - 3 * DAYSECS;

        // Sent long ago, pending, not reminded → due.
        $due = $this->make('due@example.com', 0, $now);
        invitation::send($due, $now - 5 * DAYSECS);

        // Sent recently → not due.
        $recent = $this->make('recent@example.com', 0, $now);
        invitation::send($recent, $now - HOURSECS);

        // Sent long ago but already reminded → not due.
        $reminded = $this->make('reminded@example.com', 0, $now);
        invitation::send($reminded, $now - 5 * DAYSECS);
        invitation::remind($reminded, $now - 4 * DAYSECS);

        $ids = invitation::due_reminders($cutoff, $now);
        $this->assertContains($due, $ids);
        $this->assertNotContains($recent, $ids);
        $this->assertNotContains($reminded, $ids);
    }
}
