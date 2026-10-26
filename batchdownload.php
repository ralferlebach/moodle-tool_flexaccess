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
 * Issue a batch's credentials (rotating passwords) and stream them as a ZIP of the spreadsheet, the PDF
 * list and the login cards. Issuing is an explicit, confirmed POST: it rotates passwords, so any
 * previously issued credentials become invalid. The package is built and verified completely before a
 * single password changes, and all passwords change together or not at all (tool_flexaccess #15, see
 * \tool_flexaccess\local\credential_issue). Plain passwords are never persisted.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');

use tool_flexaccess\local\batch;
use tool_flexaccess\local\credential_issue;
use tool_flexaccess\local\request;

require_login();

$id = required_param('id', PARAM_INT);
// Only a POST may rotate credentials; a GET renders the confirmation instead.
$confirm = (optional_param('confirm', 0, PARAM_BOOL) && request::is_post());

$batch = batch::get($id);
if (!$batch) {
    throw new moodle_exception('invalidrecord', 'error');
}
$courseid = (int) $batch->courseid;

// Issuing credentials rotates every (still batch-managed) member's password, so it is a distinct,
// higher-risk right and an explicit, confirmed action - never a silent side effect of a download.
batch::require_issue($courseid);

// Issued once. A repeat issue rotates every password and therefore invalidates the package that was
// already handed out, so it is reserved for site administrators who can judge that consequence.
$reissue = has_capability('tool/flexaccess:managebatches', context_system::instance());
if ((int) $batch->timeissued > 0 && !$reissue) {
    throw new moodle_exception('batchalreadyissued', 'tool_flexaccess');
}

// A batch that is still being provisioned has no complete member set to issue credentials for.
if (($batch->status ?? batch::STATUS_COMPLETE) !== batch::STATUS_COMPLETE) {
    throw new moodle_exception('batchnotreadyyet', 'tool_flexaccess');
}

$downloadurl = new moodle_url('/admin/tool/flexaccess/batchdownload.php', ['id' => $id]);
$issuer = \core\di::get(credential_issue::class);

if (!$confirm) {
    $plan = $issuer->plan($id);
    $context = context_course::instance($courseid);
    $PAGE->set_context($context);
    $PAGE->set_url($downloadurl);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('batchissuecredentials', 'tool_flexaccess'));
    $PAGE->set_heading(format_string($batch->name));
    echo $OUTPUT->header();
    echo \tool_flexaccess\local\navigation::render_accesslist($courseid);
    // What exactly will be issued - nothing is left out without being named here.
    $summary = html_writer::tag('p', get_string('batchissueplan', 'tool_flexaccess', count($plan->issue)));
    if ($plan->personalised) {
        $summary .= html_writer::tag('p', get_string('batchissueplanpersonalised', 'tool_flexaccess', count($plan->personalised)));
    }
    if ($plan->unavailable) {
        $summary .= html_writer::tag('p', s(get_string(
            'batchissueplanunavailable',
            'tool_flexaccess',
            implode(', ', $plan->unavailable)
        )));
    }
    echo $OUTPUT->notification($summary, \core\output\notification::NOTIFY_INFO, false);
    // Issuing rotates passwords, so it must be a POST: a state-changing GET can be triggered by a
    // prefetch, a crawler or a pasted link and would silently invalidate handed-out credentials.
    // single_button renders the URL parameters as hidden fields and adds the sesskey itself. The token
    // binds the confirmation to this state: a repeated or outdated submission is refused.
    $continue = new single_button(
        new moodle_url($downloadurl, ['confirm' => 1, 'token' => $plan->token]),
        get_string('continue'),
        'post'
    );
    echo $OUTPUT->confirm(
        get_string('batchissueconfirm', 'tool_flexaccess'),
        $continue,
        new moodle_url('/admin/tool/flexaccess/batches.php', ['action' => 'view', 'id' => $id])
    );
    echo $OUTPUT->footer();
    exit;
}

request::require_post();
require_sesskey();
$token = required_param('token', PARAM_ALPHANUM);

$courseurl = (new moodle_url('/course/view.php', ['id' => $batch->courseid]))->out(false);
// Verify, prepare, build and check every file, then change the passwords - or refuse with nothing changed.
$package = $issuer->issue($id, $token, $reissue, make_request_directory(), $courseurl);
send_file($package['zip'], $package['filename'], 0, 0, false, true, 'application/zip');
