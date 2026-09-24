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

namespace tool_flexaccess\task;

use tool_flexaccess\local\reconciliation;

/**
 * Continue an unfinished account reconciliation run (upgrade backfill or system-status repair).
 *
 * Works in time-boxed slices and re-queues itself until the run is complete. Safe to retry: the run
 * resumes from its persisted cursor, and every repair is idempotent. A technical error makes the task
 * fail (and be retried by cron) without losing the progress of completed batches.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reconcile_accounts extends \core\task\adhoc_task {
    /** Seconds per slice. */
    private const SLICE = 300;

    /**
     * Continue the run.
     *
     * @return void
     */
    public function execute(): void {
        $state = reconciliation::state();
        if ($state->status !== 'running') {
            return;
        }
        $state = reconciliation::run(reconciliation::MODE_REPAIR, $state->source ?: reconciliation::SOURCE_TASK, self::SLICE);
        mtrace('FlexAccess reconciliation: ' . $state->checked . ' accounts checked, status ' . $state->status . '.');
        if ($state->status === 'running') {
            reconciliation::queue_continuation();
        }
    }
}
