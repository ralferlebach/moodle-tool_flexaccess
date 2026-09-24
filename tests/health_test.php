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

use tool_flexaccess\local\health;

/**
 * Tests for the cross-plugin FlexAccess system status (issue tool#6).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_flexaccess\local\health
 * @covers \tool_flexaccess\event\health_repaired
 */
final class health_test extends \advanced_testcase {
    /**
     * Skip without both siblings.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!method_exists('\enrol_flexaccess\api', 'role_model_problems')) {
            $this->markTestSkipped('Requires auth_flexaccess and enrol_flexaccess 1.1.0+.');
        }
        $this->resetAfterTest();
    }

    /**
     * Find an item by key prefix.
     *
     * @param array $items Items.
     * @param string $prefix Key prefix.
     * @return \stdClass|null
     */
    private function item(array $items, string $prefix): ?\stdClass {
        foreach ($items as $item) {
            if (str_starts_with($item->key, $prefix)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Auth and enrol activation are checked against each other.
     *
     * @return void
     */
    public function test_plugin_readiness(): void {
        set_config('auth', '');
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $items = health::check_plugins();
        $this->assertSame(health::ERROR, $this->item($items, 'enabled_auth')->status);
        $this->assertSame(health::OK, $this->item($items, 'enabled_enrol')->status);
        set_config('auth', 'flexaccess');
        set_config('enrol_plugins_enabled', 'manual');
        $items = health::check_plugins();
        $this->assertSame(health::OK, $this->item($items, 'enabled_auth')->status);
        $this->assertSame(health::WARNING, $this->item($items, 'enabled_enrol')->status);
        // Installed siblings satisfy their declared dependencies.
        $this->assertNull($this->item($items, 'dependency_'));
        $this->assertNull($this->item($items, 'installed_auth'));
    }

    /**
     * Role problems, invariant violations, tasks, mail funnel are reported; the overall verdict follows.
     *
     * @return void
     */
    public function test_checks_detect_problems(): void {
        global $DB;
        \enrol_flexaccess\local\participant_role::ensure();
        $this->assertSame(health::OK, $this->item(health::check_roles(), 'rolemodel')->status);
        set_role_contextlevels(\enrol_flexaccess\local\participant_role::get_id(), [CONTEXT_COURSE, CONTEXT_SYSTEM]);
        $role = $this->item(health::check_roles(), 'role_participantcontext');
        $this->assertSame(health::ERROR, $role->status);
        $this->assertSame(health::REPAIR_ROLES, $role->repair);

        // A temporary account without restriction is a critical invariant violation.
        $userid = \auth_flexaccess\api::create_temporary_user(time() + 3600);
        $item = $this->item(health::check_invariants(time()), 'invariant_temporary_unrestricted');
        $this->assertSame(health::ERROR, $item->status);
        $this->assertSame(health::REPAIR_RECONCILE, $item->repair);

        // Tasks: disabling one is reported.
        $task = \core\task\manager::get_scheduled_task('\auth_flexaccess\task\expire_accounts');
        $task->set_disabled(true);
        \core\task\manager::configure_scheduled_task($task);
        $taskitem = $this->item(health::check_tasks(time()), 'task_auth_flexaccess\task\expire_accounts');
        $this->assertSame(health::ERROR, $taskitem->status);

        // Mail funnel: failed set-password mails are prioritised as errors.
        $DB->insert_record('auth_flexaccess_mailqueue', (object) [
            'userid' => $userid, 'recipient' => 'x@example.com', 'mailtype' => 'set_password', 'payloadjson' => '{}',
            'status' => 'failed', 'attempts' => 5, 'timecreated' => time(), 'nextrun' => time(),
        ]);
        $this->assertSame(health::ERROR, $this->item(health::check_mail(time()), 'mail_funnel')->status);
        $this->assertSame(health::ERROR, health::overall(health::run()));
    }

    /**
     * Repairs are previewable, deterministic, logged, and never touch enrolments.
     *
     * @return void
     */
    public function test_repairs_are_safe_and_logged(): void {
        global $DB;
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course();
        $temporary = \auth_flexaccess\api::create_temporary_user(time() + 3600);
        \enrol_flexaccess\local\enrol_service::admin_enrol((int) $course->id, $temporary, false);
        $enrolments = $DB->get_records('user_enrolments', ['userid' => $temporary]);

        $preview = health::preview(health::REPAIR_RECONCILE);
        $this->assertContains($temporary, $preview['userids']);
        $sink = $this->redirectEvents();
        $this->assertGreaterThanOrEqual(1, health::repair(health::REPAIR_RECONCILE));
        $events = array_filter($sink->get_events(), static fn($e) => $e instanceof event\health_repaired);
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame(health::REPAIR_RECONCILE, reset($events)->other['repair']);
        $this->assertNotContains($temporary, health::preview(health::REPAIR_RECONCILE)['userids']);
        // Enrolments are exactly as before.
        $this->assertEquals($enrolments, $DB->get_records('user_enrolments', ['userid' => $temporary]));
        // Unsuspending an ACTIVE account is not an automatic repair.
        $this->assertNotContains('activesuspended', health::repairs());
    }

    /**
     * A course method neutralised by a higher policy is explained with the course.
     *
     * @return void
     */
    public function test_policy_conflict_is_explained(): void {
        global $DB;
        set_config('enrol_plugins_enabled', 'manual,flexaccess');
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Conflict course']);
        $enrolid = \enrol_flexaccess\local\enrol_service::ensure_instance((int) $course->id);
        $DB->set_field('enrol_flexaccess_instance', 'allowtemporary', 1, ['enrolid' => $enrolid]);
        set_config('allowtemporary', 0, 'enrol_flexaccess');
        $item = $this->item(health::check_policies(), 'policy_' . $enrolid . '_allowtemporary');
        $this->assertNotNull($item);
        $this->assertStringContainsString('Conflict course', $item->label);
    }
}
