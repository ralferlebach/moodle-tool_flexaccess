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
 * Create one or more person-bound invitations.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class invitation_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        // A searchable, AJAX-backed course selector scales to sites with very many courses.
        $mform->addElement(
            'course',
            'courseid',
            get_string('invitecourse', 'tool_flexaccess'),
            ['multiple' => false]
        );
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'emails',
            get_string('inviteemails', 'tool_flexaccess'),
            ['rows' => 6, 'cols' => 50]
        );
        $mform->setType('emails', PARAM_RAW);
        $mform->addRule('emails', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('emails', 'inviteemails', 'tool_flexaccess');

        $mform->addElement(
            'duration',
            'expiry',
            get_string('inviteexpiry', 'tool_flexaccess'),
            ['optional' => true]
        );
        $mform->addHelpButton('expiry', 'inviteexpiry', 'tool_flexaccess');

        $mform->addElement('advcheckbox', 'sendnow', get_string('invitesendnow', 'tool_flexaccess'));
        $mform->setDefault('sendnow', 1);

        $this->add_action_buttons(true, get_string('invitecreate', 'tool_flexaccess'));
    }

    /**
     * Validate that at least one syntactically valid email was supplied.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $seen = [];
        $valid = 0;
        $invalid = [];
        foreach (preg_split('/[\s,;]+/', (string) ($data['emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $raw) {
            $email = \core_text::strtolower(trim($raw));
            if (!validate_email($email)) {
                $invalid[$email] = true;
                continue;
            }
            // Duplicates are de-duplicated silently at creation; only invalid addresses are blocking.
            if (!isset($seen[$email])) {
                $seen[$email] = true;
                $valid++;
            }
        }
        if ($valid === 0) {
            $errors['emails'] = get_string('invitenoemails', 'tool_flexaccess');
        } else if ($invalid !== []) {
            // Do not silently drop invalid addresses when some are valid: report them for correction.
            $errors['emails'] = get_string('inviteinvalidemails', 'tool_flexaccess', implode(', ', array_keys($invalid)));
        }
        return $errors;
    }
}
