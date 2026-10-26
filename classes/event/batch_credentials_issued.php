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
 * The credential package of a batch was issued (audit record, tool_flexaccess #15).
 *
 * objectid: batch id. other: count (accounts issued), reissue (whether an earlier package was
 * invalidated), personalised (accounts kept out because they are personalised), unavailable (usernames
 * kept out because they are no longer batch placeholders). No credential is ever part of the event.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class batch_credentials_issued extends \core\event\base {
    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'tool_flexaccess_batch';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventbatch_credentials_issued', 'tool_flexaccess');
    }

    /**
     * Non-localised description for the log.
     *
     * @return string
     */
    public function get_description() {
        $count = (int) ($this->other['count'] ?? 0);
        $kind = !empty($this->other['reissue']) ? 'reissued (the earlier package is invalid)' : 'issued';
        $personalised = (int) ($this->other['personalised'] ?? 0);
        $unavailable = count((array) ($this->other['unavailable'] ?? []));
        return "The user with id '{$this->userid}' {$kind} the credentials of the FlexAccess batch with id "
            . "'{$this->objectid}': {$count} account(s); {$personalised} personalised and {$unavailable} unavailable "
            . "account(s) not included.";
    }

    /**
     * Where the batch is shown.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/flexaccess/batches.php', ['action' => 'view', 'id' => $this->objectid]);
    }

    /**
     * Validate the event data.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
        foreach (['count', 'reissue'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    /**
     * Mapping for backup and restore of the log.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'tool_flexaccess_batch', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Mapping of other for backup and restore of the log.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
