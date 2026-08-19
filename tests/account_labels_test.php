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

namespace tool_flexaccess;

use tool_flexaccess\local\account_labels;
use auth_flexaccess\local\account_type;
use auth_flexaccess\local\account_state;

/**
 * Tests for the account enum label helper.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flexaccess\local\account_labels
 */
final class account_labels_test extends \advanced_testcase {
    /**
     * Known enum values map to localised, non-raw labels.
     *
     * @return void
     */
    public function test_known_values_are_localised(): void {
        $this->assertSame(
            get_string('accounttype_temporary', 'tool_flexaccess'),
            account_labels::type(account_type::TEMPORARY_USER)
        );
        $this->assertSame(
            get_string('accountstate_active', 'tool_flexaccess'),
            account_labels::state(account_state::ACTIVE)
        );
        // The label must differ from the raw stored value.
        $this->assertNotSame(account_type::TEMPORARY_USER, account_labels::type(account_type::TEMPORARY_USER));
    }

    /**
     * Unknown values fall back to the raw string rather than erroring.
     *
     * @return void
     */
    public function test_unknown_value_falls_back(): void {
        $this->assertSame('mystery', account_labels::type('mystery'));
        $this->assertSame('mystery', account_labels::state('mystery'));
    }
}
