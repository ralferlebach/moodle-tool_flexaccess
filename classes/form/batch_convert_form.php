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

use tool_flexaccess\local\batch_import;

/**
 * Upload a filled batch export to convert its accounts to permanent ones.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_convert_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id'] ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement(
            'filepicker',
            'excelfile',
            get_string('batch:uploadfile', 'tool_flexaccess'),
            null,
            ['accepted_types' => ['.xlsx'], 'maxbytes' => \tool_flexaccess\local\batch_import::MAX_IMPORT_BYTES]
        );
        $mform->addRule('excelfile', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'select',
            'usernamerule',
            get_string('batch:usernamerule', 'tool_flexaccess'),
            batch_import::rules()
        );
        $mform->setDefault('usernamerule', batch_import::RULE_EMAIL);
        $mform->addHelpButton('usernamerule', 'batch:usernamerule', 'tool_flexaccess');

        $this->add_action_buttons(true, get_string('batch:convert', 'tool_flexaccess'));
    }
}
