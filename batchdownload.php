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
 * Issue a batch's credentials (rotating passwords) and stream them as XLSX, PDF list, login cards,
 * or a ZIP of all three. Issuing is an explicit, confirmed action: it rotates passwords, so any
 * previously issued credentials become invalid. Plain passwords are never persisted, so every file
 * from a single confirmed issue shares one consistent, one-time credential set.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\batch;
use tool_flexaccess\local\batch_export;

require_login();

$id = required_param('id', PARAM_INT);
// Only a POST may rotate credentials; a GET renders the confirmation instead.
$confirm = (optional_param('confirm', 0, PARAM_BOOL) && $_SERVER['REQUEST_METHOD'] === 'POST');

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

if (!$confirm) {
    $context = context_course::instance($courseid);
    $PAGE->set_context($context);
    $PAGE->set_url($downloadurl);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('batchissuecredentials', 'tool_flexaccess'));
    $PAGE->set_heading(format_string($batch->name));
    echo $OUTPUT->header();
    // Issuing rotates passwords, so it must be a POST: a state-changing GET can be triggered by a
    // prefetch, a crawler or a pasted link and would silently invalidate handed-out credentials.
    // single_button renders the URL parameters as hidden fields and adds the sesskey itself.
    $continue = new single_button(
        new moodle_url($downloadurl, ['confirm' => 1]),
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

require_sesskey();

// Fresh passwords for a consistent credential set across every generated file. Members that have
// been personalised/converted are skipped by reset_credentials() and keep their password.
$credentials = batch::reset_credentials($id);
$DB->set_field('tool_flexaccess_batch', 'timeissued', time(), ['id' => $id]);
$courseurl = (new moodle_url('/course/view.php', ['id' => $batch->courseid]))->out(false);
$base = clean_filename(format_string($batch->name)) ?: ('batch' . $id);

// Only the complete package is offered: issuing rotates every password, so a second, separate
// download would silently invalidate the files handed out first.
// Bundle all three into a ZIP built from one credential set.
$tempdir = make_request_directory();
$files = [
    $base . '.xlsx' => $tempdir . '/' . $base . '.xlsx',
    $base . '-liste.pdf' => $tempdir . '/' . $base . '-liste.pdf',
    $base . '-kaertchen.pdf' => $tempdir . '/' . $base . '-kaertchen.pdf',
];
file_put_contents($files[$base . '.xlsx'], batch_export::excel($batch, $credentials));
file_put_contents($files[$base . '-liste.pdf'], batch_export::pdf_list($batch, $credentials));
file_put_contents(
    $files[$base . '-kaertchen.pdf'],
    batch_export::login_cards($batch, $credentials, $courseurl, (string) ($batch->cardtext ?? ''))
);

$zippath = $tempdir . '/' . $base . '.zip';
get_file_packer('application/zip')->archive_to_pathname($files, $zippath);
send_file($zippath, $base . '.zip', 0, 0, false, true, 'application/zip');
