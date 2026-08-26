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

/**
 * Failure and retry behaviour of batch provisioning.
 *
 * A failed provisioning run must never leave the batch stuck on CREATING (it would look like work
 * still in progress that will never finish), and a retry must resume rather than duplicate.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
final class batch_failure_test extends \advanced_testcase {
    public function test_failure_after_several_members_lands_in_failed_with_reason(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        // Queue a batch without provisioning it, then break provisioning by pointing it at a
        // course that no longer exists - a failure that occurs inside the provisioning loop.
        $result = batch::create('Broken', (int) $course->id, false, batch::SYNC_THRESHOLD + 5, 'kurs');
        $batchid = (int) $result['batchid'];
        $this->assertSame(batch::STATUS_QUEUED, batch::get($batchid)->status);

        $DB->set_field('tool_flexaccess_batch', 'courseid', -1, ['id' => $batchid]);
        try {
            batch::provision_members($batchid, batch::SYNC_THRESHOLD + 5, false, 'kurs');
            $this->fail('Provisioning should have failed for a missing course.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
        }

        $batch = batch::get($batchid);
        // The decisive assertion: not stuck on CREATING, and the reason is recorded.
        $this->assertSame(batch::STATUS_FAILED, $batch->status);
        $this->assertNotEmpty($batch->statusmessage);
    }

    public function test_retry_resumes_and_creates_no_duplicates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $total = 6;

        $result = batch::create('Resume', (int) $course->id, false, $total, 'kurs');
        $batchid = (int) $result['batchid'];
        // A small batch is provisioned synchronously and is already complete.
        $this->assertSame($total, $DB->count_records('tool_flexaccess_batch_member', ['batchid' => $batchid]));

        // Running provisioning again (as a retried ad-hoc task would) must be a no-op.
        batch::provision_members($batchid, $total, false, 'kurs');
        $this->assertSame($total, $DB->count_records('tool_flexaccess_batch_member', ['batchid' => $batchid]));
        $this->assertSame($total, (int) batch::get($batchid)->membercount);

        // After deleting some members, a retry recreates exactly the missing ones.
        $victims = $DB->get_records('tool_flexaccess_batch_member', ['batchid' => $batchid], 'id ASC', 'id', 0, 2);
        $DB->delete_records_list('tool_flexaccess_batch_member', 'id', array_keys($victims));
        batch::provision_members($batchid, $total, false, 'kurs');
        $this->assertSame($total, $DB->count_records('tool_flexaccess_batch_member', ['batchid' => $batchid]));
        $this->assertSame(batch::STATUS_COMPLETE, batch::get($batchid)->status);
    }

    public function test_progress_is_committed_per_chunk(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        // More than one chunk, so membercount has to be written more than once.
        $total = batch::PROVISION_CHUNK + 3;

        $result = batch::create('Chunked', (int) $course->id, false, $total, 'kurs');
        $batchid = (int) $result['batchid'];
        batch::provision_members($batchid, $total, false, 'kurs');

        $batch = batch::get($batchid);
        $this->assertSame(batch::STATUS_COMPLETE, $batch->status);
        $this->assertSame($total, (int) $batch->membercount);
    }
}
