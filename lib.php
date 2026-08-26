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
 * Add a course-level entry point to the teacher-facing access-list batch manager.
 *
 * Shown only to users who may manage batches in the course (a course teacher holding
 * tool/flexaccess:managecoursebatches, or a site manager). Lets teachers create and download
 * anonymous access lists without leaving the course.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 * @return void
 */
function tool_flexaccess_extend_navigation_course($navigation, $course, $context) {
    if (!\tool_flexaccess\local\batch::can_request((int) $course->id)) {
        return;
    }
    $navigation->add(
        get_string('coursebatches', 'tool_flexaccess'),
        new moodle_url('/admin/tool/flexaccess/coursebatches.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'flexaccesscoursebatches',
        new pix_icon('i/users', '')
    );
}
