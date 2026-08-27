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

namespace tool_flexaccess\tests\fixtures;

/**
 * Deferred mail renderer whose acknowledgement always fails.
 *
 * Used to exercise the one case that cannot be produced with real components: the mail is delivered
 * successfully, but telling the owning component about it does not work.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class failing_ack_renderer {
    /**
     * Render a trivial, deliverable mail.
     *
     * @param array $context Queue context.
     * @param int $now Current time.
     * @return array{0:string,1:string,2:string}
     */
    public static function render_deferred_mail(array $context, int $now): array {
        return ['Subject', 'Body', '<p>Body</p>'];
    }

    /**
     * Always fail, simulating a broken acknowledgement.
     *
     * @param array $context Queue context.
     * @param int $now Current time.
     * @return void
     */
    public static function deferred_mail_sent(array $context, int $now): void {
        throw new \moodle_exception('error');
    }
}
