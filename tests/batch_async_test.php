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
 * Large batches are provisioned by an ad-hoc task instead of the web request.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_flexaccess\local\batch::class)]
final class batch_async_test extends \advanced_testcase {
    public function test_small_batch_is_provisioned_synchronously(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = batch::create('Small', (int) $course->id, false, 3, 'kurs');

        $this->assertSame(batch::STATUS_COMPLETE, $result['status']);
        $this->assertCount(3, $result['credentials']);
        $this->assertSame(3, (int) batch::get((int) $result['batchid'])->membercount);
    }

    public function test_large_batch_is_queued_and_filled_by_the_task(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $count = batch::SYNC_THRESHOLD + 1;

        $result = batch::create('Large', (int) $course->id, false, $count, 'kurs');
        $batchid = (int) $result['batchid'];

        // The request returns immediately: queued, no accounts yet, no credentials inline.
        $this->assertSame(batch::STATUS_QUEUED, $result['status']);
        $this->assertSame([], $result['credentials']);
        $batch = batch::get($batchid);
        $this->assertSame(batch::STATUS_QUEUED, $batch->status);
        $this->assertSame(0, (int) $batch->membercount);
        $this->assertSame($count, (int) $batch->requestedcount);

        // Running the queued ad-hoc task provisions the accounts.
        $task = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\tool_flexaccess\task\provision_batch::class, $task);
        $task->execute();
        \core\task\manager::adhoc_task_complete($task);

        $batch = batch::get($batchid);
        $this->assertSame(batch::STATUS_COMPLETE, $batch->status);
        $this->assertSame($count, (int) $batch->membercount);
        $this->assertCount($count, batch::members($batchid));
    }
}
