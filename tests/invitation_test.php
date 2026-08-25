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

use PHPUnit\Framework\Attributes\CoversClass;
use tool_flexaccess\local\invitation;

/**
 * Tests for the person-bound FlexAccess invitation service.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_flexaccess\local\invitation::class)]
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
     * Skip a test that needs the auth_flexaccess mail queue when that plugin is not installed
     * (e.g. when this plugin's suite is run in isolation without its declared dependency).
     *
     * @return void
     */
    private function require_auth_queue(): void {
        global $DB;
        if (
            !class_exists('\\auth_flexaccess\\api')
                || !$DB->get_manager()->table_exists('auth_flexaccess_mailqueue')
        ) {
            $this->markTestSkipped('auth_flexaccess (mail queue) is not installed.');
        }
    }

    /**
     * Send an invitation and return the plaintext single-use token from the queued mail. The token
     * only ever exists in the outgoing mail (the invitation stores just its hash), so this mirrors
     * the real acceptance path. Requires the auth mail queue.
     *
     * @param int $id Invitation id.
     * @return string
     */
    private function sent_token(int $id): string {
        global $DB;
        $this->require_auth_queue();
        invitation::send($id);
        $invite = invitation::get($id);
        $jobs = $DB->get_records('auth_flexaccess_mailqueue', ['recipient' => $invite->email], 'id DESC', '*', 0, 1);
        $job = reset($jobs);
        $payload = json_decode($job->payloadjson);
        preg_match('/token=([A-Za-z0-9]+)/', $payload->body, $m);
        return $m[1];
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
        $this->assertTrue(invitation::is_acceptable($invite));
        // The token materialises only on send; look it up via the hash.
        $token = $this->sent_token($id);
        $this->assertSame($invite->id, invitation::get_by_token($token)->id);
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
        $this->require_auth_queue();
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
        $id = $this->make('d@example.com');
        $token = $this->sent_token($id);

        $first = invitation::accept($token);
        $this->assertNotNull($first);
        $this->assertSame(invitation::STATUS_ACCEPTED, $first->status);
        // Second attempt is refused; an accepted invite is not acceptable.
        $this->assertNull(invitation::accept($token));
    }

    /**
     * due_reminders returns only pending, sent, unreminded, unexpired invitations past the cutoff.
     *
     * @return void
     */
    public function test_due_reminders(): void {
        $this->require_auth_queue();
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

    /**
     * P0-2: reserving does not consume the invitation; releasing returns it to pending so a failed
     * registration can be retried, while committing finalises it (and blocks reuse).
     *
     * @return void
     */
    public function test_reserve_release_and_commit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = $this->make('reserve@example.com');
        $token = $this->sent_token($id);

        // Reserve -> not yet accepted; concurrent reserve is blocked.
        $reserved = invitation::reserve($token);
        $this->assertNotNull($reserved);
        $this->assertSame(invitation::STATUS_RESERVED, invitation::get($id)->status);
        $this->assertNull(invitation::reserve($token));

        // Release -> back to pending, reusable.
        invitation::release_reservation($id);
        $this->assertSame(invitation::STATUS_PENDING, invitation::get($id)->status);
        $this->assertTrue(invitation::is_acceptable(invitation::get($id)));

        // Reserve again, then commit -> accepted and no longer usable.
        $this->assertNotNull(invitation::reserve($token));
        invitation::commit_acceptance($id);
        $this->assertSame(invitation::STATUS_ACCEPTED, invitation::get($id)->status);
        $this->assertNull(invitation::reserve($token));
    }
}
