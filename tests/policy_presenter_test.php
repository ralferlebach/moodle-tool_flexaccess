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
 * Tests for the tool_flexaccess policy presenter.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess;

use tool_flexaccess\local\policy_presenter;
use enrol_flexaccess\local\policy;

/**
 * Policy presenter tests.
 *
 * @package    tool_flexaccess
 * @covers \tool_flexaccess\local\policy_presenter
 */
final class policy_presenter_test extends \advanced_testcase {
    /**
     * Skip when the required sibling plugin is not installed (per-plugin CI).
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        if (!$DB->get_manager()->table_exists('enrol_flexaccess_instance')) {
            $this->markTestSkipped('Requires the enrol_flexaccess sibling plugin to be installed.');
        }
    }

    /**
     * The summary exposes the effective values and never a secret.
     */
    public function test_summarise_exposes_values_without_secrets(): void {
        $p = new policy();
        $p->allowtemporary = true;
        $p->allowquick = false;
        $p->allowguest = false;
        $p->allownormallogin = true;
        $p->availablefrom = 1000;
        $p->availableuntil = 2000;
        $p->maxparticipants = 30;
        $p->participantlistaccess = 'hide';
        $p->temporaryaccesskeyscope = 'course';

        $summary = policy_presenter::summarise($p, true);

        $this->assertTrue($summary['targetenabled']);
        $this->assertTrue($summary['allowtemporary']);
        $this->assertFalse($summary['allowquick']);
        $this->assertSame(1000, $summary['availablefrom']);
        $this->assertSame(2000, $summary['availableuntil']);
        $this->assertSame(30, $summary['maxparticipants']);
        $this->assertSame('hide', $summary['participantlistaccess']);
        $this->assertSame('course', $summary['accesskeyscope']);

        // No hash/secret key is ever present in the summary.
        $joined = strtolower(implode(' ', array_keys($summary)));
        $this->assertStringNotContainsString('hash', $joined);
        $this->assertStringNotContainsString('secret', $joined);
    }
}
