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

namespace tool_flexaccess\local;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Round-trip conversion of batch accounts from a filled-in export spreadsheet (campaign, part 2).
 *
 * The administrator downloads the batch export, fills in first name, last name, email (and
 * optionally a new username) per account, and re-uploads it here. Each matched account is
 * personalised and converted into a full, permanent account, and a set-password email is queued to
 * the real address (the email-confirmation step). The username is taken from the sheet when given,
 * otherwise derived from a chosen rule.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch_import {
    /** Member table. */
    private const MEMBER_TABLE = 'tool_flexaccess_batch_member';

    /** Username rule: use the email address. */
    public const RULE_EMAIL = 'email';

    /** Username rule: keep the original batch username. */
    public const RULE_KEEP = 'keep';

    /** Username rule: local part of the email (before the @). */
    public const RULE_EMAILLOCAL = 'emaillocal';

    /** Username rule: firstname.lastname. */
    public const RULE_FIRSTLAST = 'firstlast';

    /** Maximum accepted import file size (2 MiB) - guards the XLSX reader (P0-4). */
    public const MAX_IMPORT_BYTES = 2097152;

    /** Rows converted before progress is written back; keeps each unit of work small. */
    public const CONVERT_CHUNK = 25;

    /** Above this many rows, conversion is handed to an ad-hoc task instead of the web request. */
    public const MAX_SYNC_CONVERT = 100;

    /** Schema marker written to H1 by the exporter; the importer accepts only this format. */
    public const SCHEMA_VERSION = 'FLEXACCESS-BATCH-V1';

    /** Maximum data rows read from an import file, bounding the row iterator (P0-4). */
    public const MAX_IMPORT_ROWS = 2000;

    /**
     * The selectable username rules, for a form select.
     *
     * @return array<string,string> rule => localised label.
     */
    public static function rules(): array {
        return [
            self::RULE_EMAIL => get_string('batchrule_email', 'tool_flexaccess'),
            self::RULE_KEEP => get_string('batchrule_keep', 'tool_flexaccess'),
            self::RULE_EMAILLOCAL => get_string('batchrule_emaillocal', 'tool_flexaccess'),
            self::RULE_FIRSTLAST => get_string('batchrule_firstlast', 'tool_flexaccess'),
        ];
    }

    /**
     * Parse a filled export spreadsheet into rows.
     *
     * Columns follow the export layout: A username (match key), C first name, D last name, E email,
     * G optional new username. The header row is skipped.
     *
     * Hardened against the PhpSpreadsheet unbounded-row-dimension CPU DoS (CVE-2026-40902): the file
     * size is capped, an explicit read-only Xlsx reader is used (no format sniffing), and the row
     * iteration is bounded to a fixed window regardless of the sheet's declared dimensions (P0-4).
     *
     * @param string $filepath Absolute path to the uploaded .xlsx file.
     * @return array<int,array{username:string,firstname:string,lastname:string,email:string,newusername:string}>
     */
    public static function parse(string $filepath): array {
        if (!is_readable($filepath)) {
            throw new \moodle_exception('batchimportunreadable', 'tool_flexaccess');
        }
        if (filesize($filepath) > self::MAX_IMPORT_BYTES) {
            throw new \moodle_exception(
                'batchimporttoolarge',
                'tool_flexaccess',
                '',
                (object) ['limit' => display_size(self::MAX_IMPORT_BYTES)]
            );
        }
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        try {
            $spreadsheet = $reader->load($filepath);
        } catch (\Throwable $e) {
            // A corrupt or non-XLSX payload must fail with a clear message, not a raw library error.
            throw new \moodle_exception('batchimportunreadable', 'tool_flexaccess');
        }
        $sheet = $spreadsheet->getActiveSheet();

        // Strict schema check: the export writes a machine-readable marker, so an arbitrary or
        // hand-built spreadsheet is rejected rather than silently misinterpreted column by column.
        $marker = trim((string) $sheet->getCell('H1')->getValue());
        if ($marker !== self::SCHEMA_VERSION) {
            throw new \moodle_exception(
                'batchimportbadschema',
                'tool_flexaccess',
                '',
                (object) ['expected' => self::SCHEMA_VERSION]
            );
        }

        $rows = [];
        $first = true;
        // One row beyond the limit is read on purpose: if it carries data the file is too big, and
        // the import is refused instead of silently dropping records.
        foreach ($sheet->getRowIterator(1, self::MAX_IMPORT_ROWS + 2) as $row) {
            if ($first) {
                $first = false;
                continue;
            }
            $cells = [];
            $it = $row->getCellIterator('A', 'G');
            $it->setIterateOnlyExistingCells(false);
            foreach ($it as $cell) {
                $cells[$cell->getColumn()] = trim((string) $cell->getValue());
            }
            $username = \core_text::strtolower($cells['A'] ?? '');
            if ($username === '') {
                continue;
            }
            if (count($rows) >= self::MAX_IMPORT_ROWS) {
                throw new \moodle_exception(
                    'batchimporttoomanyrows',
                    'tool_flexaccess',
                    '',
                    (object) ['max' => self::MAX_IMPORT_ROWS]
                );
            }
            $rows[] = [
                'username' => $username,
                'firstname' => $cells['C'] ?? '',
                'lastname' => $cells['D'] ?? '',
                'email' => \core_text::strtolower($cells['E'] ?? ''),
                'newusername' => \core_text::strtolower($cells['G'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * Record on the member itself why its conversion attempt failed.
     *
     * Aggregate counts do not tell an administrator which of 2000 rows went wrong; the reason is
     * therefore attached to the member and survives the request.
     *
     * @param \stdClass $member Batch member record.
     * @param string $message Human-readable reason.
     * @return void
     */
    private static function record_member_error(\stdClass $member, string $message): void {
        global $DB;
        $DB->set_field(
            self::MEMBER_TABLE,
            'converterror',
            \core_text::substr($message, 0, 255),
            ['id' => $member->id]
        );
    }

    /**
     * Convert an import, synchronously for small files and via an ad-hoc task for large ones.
     *
     * Per row the conversion performs several database, identity and mail operations, so a large
     * import must not sit in a web request. Callers get an immediate answer either way.
     *
     * @param int $batchid Batch id.
     * @param array $rows Parsed import rows.
     * @param string $usernamerule Username rule to apply.
     * @param int|null $now Current time.
     * @return array{queued:bool, converted:int, skipped:int, errors:array<string>}
     */
    public static function convert_dispatch(
        int $batchid,
        array $rows,
        string $usernamerule = self::RULE_EMAIL,
        ?int $now = null
    ): array {
        if (count($rows) <= self::MAX_SYNC_CONVERT) {
            return ['queued' => false] + self::convert($batchid, $rows, $usernamerule, $now);
        }
        batch::mark_converting($batchid, count($rows));
        $task = new \tool_flexaccess\task\convert_batch();
        $task->set_custom_data([
            'batchid' => $batchid,
            'rows' => $rows,
            'usernamerule' => $usernamerule,
        ]);
        \core\task\manager::queue_adhoc_task($task);
        return ['queued' => true, 'converted' => 0, 'skipped' => 0, 'errors' => []];
    }

    /**
     * Convert the accounts described by $rows to permanent accounts with email confirmation.
     *
     * @param int $batchid Batch whose members these rows belong to.
     * @param array $rows Parsed rows (each: username, firstname, lastname, email, newusername).
     * @param string $usernamerule One of the RULE_* constants (used when a row has no new username).
     * @param int|null $now Current time.
     * @return array{converted:int, skipped:int, errors:array<int,string>} Summary; errors are human lines.
     */
    public static function convert(
        int $batchid,
        array $rows,
        string $usernamerule = self::RULE_EMAIL,
        ?int $now = null
    ): array {
        global $DB;
        $now = $now ?? time();
        $converted = 0;
        $skipped = 0;
        $errors = [];

        // Load the batch's members once and index them by username, instead of a query per row.
        $members = [];
        foreach ($DB->get_records(self::MEMBER_TABLE, ['batchid' => $batchid]) as $record) {
            $members[$record->username] = $record;
        }
        batch::mark_converting($batchid, count($rows));
        $processed = 0;

        foreach ($rows as $row) {
            $processed++;
            if ($processed % self::CONVERT_CHUNK === 0) {
                // Progress counts PROCESSED rows, not just successful ones: an import where most
                // rows are skipped would otherwise appear stuck at a low number while it is in fact
                // nearly finished.
                batch::report_convert_progress($batchid, $processed);
            }
            $username = $row['username'];
            $member = $members[$username] ?? null;
            if ($member && !empty($member->converted)) {
                // Already converted (e.g. this import is being retried): skip silently so no second
                // identity transition happens and no duplicate set-password mail goes out.
                $skipped++;
                continue;
            }
            if (!$member) {
                $skipped++;
                $errors[] = get_string('batcherr_notfound', 'tool_flexaccess', $username);
                continue;
            }
            $email = trim($row['email']);
            if ($email === '') {
                $skipped++;
                $message = get_string('batcherr_noemail', 'tool_flexaccess', $username);
                $errors[] = $message;
                self::record_member_error($member, $message);
                continue;
            }
            $userid = (int) $member->userid;

            // Convert + personalise + queue the set-password (confirmation) email. This sets the
            // username to the email; a chosen username is applied immediately afterwards.
            $status = \auth_flexaccess\api::admin_convert($userid, $email, $row['firstname'], $row['lastname'], $now);
            if ($status !== 'converted') {
                $skipped++;
                $message = get_string(
                    'batcherr_convert',
                    'tool_flexaccess',
                    (object) ['username' => $username, 'status' => $status]
                );
                $errors[] = $message;
                self::record_member_error($member, $message);
                continue;
            }

            $target = self::target_username($row, $usernamerule);
            if ($target !== '' && $target !== $email) {
                if (\auth_flexaccess\api::rename_username($userid, $target)) {
                    $DB->set_field(self::MEMBER_TABLE, 'username', $target, ['id' => $member->id]);
                } else {
                    // Conversion succeeded; only the rename failed (username taken/invalid).
                    $message = get_string(
                        'batcherr_rename',
                        'tool_flexaccess',
                        (object) ['username' => $username, 'target' => $target]
                    );
                    $errors[] = $message;
                    self::record_member_error($member, $message);
                    $DB->set_field(self::MEMBER_TABLE, 'username', $email, ['id' => $member->id]);
                }
            } else {
                $DB->set_field(self::MEMBER_TABLE, 'username', $email, ['id' => $member->id]);
            }
            // Mark the member as no longer batch-managed: a later credential re-issue must never
            // touch this now-personalised, permanent account (P0-1).
            $DB->set_field(self::MEMBER_TABLE, 'converted', 1, ['id' => $member->id]);
            $DB->set_field(self::MEMBER_TABLE, 'converterror', null, ['id' => $member->id]);
            $converted++;
        }

        batch::finish_converting($batchid, $converted, $skipped);
        return ['converted' => $converted, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Decide the target username for a row: an explicit sheet value wins, else apply the rule.
     *
     * @param array $row Parsed row.
     * @param string $rule One of the RULE_* constants.
     * @return string Desired username ('' to leave as the email).
     */
    private static function target_username(array $row, string $rule): string {
        $explicit = trim($row['newusername'] ?? '');
        if ($explicit !== '') {
            return $explicit;
        }
        $email = trim($row['email']);
        switch ($rule) {
            case self::RULE_KEEP:
                return trim($row['username']);
            case self::RULE_EMAILLOCAL:
                $at = strpos($email, '@');
                return $at !== false ? substr($email, 0, $at) : $email;
            case self::RULE_FIRSTLAST:
                $first = \core_text::strtolower(trim($row['firstname']));
                $last = \core_text::strtolower(trim($row['lastname']));
                $joined = trim($first . '.' . $last, '.');
                return $joined !== '' ? $joined : '';
            case self::RULE_EMAIL:
            default:
                return '';
        }
    }
}
