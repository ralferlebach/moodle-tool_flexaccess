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
 * Create/edit form for a FlexAccess invitation campaign.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaign_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courses = (array) ($this->_customdata['courses'] ?? []);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('campaignname', 'tool_flexaccess'), ['size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'courseid', get_string('campaigncourse', 'tool_flexaccess'), $courses);
        $mform->setType('courseid', PARAM_INT);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', get_string('campaignenabled', 'tool_flexaccess'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('date_time_selector', 'timeavailablefrom', get_string('campaignfrom', 'tool_flexaccess'), [
            'optional' => true,
        ]);
        $mform->addElement('date_time_selector', 'timeavailableuntil', get_string('campaignuntil', 'tool_flexaccess'), [
            'optional' => true,
        ]);

        $mform->addElement('text', 'maxredemptions', get_string('campaignmax', 'tool_flexaccess'), ['size' => 8]);
        $mform->setType('maxredemptions', PARAM_INT);
        $mform->addHelpButton('maxredemptions', 'campaignmax', 'tool_flexaccess');

        $mform->addElement('select', 'gatemode', get_string('campaigngate', 'tool_flexaccess'), [
            'none' => get_string('gatenone', 'tool_flexaccess'),
            'password' => get_string('gatepassword', 'tool_flexaccess'),
            'domain' => get_string('gatedomain', 'tool_flexaccess'),
        ]);
        $mform->setDefault('gatemode', 'none');

        $mform->addElement('passwordunmask', 'gatepassword', get_string('campaigngatepassword', 'tool_flexaccess'));
        $mform->setType('gatepassword', PARAM_RAW);
        $mform->hideIf('gatepassword', 'gatemode', 'neq', 'password');

        $mform->addElement('textarea', 'gatedomains', get_string('campaigngatedomains', 'tool_flexaccess'));
        $mform->setType('gatedomains', PARAM_RAW);
        $mform->hideIf('gatedomains', 'gatemode', 'neq', 'domain');

        $this->add_action_buttons();
    }

    /**
     * Validation: an end date must not precede the start date.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $from = (int) ($data['timeavailablefrom'] ?? 0);
        $until = (int) ($data['timeavailableuntil'] ?? 0);
        if ($from > 0 && $until > 0 && $until < $from) {
            $errors['timeavailableuntil'] = get_string('campaignbadwindow', 'tool_flexaccess');
        }
        return $errors;
    }
}
