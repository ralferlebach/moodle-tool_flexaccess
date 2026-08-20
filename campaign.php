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
 * Public landing page for a FlexAccess invitation campaign.
 *
 * Validates the campaign token and its gate, then lets the visitor self-register into the campaign's
 * course through the FlexAccess quick-registration flow. A redemption is only recorded once an
 * account is successfully created.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

use tool_flexaccess\local\campaign;

$token = required_param('token', PARAM_ALPHANUM);

$campaign = campaign::get_by_token($token);
$course = $campaign ? $DB->get_record('course', ['id' => $campaign->courseid]) : false;
$usable = $campaign
    && $course
    && (int) $course->visible === 1
    && campaign::is_redeemable($campaign)
    && \enrol_flexaccess\api::offers_quick_registration((int) $campaign->courseid);

if (!$usable) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/admin/tool/flexaccess/campaign.php'));
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(get_string('campaigninvitation', 'tool_flexaccess'));
    $PAGE->set_heading(get_string('campaigninvitation', 'tool_flexaccess'));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('campaigninvitation', 'tool_flexaccess'));
    echo $OUTPUT->notification(get_string('campaignunavailable', 'tool_flexaccess'), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/login/index.php'));
    echo $OUTPUT->footer();
    exit;
}

$courseurl = new moodle_url('/course/view.php', ['id' => $campaign->courseid]);

$PAGE->set_context(context_course::instance((int) $campaign->courseid));
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/campaign.php', ['token' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('campaigninvitation', 'tool_flexaccess'));
$PAGE->set_heading(format_string($course->fullname));

if (isloggedin() && !isguestuser()) {
    redirect($courseurl);
}

$form = new \auth_flexaccess\form\quick_registration_form(
    new moodle_url('/admin/tool/flexaccess/campaign.php', ['token' => $token]),
    ['courseid' => (int) $campaign->courseid, 'wantsurl' => '', 'gatemode' => $campaign->gatemode]
);

$failure = null;
if ($form->is_cancelled()) {
    redirect($courseurl);
} else if ($data = $form->get_data()) {
    $accesspassword = (string) ($data->accesspassword ?? '');
    if (!campaign::passes_gate($campaign, (string) $data->email, $accesspassword)) {
        $failure = 'campaignbadgate';
    } else {
        $result = \enrol_flexaccess\local\access_controller::grant_quick_registration((int) $campaign->courseid, (object) [
            'email' => $data->email,
            'firstname' => $data->firstname,
            'lastname' => $data->lastname,
            'password' => $data->password,
            'accesspassword' => '',
        ], getremoteaddr());
        if ($result->status === 'granted' || $result->status === 'verificationsent') {
            // Record the redemption only for a successful sign-up.
            campaign::redeem((int) $campaign->id);
            $user = $DB->get_record('user', ['id' => $result->userid], '*', MUST_EXIST);
            complete_user_login($user);
            $message = $result->status === 'verificationsent'
                ? get_string('register:verificationsent', 'auth_flexaccess')
                : get_string('register:success', 'auth_flexaccess');
            redirect($courseurl, $message);
        }
        $failure = 'access:' . $result->status;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($campaign->name));
if ($failure !== null) {
    if (str_starts_with($failure, 'access:')) {
        echo $OUTPUT->notification(get_string($failure, 'auth_flexaccess'), 'error');
    } else {
        echo $OUTPUT->notification(get_string($failure, 'tool_flexaccess'), 'error');
    }
}
echo html_writer::tag('p', get_string('campaignintro', 'tool_flexaccess', format_string($course->fullname)));
$form->display();
echo $OUTPUT->footer();
