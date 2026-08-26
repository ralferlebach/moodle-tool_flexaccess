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

namespace tool_flexaccess\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Provision a batch of course accounts with random credentials.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_form extends \moodleform {
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

        $mform->addElement(
            'course',
            'courseid',
            get_string('batchcourse', 'tool_flexaccess'),
            ['multiple' => false]
        );
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'permanent', get_string('batchaccounttype', 'tool_flexaccess'), [
            0 => get_string('batchtype_temporary', 'tool_flexaccess'),
            1 => get_string('batchtype_permanent', 'tool_flexaccess'),
        ]);
        $mform->addHelpButton('permanent', 'batchaccounttype', 'tool_flexaccess');

        $mform->addElement('text', 'count', get_string('batchcount', 'tool_flexaccess'), ['size' => 6]);
        $mform->setType('count', PARAM_INT);
        $mform->addRule('count', get_string('required'), 'required', null, 'client');
        $mform->setDefault('count', 10);

        $mform->addElement(
            'text',
            'usernameprefix',
            get_string('batchusernameprefix', 'tool_flexaccess'),
            ['size' => 20]
        );
        $mform->setType('usernameprefix', PARAM_ALPHANUM);
        $mform->setDefault('usernameprefix', 'kurs');
        $mform->addHelpButton('usernameprefix', 'batchusernameprefix', 'tool_flexaccess');

        $mform->addElement('select', 'passwordlength', get_string('batchpasswordlength', 'tool_flexaccess'), [
            8 => '8', 10 => '10', 12 => '12', 14 => '14', 16 => '16',
        ]);
        $mform->setDefault('passwordlength', 10);

        $mform->addElement(
            'duration',
            'expiry',
            get_string('batchexpiry', 'tool_flexaccess'),
            ['optional' => true]
        );
        $mform->addHelpButton('expiry', 'batchexpiry', 'tool_flexaccess');
        $mform->hideIf('expiry', 'permanent', 'eq', 1);

        $this->add_action_buttons(true, get_string('batchcreate', 'tool_flexaccess'));
    }

    /**
     * Validate the requested account count.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $count = (int) ($data['count'] ?? 0);
        if ($count < 1 || $count > 1000) {
            $errors['count'] = get_string('batchcountrange', 'tool_flexaccess');
        }
        return $errors;
    }
}
