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
 * Administrative form for a category-level FlexAccess policy override.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_policy_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $categories = (array) ($this->_customdata['categories'] ?? []);

        $tristate = [
            -1 => get_string('policyinherit', 'tool_flexaccess'),
            1 => get_string('policyallow', 'tool_flexaccess'),
            0 => get_string('policydeny', 'tool_flexaccess'),
        ];
        $visibility = [
            'inherit' => get_string('policyinherit', 'tool_flexaccess'),
            'show' => get_string('policyvisibilityshow', 'tool_flexaccess'),
            'hide' => get_string('policyvisibilityhide', 'tool_flexaccess'),
        ];

        $mform->addElement('select', 'categoryid', get_string('policycategory', 'tool_flexaccess'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', get_string('required'), 'required', null, 'client');

        foreach (['allowtemporary', 'allowquick', 'allowguest', 'allownormallogin'] as $flag) {
            $mform->addElement('select', $flag, get_string('policy' . $flag, 'tool_flexaccess'), $tristate);
            $mform->setType($flag, PARAM_INT);
            $mform->setDefault($flag, -1);
        }

        $mform->addElement('duration', 'temporarylifetime', get_string('policytemporarylifetime', 'tool_flexaccess'), [
            'optional' => true,
        ]);

        $mform->addElement('duration', 'provisionallifetime', get_string('policyprovisionallifetime', 'tool_flexaccess'), [
            'optional' => true,
        ]);

        $mform->addElement('select', 'participantvisibility', get_string('policyvisibility', 'tool_flexaccess'), $visibility);
        $mform->setType('participantvisibility', PARAM_ALPHA);
        $mform->setDefault('participantvisibility', 'inherit');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
