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
 * Create a batch of anonymous access accounts for one course.
 *
 * Identical to the site-wide batch form minus the course selector: the course is fixed by the
 * page (teacher-facing, course context), so the teacher never picks a course.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursebatch_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('batchname', 'tool_flexaccess'), ['size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'permanent', get_string('batchaccounttype', 'tool_flexaccess'), [
            0 => get_string('batchtype_temporary', 'tool_flexaccess'),
            1 => get_string('batchtype_permanent', 'tool_flexaccess'),
        ]);

        $mform->addElement('text', 'count', get_string('batchcount', 'tool_flexaccess'), ['size' => 6]);
        $mform->setType('count', PARAM_INT);
        $mform->addRule('count', get_string('required'), 'required', null, 'client');
        $mform->setDefault('count', 10);

        $mform->addElement(
            'text',
            'usernameprefix',
            get_string('batchusernameprefix', 'tool_flexaccess'),
            ['size' => 16]
        );
        $mform->setType('usernameprefix', PARAM_ALPHANUM);
        $mform->setDefault('usernameprefix', 'kurs');

        $this->add_action_buttons(true, get_string('batchcreate', 'tool_flexaccess'));
    }

    /**
     * Validate the submitted counts.
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
