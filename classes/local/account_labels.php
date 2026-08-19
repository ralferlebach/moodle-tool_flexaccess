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
 * Maps FlexAccess account enum values to localised, human-readable labels.
 *
 * The stored enum values are internal identifiers; administrators should see translated labels
 * rather than raw values. The literal keys below mirror auth_flexaccess\\local\\account_type and
 * account_state; they are kept as literals so this admin tool's label mapping does not hard-depend
 * on auth_flexaccess being installed (e.g. during isolated CI), while staying correct in production.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class account_labels {
    /**
     * Localised label for an account type.
     *
     * @param string $value Raw account-type value.
     * @return string
     */
    public static function type(string $value): string {
        $map = [
            // Mirrors auth_flexaccess\local\account_type::TEMPORARY_USER / AUTHENTICATED_USER.
            'temporary user' => 'accounttype_temporary',
            'authenticated user' => 'accounttype_authenticated',
        ];
        return isset($map[$value]) ? get_string($map[$value], 'tool_flexaccess') : $value;
    }

    /**
     * Localised label for an account state.
     *
     * @param string $value Raw account-state value.
     * @return string
     */
    public static function state(string $value): string {
        $map = [
            // Mirrors auth_flexaccess\local\account_state::* values.
            'ephemeral' => 'accountstate_ephemeral',
            'provisional' => 'accountstate_provisional',
            'active' => 'accountstate_active',
            'expired' => 'accountstate_expired',
            'suspended' => 'accountstate_suspended',
        ];
        return isset($map[$value]) ? get_string($map[$value], 'tool_flexaccess') : $value;
    }
}
