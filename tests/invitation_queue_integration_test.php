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

use tool_flexaccess\local\invitation;

/**
 * Invitations must go through the central FlexAccess mail queue, not around it.
 *
 * The earlier immediate-send fix kept the token out of storage but bypassed the shared hourly send
 * limit, retry/backoff and queue monitoring. These tests pin down that both properties now hold at
 * once: queued (and therefore rate-limited) AND secret-free.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_flexaccess\local\invitation_mail_renderer::class)]
final class invitation_queue_integration_test extends \advanced_testcase {
    /**
     * Create a pending invitation for a fresh course.
     *
     * @param string $email Recipient.
     * @return int Invitation id.
     */
    private function make(string $email): int {
        $course = $this->getDataGenerator()->create_course();
        return invitation::create((int) $course->id, $email, 0, null, time());
    }

    public function test_invitations_are_subject_to_the_hourly_send_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!method_exists('\auth_flexaccess\api', 'queue_deferred_mail')) {
            $this->markTestSkipped('auth_flexaccess predates api::queue_deferred_mail().');
        }
        // Ten mails per hour may leave this site (the smallest non-zero allowed limit).
        set_config('maillimitperhour', 10, 'auth_flexaccess');
        // Nine of this hour's budget is already spent, so exactly one invitation can still go out.
        $now = time();
        for ($i = 0; $i < 9; $i++) {
            $DB->insert_record('auth_flexaccess_mailqueue', (object) [
                'userid' => null,
                'recipient' => "spent$i@example.com",
                'mailtype' => 'test',
                'payloadjson' => json_encode(['subject' => 's', 'body' => 'b', 'bodyhtml' => 'b']),
                'status' => 'sent',
                'attempts' => 0,
                'timecreated' => $now - 60,
                'nextrun' => $now - 60,
                'timesent' => $now - 60,
            ]);
        }

        $first = $this->make('one@example.com');
        $second = $this->make('two@example.com');
        invitation::send($first);
        invitation::send($second);
        $this->assertSame(2, $DB->count_records('auth_flexaccess_mailqueue', ['status' => 'queued']));

        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time());
        $sink->close();

        // The limit applies to invitations exactly as to every other FlexAccess mail: one goes out,
        // the other stays queued for the next run instead of being blasted out immediately.
        $this->assertSame(10, $DB->count_records('auth_flexaccess_mailqueue', ['status' => 'sent']));
        $this->assertSame(1, $DB->count_records('auth_flexaccess_mailqueue', ['status' => 'queued']));
    }

    public function test_revoked_invitation_mints_no_token_at_delivery(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        if (!method_exists('\auth_flexaccess\api', 'queue_deferred_mail')) {
            $this->markTestSkipped('auth_flexaccess predates api::queue_deferred_mail().');
        }
        $id = $this->make('gone@example.com');
        invitation::send($id);
        // Revoked between queueing and delivery: the worker must not mint a usable token.
        invitation::revoke($id);

        $sink = $this->redirectEmails();
        \auth_flexaccess\local\mail_worker::run(time());
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages);
        $this->assertSame(0, (int) invitation::get($id)->timesent);
    }
}
