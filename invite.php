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
 * Public acceptance page for a person-bound FlexAccess invitation.
 *
 * The recipient arrives via a single-use tokenised link. The invitation's own email address is used
 * for the registration (the invite is person-bound), and the invitation is consumed only once the
 * account is successfully created.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

use tool_flexaccess\local\invitation;

$token = required_param('token', PARAM_ALPHANUM);

$invite = invitation::get_by_token($token);
$course = $invite ? $DB->get_record('course', ['id' => $invite->courseid]) : false;
$usable = $invite
    && $course
    && (int) $course->visible === 1
    && invitation::is_acceptable($invite)
    && \enrol_flexaccess\api::offers_quick_registration((int) $invite->courseid);

if (!$usable) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/admin/tool/flexaccess/invite.php'));
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(get_string('invitetitle', 'tool_flexaccess'));
    $PAGE->set_heading(get_string('invitetitle', 'tool_flexaccess'));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('invitetitle', 'tool_flexaccess'));
    echo $OUTPUT->notification(get_string('inviteunavailable', 'tool_flexaccess'), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/login/index.php'));
    echo $OUTPUT->footer();
    exit;
}

$courseurl = new moodle_url('/course/view.php', ['id' => $invite->courseid]);

$PAGE->set_context(context_course::instance((int) $invite->courseid));
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/invite.php', ['token' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('invitetitle', 'tool_flexaccess'));
$PAGE->set_heading(format_string($course->fullname));

if (isloggedin() && !isguestuser()) {
    redirect($courseurl);
}

$form = new \auth_flexaccess\form\quick_registration_form(
    new moodle_url('/admin/tool/flexaccess/invite.php', ['token' => $token]),
    ['courseid' => (int) $invite->courseid, 'wantsurl' => '', 'gatemode' => 'none', 'lockemail' => $invite->email]
);
$form->set_data(['email' => $invite->email]);

$failure = null;
if ($form->is_cancelled()) {
    redirect($courseurl);
} else if ($data = $form->get_data()) {
    // Reserve the invitation first so concurrent attempts cannot use it, but do NOT consume it yet:
    // it is only marked accepted once registration succeeds. On any failure it returns to pending so
    // the recipient can retry with the same single-use invitation.
    $reserved = invitation::reserve($token);
    if ($reserved === null) {
        $failure = 'inviteunavailable';
    } else {
        $result = \enrol_flexaccess\local\access_controller::grant_quick_registration((int) $invite->courseid, (object) [
            'email' => $invite->email,
            'firstname' => $data->firstname,
            'lastname' => $data->lastname,
            'password' => $data->password,
            'accesspassword' => '',
        ], getremoteaddr(), null, true);
        if ($result->status === 'granted' || $result->status === 'verificationsent') {
            invitation::commit_acceptance((int) $reserved->id);
            $user = $DB->get_record('user', ['id' => $result->userid], '*', MUST_EXIST);
            complete_user_login($user);
            $message = $result->status === 'verificationsent'
                ? get_string('registerverificationsent', 'auth_flexaccess')
                : get_string('registersuccess', 'auth_flexaccess');
            redirect($courseurl, $message);
        }
        // Registration failed: hand the invitation back so it can be used again.
        invitation::release_reservation((int) $reserved->id);
        $failure = 'access' . $result->status;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname));
if ($failure !== null) {
    $component = str_starts_with($failure, 'access') ? 'auth_flexaccess' : 'tool_flexaccess';
    echo $OUTPUT->notification(get_string($failure, $component), 'error');
}
echo html_writer::tag('p', get_string('inviteintro', 'tool_flexaccess', s($invite->email)));
$form->display();
echo $OUTPUT->footer();
