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

namespace tool_flexaccess\task;

use tool_flexaccess\local\batch;

/**
 * Provision the accounts of a large batch outside the web request.
 *
 * Creating hundreds of accounts synchronously would tie up a request and risk timing out part-way
 * through. The batch row is created immediately (status 'queued') and this task fills it, so the
 * user gets an instant response and a visible provisioning state.
 *
 * Safe to retry: provision_members() only creates the accounts still missing from the batch, so a
 * task that Moodle re-runs after a failure resumes instead of duplicating accounts or enrolments.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provision_batch extends \core\task\adhoc_task {
    /**
     * Provision the batch described by this task's custom data.
     *
     * @return void
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $batchid = (int) ($data['batchid'] ?? 0);
        if (!$batchid || !batch::get($batchid)) {
            // The batch was deleted before the task ran; nothing to do.
            return;
        }
        batch::provision_members(
            $batchid,
            (int) ($data['count'] ?? 0),
            (bool) ($data['permanent'] ?? false),
            (string) ($data['prefix'] ?? 'kurs'),
            (int) ($data['passwordlength'] ?? 10),
            isset($data['timeexpires']) ? (int) $data['timeexpires'] : null
        );
    }
}
