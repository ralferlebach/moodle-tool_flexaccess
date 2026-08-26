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

namespace tool_flexaccess\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Request a batch of anonymous access accounts for a course (teacher without provisioning rights).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursebatchrequest_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'intro', '', get_string('coursebatches_requestintro', 'tool_flexaccess'));

        $mform->addElement('text', 'count', get_string('batchrequestcount', 'tool_flexaccess'), ['size' => 6]);
        $mform->setType('count', PARAM_INT);
        $mform->addRule('count', get_string('required'), 'required', null, 'client');
        $mform->setDefault('count', 10);

        $this->add_action_buttons(true, get_string('coursebatches_request', 'tool_flexaccess'));
    }

    /**
     * Validate the requested count.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int) $data['count'] < 1 || (int) $data['count'] > 1000) {
            $errors['count'] = get_string('batchcountrange', 'tool_flexaccess');
        }
        return $errors;
    }
}
