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

namespace tool_flexaccess\event;

/**
 * A frozen FlexAccess account was recovered (audit record).
 *
 * other: scope (course|system), courseid, oldstate, newstate, action, enrolments (list of ueid,
 * courseid, oldstatus, newstatus, newtimeend), reenrolled (course ids), outcomes.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_recovered extends \core\event\base {
    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventaccount_recovered', 'tool_flexaccess');
    }

    /**
     * Non-localised description for the log.
     *
     * @return string
     */
    public function get_description() {
        $scope = s((string) ($this->other['scope'] ?? ''));
        $old = s((string) ($this->other['oldstate'] ?? ''));
        $new = s((string) ($this->other['newstate'] ?? ''));
        $count = count((array) ($this->other['enrolments'] ?? []));
        return "The user with id '{$this->userid}' recovered the FlexAccess account of the user with id "
            . "'{$this->relateduserid}' ({$scope} scope): state {$old} -> {$new}, {$count} enrolment(s) reactivated.";
    }

    /**
     * Validate the event data.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        foreach (['scope', 'oldstate', 'newstate'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    /**
     * No other-field mapping is needed for backup/restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
