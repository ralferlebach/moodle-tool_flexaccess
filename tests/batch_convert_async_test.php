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
use tool_flexaccess\local\batch_import;

/**
 * Conversion imports: sync cap, background execution, idempotence and per-member errors.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_flexaccess\local\batch_import::class)]
final class batch_convert_async_test extends \advanced_testcase {
    /**
     * Create a batch and return its id plus rows that convert its members.
     *
     * @param int $count Number of members.
     * @return array{0:int,1:array}
     */
    private function batch_with_rows(int $count): array {
        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Convert', (int) $course->id, false, $count, 'kurs');
        $batchid = (int) $result['batchid'];
        // A batch above the provisioning threshold is created in the background. Run that queued
        // task properly (and complete it), so the members exist and it does not sit in the queue
        // ahead of the conversion task this test is about.
        if (($result['status'] ?? '') === batch::STATUS_QUEUED) {
            $provision = \core\task\manager::get_next_adhoc_task(time());
            $provision->execute();
            \core\task\manager::adhoc_task_complete($provision);
        }
        $rows = [];
        $i = 0;
        foreach (batch::members($batchid) as $member) {
            $i++;
            $rows[] = [
                'username' => $member->username,
                'firstname' => 'First' . $i,
                'lastname' => 'Last' . $i,
                'email' => "person$i@example.com",
                'newusername' => '',
            ];
        }
        return [$batchid, $rows];
    }

    public function test_retry_converts_nothing_twice_and_sends_no_duplicate_mail(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$batchid, $rows] = $this->batch_with_rows(3);

        $first = batch_import::convert($batchid, $rows);
        $this->assertSame(3, $first['converted']);
        $mailsafter = $DB->count_records('auth_flexaccess_mailqueue');
        $this->assertGreaterThan(0, $mailsafter);

        // Re-running the same import (as a retried task would) must be a no-op.
        $second = batch_import::convert($batchid, $rows);
        $this->assertSame(0, $second['converted']);
        $this->assertSame(3, $second['skipped']);
        // No second identity transition means no second set-password mail.
        $this->assertSame($mailsafter, $DB->count_records('auth_flexaccess_mailqueue'));
    }

    public function test_large_import_is_queued_and_run_by_the_task(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $count = batch_import::MAX_SYNC_CONVERT + 2;
        [$batchid, $rows] = $this->batch_with_rows($count);

        $result = batch_import::convert_dispatch($batchid, $rows);

        $this->assertTrue($result['queued']);
        $this->assertSame(0, $result['converted']);
        $this->assertSame(batch::STATUS_CONVERTING, batch::get($batchid)->status);

        $task = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\tool_flexaccess\task\convert_batch::class, $task);
        $task->execute();
        \core\task\manager::adhoc_task_complete($task);

        $batch = batch::get($batchid);
        $this->assertSame(batch::STATUS_COMPLETE, $batch->status);
        $converted = count(array_filter(
            batch::members($batchid),
            static fn($m) => !empty($m->converted)
        ));
        $this->assertSame($count, $converted);
    }

    public function test_row_level_failure_is_recorded_on_the_member(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$batchid, $rows] = $this->batch_with_rows(2);
        // Second row has no email: it must fail, and say so on its own member row.
        $rows[1]['email'] = '';

        $result = batch_import::convert($batchid, $rows);

        $this->assertSame(1, $result['converted']);
        $this->assertSame(1, $result['skipped']);
        $failed = $DB->get_records_select(
            'tool_flexaccess_batch_member',
            'batchid = :batchid AND converterror IS NOT NULL',
            ['batchid' => $batchid]
        );
        $this->assertCount(1, $failed);
    }
}
