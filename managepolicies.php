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
 * Administrative page to manage category-level FlexAccess policy overrides.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:managepolicies', $context);

$categoryid = optional_param('categoryid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/managepolicies.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managepolicies', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$returnurl = new moodle_url('/admin/tool/flexaccess/managepolicies.php');
$categories = \core_course_category::make_categories_list('moodle/category:manage');

$form = new \tool_flexaccess\form\category_policy_form($returnurl, ['categories' => $categories]);

if ($existing = ($categoryid > 0 ? \enrol_flexaccess\local\category_policy::load($categoryid) : null)) {
    $form->set_data([
        'categoryid' => $categoryid,
        'allowtemporary' => (int) $existing->allowtemporary,
        'allowquick' => (int) $existing->allowquick,
        'allowguest' => (int) $existing->allowguest,
        'allownormallogin' => (int) $existing->allownormallogin,
        'temporarylifetime' => (int) ($existing->temporarylifetime ?? 0),
        'provisionallifetime' => (int) ($existing->provisionallifetime ?? 0),
        'participantvisibility' => $existing->participantvisibility,
    ]);
} else if ($categoryid > 0) {
    $form->set_data(['categoryid' => $categoryid]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if (optional_param('delete', 0, PARAM_INT) === 1 && $categoryid > 0 && confirm_sesskey()) {
    \enrol_flexaccess\local\category_policy::delete($categoryid);
    redirect($returnurl, get_string('policydeleted', 'tool_flexaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($data = $form->get_data()) {
    \enrol_flexaccess\local\category_policy::save((int) $data->categoryid, [
        'allowtemporary' => $data->allowtemporary,
        'allowquick' => $data->allowquick,
        'allowguest' => $data->allowguest,
        'allownormallogin' => $data->allownormallogin,
        'temporarylifetime' => $data->temporarylifetime,
        'provisionallifetime' => $data->provisionallifetime,
        'participantvisibility' => $data->participantvisibility,
    ]);
    redirect($returnurl, get_string('policysaved', 'tool_flexaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepolicies', 'tool_flexaccess'));
echo html_writer::tag('p', get_string('managepolicies_intro', 'tool_flexaccess'));

$overrides = \enrol_flexaccess\local\category_policy::all();
if ($overrides) {
    $flagword = function (int $value): string {
        if ($value === 1) {
            return get_string('policyallow', 'tool_flexaccess');
        }
        if ($value === 0) {
            return get_string('policydeny', 'tool_flexaccess');
        }
        return get_string('policyinherit', 'tool_flexaccess');
    };
    $table = new html_table();
    $table->head = [
        get_string('policycategory', 'tool_flexaccess'),
        get_string('policyallowtemporary', 'tool_flexaccess'),
        get_string('policyallowquick', 'tool_flexaccess'),
        get_string('policyallowguest', 'tool_flexaccess'),
        get_string('policyvisibility', 'tool_flexaccess'),
        '',
    ];
    foreach ($overrides as $catid => $row) {
        $name = $categories[$catid] ?? ('#' . $catid);
        $editurl = new moodle_url($returnurl, ['categoryid' => $catid]);
        $deleteurl = new moodle_url($returnurl, ['categoryid' => $catid, 'delete' => 1, 'sesskey' => sesskey()]);
        $actions = html_writer::link($editurl, get_string('edit'))
            . ' · '
            . html_writer::link($deleteurl, get_string('delete'));
        $table->data[] = [
            format_string($name),
            $flagword((int) $row->allowtemporary),
            $flagword((int) $row->allowquick),
            $flagword((int) $row->allowguest),
            s($row->participantvisibility),
            $actions,
        ];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('managepolicies_none', 'tool_flexaccess'));
}

echo $OUTPUT->heading(get_string('managepolicies_edit', 'tool_flexaccess'), 3);
$form->display();
echo $OUTPUT->footer();
