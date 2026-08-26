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
 * Administrative policies diagnostics page for tool_flexaccess.
 *
 * Read-only. Consumes the enrol_flexaccess public facade at runtime to show the effective
 * policy for a course. Never displays secret values or hashes.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:viewpolicies', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/policies.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('policies', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('policies', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('policiesintro', 'tool_flexaccess'));

if ($courseid > 0 && class_exists('\\enrol_flexaccess\\api')) {
    $course = get_course($courseid);
    $enabled = \enrol_flexaccess\api::is_target_enabled($courseid);
    $policy = \enrol_flexaccess\api::get_effective_policy($courseid);
    $summary = \tool_flexaccess\local\policy_presenter::summarise($policy, $enabled);

    $yesno = fn(bool $v): string => $v ? get_string('yes') : get_string('no');
    $time = fn(int $t): string => $t > 0 ? userdate($t) : get_string('policyunbounded', 'tool_flexaccess');
    $cap = $summary['maxparticipants'] > 0
        ? (string) $summary['maxparticipants']
        : get_string('policyunlimited', 'tool_flexaccess');

    $rows = [
        [get_string('policytargetenabled', 'tool_flexaccess'), $yesno($summary['targetenabled'])],
        [get_string('policyallowtemporary', 'tool_flexaccess'), $yesno($summary['allowtemporary'])],
        [get_string('policyallowquick', 'tool_flexaccess'), $yesno($summary['allowquick'])],
        [get_string('policyallowguest', 'tool_flexaccess'), $yesno($summary['allowguest'])],
        [get_string('policyallownormallogin', 'tool_flexaccess'), $yesno($summary['allownormallogin'])],
        [get_string('policyavailablefrom', 'tool_flexaccess'), $time($summary['availablefrom'])],
        [get_string('policyavailableuntil', 'tool_flexaccess'), $time($summary['availableuntil'])],
        [get_string('policymaxparticipants', 'tool_flexaccess'), $cap],
        [
            get_string('policyvisibility', 'tool_flexaccess'),
            s(\tool_flexaccess\local\policy_presenter::visibility_label($summary['participantlistaccess'])),
        ],
        [get_string('policyaccesskeyscope', 'tool_flexaccess'), s($summary['accesskeyscope'])],
    ];

    $table = new html_table();
    $table->head = [get_string('policyproperty', 'tool_flexaccess'), get_string('policyvalue', 'tool_flexaccess')];
    $table->caption = format_string($course->fullname);
    $table->data = $rows;
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('policiesnocourse', 'tool_flexaccess'), 'info');
}

echo $OUTPUT->footer();
