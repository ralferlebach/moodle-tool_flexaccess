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
 * Tests for tool_flexaccess reference-number query validation.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Reference query tests.
 *
 * @package    tool_flexaccess
 */
#[CoversClass(\tool_flexaccess\local\reference_query::class)]
final class reference_query_test extends \advanced_testcase {
    /**
     * Test valid and invalid reference search terms.
     */
    public function test_normalise(): void {
        $this->assertSame('483921', \tool_flexaccess\local\reference_query::normalise(' 483921 '));
        $this->assertSame('', \tool_flexaccess\local\reference_query::normalise('48-3921'));
    }
}
