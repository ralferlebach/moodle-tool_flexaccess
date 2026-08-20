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
 * Reset a batch's credentials and stream them as XLSX, PDF list, login cards, or a ZIP of all three.
 *
 * A download always issues fresh passwords (plain passwords are never persisted), so all files from
 * a single request share one consistent credential set.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\batch;
use tool_flexaccess\local\batch_export;

require_login();
require_capability('tool/flexaccess:managebatches', context_system::instance());
require_sesskey();

$id = required_param('id', PARAM_INT);
$format = optional_param('format', 'all', PARAM_ALPHA);

$batch = batch::get($id);
if (!$batch) {
    throw new moodle_exception('invalidrecord', 'error');
}

// Fresh passwords for a consistent credential set across every generated file.
$credentials = batch::reset_credentials($id, 10);
$courseurl = (new moodle_url('/course/view.php', ['id' => $batch->courseid]))->out(false);
$base = clean_filename(format_string($batch->name)) ?: ('batch' . $id);

if ($format === 'excel') {
    send_file(
        batch_export::excel($batch, $credentials),
        $base . '.xlsx',
        0,
        0,
        true,
        true,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
} else if ($format === 'pdflist') {
    send_file(
        batch_export::pdf_list($batch, $credentials),
        $base . '-liste.pdf',
        0,
        0,
        true,
        true,
        'application/pdf'
    );
} else if ($format === 'cards') {
    send_file(
        batch_export::login_cards($batch, $credentials, $courseurl),
        $base . '-kaertchen.pdf',
        0,
        0,
        true,
        true,
        'application/pdf'
    );
} else {
    // Bundle all three into a ZIP built from one credential set.
    $tempdir = make_request_directory();
    $files = [
        $base . '.xlsx' => $tempdir . '/' . $base . '.xlsx',
        $base . '-liste.pdf' => $tempdir . '/' . $base . '-liste.pdf',
        $base . '-kaertchen.pdf' => $tempdir . '/' . $base . '-kaertchen.pdf',
    ];
    file_put_contents($files[$base . '.xlsx'], batch_export::excel($batch, $credentials));
    file_put_contents($files[$base . '-liste.pdf'], batch_export::pdf_list($batch, $credentials));
    file_put_contents($files[$base . '-kaertchen.pdf'], batch_export::login_cards($batch, $credentials, $courseurl));

    $zippath = $tempdir . '/' . $base . '.zip';
    get_file_packer('application/zip')->archive_to_pathname($files, $zippath);
    send_file($zippath, $base . '.zip', 0, 0, false, true, 'application/zip');
}
