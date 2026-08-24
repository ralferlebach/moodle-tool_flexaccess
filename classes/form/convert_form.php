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
 * Administrative conversion form for tool_flexaccess.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Collects the real email (and name) needed to convert a temporary account into a re-loginnable one.
 *
 * @package    tool_flexaccess
 */
class convert_form extends \moodleform {
    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'convertintro', '', get_string('convertintro', 'tool_flexaccess'));

        $mform->addElement('text', 'email', get_string('convertemail', 'tool_flexaccess'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('email', 'convertemail', 'tool_flexaccess');

        $mform->addElement('text', 'firstname', get_string('convertfirstname', 'tool_flexaccess'));
        $mform->setType('firstname', PARAM_NOTAGS);

        $mform->addElement('text', 'lastname', get_string('convertlastname', 'tool_flexaccess'));
        $mform->setType('lastname', PARAM_NOTAGS);

        $mform->addElement('hidden', 'userid', $this->_customdata['userid']);
        $mform->setType('userid', PARAM_INT);

        $this->add_action_buttons(true, get_string('accountconvert', 'tool_flexaccess'));
    }

    /**
     * Validate the submitted email.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['email']) || !validate_email(trim($data['email']))) {
            $errors['email'] = get_string('convertinvalidemail', 'tool_flexaccess');
        }
        return $errors;
    }
}
