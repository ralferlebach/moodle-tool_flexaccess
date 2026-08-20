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

use tool_flexaccess\local\campaign;

/**
 * Tests for the FlexAccess invitation campaign service.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_flexaccess\local\campaign
 */
final class campaign_test extends \advanced_testcase {
    /**
     * Create a campaign for a course with the given overrides.
     *
     * @param array $overrides Field overrides.
     * @return int Campaign id.
     */
    private function make(array $overrides = []): int {
        $course = $this->getDataGenerator()->create_course();
        return campaign::create($overrides + [
            'name' => 'Spring intake',
            'courseid' => (int) $course->id,
            'enabled' => 1,
            'timeavailablefrom' => 0,
            'timeavailableuntil' => 0,
            'maxredemptions' => 0,
            'gatemode' => 'none',
        ]);
    }

    /**
     * Create/get/token round-trips, and update preserves token and redemption count.
     *
     * @return void
     */
    public function test_create_get_and_update(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = $this->make(['name' => 'Cohort A']);
        $campaign = campaign::get($id);
        $this->assertNotNull($campaign);
        $this->assertSame('Cohort A', $campaign->name);
        $this->assertNotEmpty($campaign->token);
        $this->assertSame($campaign->id, campaign::get_by_token($campaign->token)->id);

        campaign::redeem($id);
        campaign::update($id, [
            'name' => 'Cohort A renamed', 'courseid' => (int) $campaign->courseid,
            'enabled' => 1, 'maxredemptions' => 5, 'gatemode' => 'none',
        ]);
        $updated = campaign::get($id);
        $this->assertSame('Cohort A renamed', $updated->name);
        $this->assertSame($campaign->token, $updated->token);
        $this->assertSame(1, (int) $updated->redemptioncount);
    }

    /**
     * Redeemability respects enabled flag, availability window and redemption cap.
     *
     * @return void
     */
    public function test_is_redeemable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = 5000000;

        $open = campaign::get($this->make());
        $this->assertTrue(campaign::is_redeemable($open, $now));

        $disabled = campaign::get($this->make(['enabled' => 0]));
        $this->assertFalse(campaign::is_redeemable($disabled, $now));

        $future = campaign::get($this->make(['timeavailablefrom' => $now + 1000]));
        $this->assertFalse(campaign::is_redeemable($future, $now));

        $past = campaign::get($this->make(['timeavailableuntil' => $now - 1000]));
        $this->assertFalse(campaign::is_redeemable($past, $now));
    }

    /**
     * Redemption is atomic and never exceeds the cap.
     *
     * @return void
     */
    public function test_redeem_respects_cap(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = $this->make(['maxredemptions' => 2]);

        $this->assertTrue(campaign::redeem($id));
        $this->assertTrue(campaign::redeem($id));
        // Third attempt is refused; the count stays at the cap.
        $this->assertFalse(campaign::redeem($id));
        $this->assertSame(2, (int) campaign::get($id)->redemptioncount);
    }

    /**
     * Password and domain gates admit and reject the right applicants.
     *
     * @return void
     */
    public function test_gates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $pw = campaign::get($this->make(['gatemode' => 'password', 'gatepassword' => 'let-me-in']));
        $this->assertFalse(campaign::passes_gate($pw, 'a@x.com', 'nope'));
        $this->assertTrue(campaign::passes_gate($pw, 'a@x.com', 'let-me-in'));

        $dom = campaign::get($this->make(['gatemode' => 'domain', 'gatedomains' => "university.edu"]));
        $this->assertFalse(campaign::passes_gate($dom, 'a@gmail.com', ''));
        $this->assertTrue(campaign::passes_gate($dom, 'stud@mail.university.edu', ''));

        $none = campaign::get($this->make());
        $this->assertTrue(campaign::passes_gate($none, 'anyone@anywhere.com', ''));
    }
}
