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

namespace tool_flexaccess\local;

/**
 * HTTP method contract of the administration pages (tool_flexaccess #14).
 *
 * Every action that changes something - accounts, credentials, the merge register, LTI bindings, enrolments,
 * the mail queue - is accepted only as POST, checked before any side effect and in addition to the sesskey
 * and capability checks. A GET (or HEAD) with a valid sesskey, a prefetch or a reload changes nothing.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /**
     * Whether the current request is a POST.
     *
     * @return bool
     */
    public static function is_post(): bool {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST';
    }

    /**
     * Refuse anything but POST for a changing action, before any side effect.
     *
     * @return void
     * @throws \moodle_exception invalidrequest for every other method.
     */
    public static function require_post(): void {
        if (!self::is_post()) {
            throw new \moodle_exception('invalidrequest', 'error');
        }
    }
}
