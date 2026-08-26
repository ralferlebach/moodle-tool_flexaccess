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
use tool_flexaccess\local\batch_import;

/**
 * Run a large batch conversion import outside the web request.
 *
 * Each row performs several database, identity and mail operations, so a file with hundreds of rows
 * must not be processed while a user waits on a page.
 *
 * Safe to retry: batch_import::convert() skips members that are already converted, so a re-run
 * performs no second identity transition and sends no duplicate set-password mail.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class convert_batch extends \core\task\adhoc_task {
    /**
     * Convert the rows described by this task's custom data.
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
        $rows = array_map(static fn($row) => (array) $row, (array) ($data['rows'] ?? []));
        batch_import::convert($batchid, $rows, (string) ($data['usernamerule'] ?? batch_import::RULE_EMAIL));
    }
}
