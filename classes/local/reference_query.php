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
 * Validation helper for administrative reference-number searches.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess\local;

/** Normalises administrator-entered FlexAccess reference numbers. */
final class reference_query {
    /**
     * Normalise a reference-number search term.
     *
     * @param string $value Raw input.
     * @return string Normalised digits only, or empty string when invalid.
     */
    public static function normalise(string $value): string {
        $value = trim($value);
        return preg_match('/^[0-9]+$/', $value) ? $value : '';
    }
}
