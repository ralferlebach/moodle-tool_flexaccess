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

/**
 * Library callbacks for tool_flexaccess.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Add the course-level FlexAccess entry (Course > More > FlexAccess).
 *
 * One entry for the course-scoped FlexAccess management; its tabs (users, access lists,
 * restrictions) are filtered by capability, so the entry is shown as soon as at least one of them is
 * available to the current user.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 * @return void
 */
function tool_flexaccess_extend_navigation_course($navigation, $course, $context) {
    $tabs = \tool_flexaccess\local\navigation::course_tabs((int) $course->id);
    if (!$tabs) {
        return;
    }
    $navigation->add(
        get_string('coursemanagement', 'tool_flexaccess'),
        reset($tabs)['url'],
        navigation_node::TYPE_SETTING,
        null,
        'flexaccess',
        new pix_icon('i/settings', '')
    );
}
