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

use tool_flexaccess\local\invitation;

/**
 * Scheduled task that sends a one-off reminder for unanswered invitations.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_invitation_reminders extends \core\task\scheduled_task {
    /** Default days after sending before a reminder goes out. */
    private const DEFAULT_DAYS = 3;

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskinvitationreminders', 'tool_flexaccess');
    }

    /**
     * Send due reminders. A value of 0 for the reminder window disables the task.
     *
     * @return void
     */
    public function execute(): void {
        $days = get_config('tool_flexaccess', 'invitationreminderdays');
        $days = ($days === false || $days === '') ? self::DEFAULT_DAYS : (int) $days;
        if ($days <= 0) {
            return;
        }
        $now = time();
        $cutoff = $now - $days * DAYSECS;
        foreach (invitation::due_reminders($cutoff, $now, 500) as $id) {
            invitation::remind($id, $now);
        }
    }
}
