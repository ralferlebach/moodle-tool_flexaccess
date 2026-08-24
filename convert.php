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
 * Administrative conversion page for tool_flexaccess.
 *
 * Converts a temporary FlexAccess account into a re-loginnable authenticated account: the
 * administrator supplies the person's real email, and the auth facade rewrites the identity and
 * mails a set-password link. All domain mutations go through the facade.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$userid = required_param('userid', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:convertaccounts', $context);

$accountsurl = new moodle_url('/admin/tool/flexaccess/accounts.php');
$pageurl = new moodle_url('/admin/tool/flexaccess/convert.php', ['userid' => $userid]);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('convertheading', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$account = $DB->get_record('auth_flexaccess_account', ['userid' => $userid]);
if (!$account || $account->accounttype !== \auth_flexaccess\local\account_type::TEMPORARY_USER) {
    redirect(
        $accountsurl,
        get_string('accountnotconverted', 'tool_flexaccess'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

$user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname', MUST_EXIST);
$form = new \tool_flexaccess\form\convert_form($pageurl->out(false), ['userid' => $userid]);
$form->set_data(['firstname' => $user->firstname, 'lastname' => $user->lastname]);

if ($form->is_cancelled()) {
    redirect($accountsurl);
}

if ($data = $form->get_data()) {
    $status = \auth_flexaccess\api::admin_convert(
        $userid,
        (string) $data->email,
        (string) ($data->firstname ?? ''),
        (string) ($data->lastname ?? '')
    );
    if ($status === 'converted') {
        redirect(
            $accountsurl,
            get_string('accountconverted', 'tool_flexaccess'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(
        $pageurl,
        get_string('convertstatus:' . $status, 'tool_flexaccess'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('convertheading', 'tool_flexaccess'));
$form->display();
echo $OUTPUT->footer();
