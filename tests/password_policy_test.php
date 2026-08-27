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

namespace tool_flexaccess;

use tool_flexaccess\local\batch;

/**
 * Generated batch passwords must satisfy the site's configured password policy.
 *
 * A password the site would reject leaves the account unusable as soon as its owner tries to change
 * it, and quietly undercuts a security setting the administrator made deliberately.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\batch
 */
final class password_policy_test extends \advanced_testcase {
    public function test_generated_password_satisfies_a_strict_policy(): void {
        global $CFG;
        $this->resetAfterTest();
        // Demand more than the plugin's own default of eight characters, plus every character class.
        $CFG->passwordpolicy = 1;
        $CFG->minpasswordlength = 14;
        $CFG->minpassworddigits = 2;
        $CFG->minpasswordlower = 2;
        $CFG->minpasswordupper = 2;
        $CFG->minpasswordnonalphanum = 2;

        for ($i = 0; $i < 5; $i++) {
            // The caller asks for eight characters; the policy must win.
            $password = batch::generate_password(8);
            $errors = '';
            $this->assertTrue(
                check_password_policy($password, $errors),
                "Generated password violates the policy: $errors"
            );
            $this->assertGreaterThanOrEqual(14, \core_text::strlen($password));
        }
    }

    public function test_provisioning_uses_policy_compliant_passwords(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->passwordpolicy = 1;
        $CFG->minpasswordlength = 12;
        $CFG->minpassworddigits = 1;
        $CFG->minpasswordnonalphanum = 1;

        $course = $this->getDataGenerator()->create_course();
        $result = batch::create('Policy', (int) $course->id, false, 2, 'kurs', 8);

        $this->assertCount(2, $result['credentials']);
        foreach ($result['credentials'] as $password) {
            $errors = '';
            $this->assertTrue(check_password_policy($password, $errors), $errors);
        }
    }

    public function test_an_unsatisfiable_policy_fails_loudly(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->passwordpolicy = 1;
        // The generator's alphabet contains no such characters, so no candidate can ever pass.
        $CFG->minpasswordlength = 8;
        $CFG->minpasswordnonalphanum = 40;

        // Silently handing out a password the site rejects would produce unusable accounts.
        $this->expectException(\moodle_exception::class);
        batch::generate_password(10);
    }
}
