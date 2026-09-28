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

use auth_flexaccess\local\account_service;
use auth_flexaccess\local\account_state;
use tool_flexaccess\local\reconciliation;

/**
 * Batch decision about suspensions of unknown origin.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\reconciliation
 * @covers \auth_flexaccess\local\lifecycle
 */
final class lock_review_test extends \advanced_testcase {
    /** @var \stdClass FlexAccess enrol instance. */
    private $instance;

    /**
     * Course with FlexAccess.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        if (!method_exists('\auth_flexaccess\api', 'attribute_suspension')) {
            $this->markTestSkipped('Requires auth_flexaccess 2026092802+.');
        }
        $this->resetAfterTest();
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $course->id);
        $this->instance = $DB->get_record('enrol', ['id' => $enrolid]);
    }

    /**
     * A legacy frozen account: expired, suspended, origin unknown, enrolment suspended.
     *
     * @return int User id.
     */
    private function legacy(): int {
        global $DB;
        $userid = \auth_flexaccess\api::create_temporary_user(time() + 3600);
        enrol_get_plugin('flexaccess')->enrol_user($this->instance, $userid, null, 0, 0, ENROL_USER_SUSPENDED);
        $DB->set_field('auth_flexaccess_account', 'accountstate', account_state::EXPIRED, ['userid' => $userid]);
        $DB->set_field('user', 'suspended', 1, ['id' => $userid]);
        $DB->set_field('auth_flexaccess_account', 'lockedby', null, ['userid' => $userid]);
        reconciliation::reconcile_user($userid, reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK);
        return $userid;
    }

    /**
     * Undecided suspensions are listed; decided or FlexAccess-caused ones are not.
     *
     * @return void
     */
    public function test_listing(): void {
        global $DB;
        $undecided = $this->legacy();
        $flex = \auth_flexaccess\api::create_temporary_user(time() - 1);
        account_service::expire_due();
        reconciliation::reconcile_user($flex, reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK);
        $this->assertSame([$undecided], reconciliation::lock_review_userids());
    }

    /**
     * Administrative decision: suspension stays, is no longer reported, never lifted.
     *
     * @return void
     */
    public function test_decide_admin(): void {
        global $DB;
        $this->setAdminUser();
        $userid = $this->legacy();
        $active = (int) $this->getDataGenerator()->create_user(['auth' => 'flexaccess', 'suspended' => 1])->id;
        account_service::create_authenticated($active, account_service::generate_unique_reference());
        reconciliation::reconcile_user($active, reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK);
        $this->assertEqualsCanonicalizing([$userid, $active], reconciliation::lock_review_userids());

        $results = reconciliation::decide_lock_origin([$userid, $active], reconciliation::DECIDE_ADMIN);
        $this->assertSame([$userid => 'decided', $active => 'decided'], $results);
        foreach ([$userid, $active] as $id) {
            $this->assertSame('admin', \auth_flexaccess\api::get_account($id)->lockedby);
            $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $id]));
            $this->assertFalse(\auth_flexaccess\api::suspension_liftable($id));
        }
        // No longer reported - neither as case nor as invariant violation - also after a full rerun.
        set_config('reconcile_state', '', 'tool_flexaccess');
        reconciliation::run(reconciliation::MODE_REPAIR, reconciliation::SOURCE_TASK);
        $this->assertSame([], reconciliation::lock_review_userids());
        $this->assertArrayNotHasKey('active_suspended', \auth_flexaccess\api::count_state_mismatches());
        // A later recovery attempt leaves it alone.
        $this->assertSame('foreignlock', local\recovery::recover($active, null)->reason);
        // Audit trail with the acting administrator.
        $row = $DB->get_record('tool_flexaccess_reconcile_log', ['userid' => $active, 'rule' => 'attribute_lock_admin']);
        $this->assertEquals(get_admin()->id, $row->actorid);
    }

    /**
     * FlexAccess decision: recorded only, then recoverable; with recovery: account and enrolment active.
     *
     * @return void
     */
    public function test_decide_flexaccess_and_recover(): void {
        global $DB;
        $this->setAdminUser();
        $later = $this->legacy();
        $now = $this->legacy();

        $this->assertSame([$later => 'decided'], reconciliation::decide_lock_origin([$later], reconciliation::DECIDE_FLEXACCESS));
        $this->assertSame('flexaccess', \auth_flexaccess\api::get_account($later)->lockedby);
        $this->assertEquals(1, $DB->get_field('user', 'suspended', ['id' => $later]));
        $this->assertTrue(\auth_flexaccess\api::suspension_liftable($later));

        $results = reconciliation::decide_lock_origin([$now], reconciliation::DECIDE_FLEXACCESS, true, true);
        $this->assertSame([$now => 'recovered'], $results);
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $now]));
        $this->assertSame(account_state::EPHEMERAL, \auth_flexaccess\api::get_account($now)->accountstate);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id, 'userid' => $now]);
        $this->assertEquals(ENROL_USER_ACTIVE, $ue->status);
        $this->assertSame([], reconciliation::lock_review_userids());
    }

    /**
     * Users without an open lock review are skipped; the right is enforced.
     *
     * @return void
     */
    public function test_skip_and_capability(): void {
        $this->setAdminUser();
        $live = \auth_flexaccess\api::create_temporary_user(time() + 3600);
        $this->assertSame([$live => 'skipped'], reconciliation::decide_lock_origin([$live], reconciliation::DECIDE_ADMIN));
        $this->assertFalse(\auth_flexaccess\api::attribute_suspension($live, 'admin'), 'not suspended');
        $this->assertFalse(\auth_flexaccess\api::attribute_suspension($live, 'whatever'));

        $userid = $this->legacy();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        reconciliation::decide_lock_origin([$userid], reconciliation::DECIDE_ADMIN);
    }
}
