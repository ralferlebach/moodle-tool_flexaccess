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

/**
 * Read-only presenter for effective FlexAccess policy diagnostics.
 *
 * The admin tool only reads and displays the effective enrol policy via the enrol facade. It
 * never receives, stores or renders any secret value or hash (e.g. access-key hashes) — only
 * the effective access-key scope (none/system/course).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess\local;

/** Turns an effective policy into a flat, secret-free set of display values. */
final class policy_presenter {
    /**
     * Summarise the effective policy as an ordered map of display keys to scalar values.
     *
     * @param \enrol_flexaccess\local\policy $policy Effective policy from the enrol facade.
     * @param bool $enabled Whether the course has an enabled FlexAccess enrolment instance.
     * @return array<string, bool|int|string>
     */
    public static function summarise(\enrol_flexaccess\local\policy $policy, bool $enabled): array {
        return [
            'targetenabled' => $enabled,
            'allowtemporary' => $policy->allowtemporary,
            'allowquick' => $policy->allowquick,
            'allowguest' => $policy->allowguest,
            'allownormallogin' => $policy->allownormallogin,
            'availablefrom' => (int) $policy->availablefrom,
            'availableuntil' => (int) $policy->availableuntil,
            'maxparticipants' => (int) $policy->maxparticipants,
            'participantvisibility' => (string) $policy->participantvisibility,
            'accesskeyscope' => (string) $policy->temporaryaccesskeyscope,
        ];
    }
}
