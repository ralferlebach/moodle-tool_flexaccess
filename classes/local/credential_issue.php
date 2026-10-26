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

/**
 * Issuing the credential package of a batch (tool_flexaccess #15).
 *
 * Issuing rotates passwords, so it runs in phases and changes nothing until everything else has
 * succeeded:
 *
 * 1. Verify the members, under a per-batch lock: the set of accounts to issue must still be the set
 *    the operator confirmed (a token over the batch state and that set). A repeated or concurrent
 *    request, a member removed or converted in the meantime, or a batch that is no longer complete
 *    is refused.
 * 2. Prepare the credentials in memory only - plain passwords are never stored.
 * 3. Build the spreadsheet, the list and the login cards from that one credential set, write them,
 *    and verify every write, the content of each file and the archive.
 * 4. Persist: change all passwords and record the issue in one transaction. A single failed change
 *    rolls everything back; there is no partial package and no account with a password nobody has.
 *
 * Accounts that are not issued are never dropped silently: personalised accounts keep their password
 * and unavailable ones (deleted, or no longer a batch placeholder) are listed on the confirmation page
 * and in the audit event. Every issue is logged (event batch_credentials_issued); a package lost after
 * the issue (for example an interrupted download) is recovered by a confirmed reissue, reserved for
 * site administrators and logged as such.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credential_issue {
    /** Lock type of the per-batch issue lock. */
    public const LOCK_TYPE = 'tool_flexaccess_issue';

    /** Length of issued passwords (the site policy may demand more, see batch::generate_password()). */
    public const PASSWORD_LENGTH = 10;

    /** Login cards per PDF page (see batch_export::login_cards()). */
    private const CARDS_PER_PAGE = 8;

    /**
     * Classify the members of a batch for an issue.
     *
     * @param int $batchid Batch.
     * @return \stdClass {batch, issue: username => userid, personalised: string[], unavailable: string[], token}
     * @throws \moodle_exception If the batch does not exist.
     */
    public function plan(int $batchid): \stdClass {
        global $DB;
        $batch = batch::get($batchid);
        if (!$batch) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $members = batch::members($batchid);
        $userids = array_map(fn($m) => (int) $m->userid, $members);
        $users = $userids ? $DB->get_records_list('user', 'id', array_values($userids), '', 'id, auth, email, deleted') : [];
        $issue = [];
        $personalised = [];
        $unavailable = [];
        foreach ($members as $member) {
            $user = $users[(int) $member->userid] ?? null;
            if (!empty($member->converted)) {
                $personalised[] = (string) $member->username;
            } else if (
                !$user || !empty($user->deleted) || $user->auth !== 'flexaccess'
                || !str_ends_with((string) $user->email, '@flexaccess.invalid')
            ) {
                $unavailable[] = (string) $member->username;
            } else {
                $issue[(string) $member->username] = (int) $member->userid;
            }
        }
        ksort($issue);
        return (object) [
            'batch' => $batch,
            'issue' => $issue,
            'personalised' => $personalised,
            'unavailable' => $unavailable,
            'token' => hash('sha256', implode('|', [
                $batchid,
                (int) $batch->timeissued,
                (string) ($batch->status ?? batch::STATUS_COMPLETE),
                json_encode($issue),
            ])),
        ];
    }

    /**
     * Issue the package: verify, prepare, build and check the files, then rotate the passwords.
     *
     * @param int $batchid Batch.
     * @param string $token Token of the plan the operator confirmed.
     * @param bool $mayreissue Whether the caller may issue a batch that was issued before.
     * @param string $dir Empty request directory for the files.
     * @param string $courseurl Course URL printed on the cards.
     * @return array{zip:string, filename:string, count:int} The verified package.
     * @throws \moodle_exception With nothing changed, on every refusal or failure.
     */
    public function issue(int $batchid, string $token, bool $mayreissue, string $dir, string $courseurl): array {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE)->get_lock('batch_' . $batchid, 0);
        if (!$lock) {
            throw new \moodle_exception('batchissuebusy', 'tool_flexaccess');
        }
        try {
            // Phase 1: verify, under the lock.
            $plan = $this->plan($batchid);
            $batch = $plan->batch;
            if ((int) $batch->timeissued > 0 && !$mayreissue) {
                throw new \moodle_exception('batchalreadyissued', 'tool_flexaccess');
            }
            if (($batch->status ?? batch::STATUS_COMPLETE) !== batch::STATUS_COMPLETE) {
                throw new \moodle_exception('batchnotreadyyet', 'tool_flexaccess');
            }
            if (!hash_equals($plan->token, $token)) {
                // Issued again, or the member set changed, since the operator confirmed.
                throw new \moodle_exception('batchissuestale', 'tool_flexaccess');
            }
            if (!$plan->issue) {
                throw new \moodle_exception('batchissuenothing', 'tool_flexaccess');
            }

            // Phase 2: credentials in memory only.
            $credentials = [];
            foreach ($plan->issue as $username => $userid) {
                $credentials[$username] = batch::generate_password(self::PASSWORD_LENGTH);
            }

            // Phase 3: build, write and verify every file and the archive.
            $base = clean_filename(format_string($batch->name)) ?: ('batch' . $batchid);
            $files = [
                $base . '.xlsx' => $this->render('excel', $batch, $credentials, $courseurl),
                $base . '-liste.pdf' => $this->render('list', $batch, $credentials, $courseurl),
                $base . '-kaertchen.pdf' => $this->render('cards', $batch, $credentials, $courseurl),
            ];
            $this->verify_spreadsheet($files[$base . '.xlsx'], $credentials, $dir);
            $this->verify_pdf($files[$base . '-liste.pdf'], 1);
            $this->verify_pdf($files[$base . '-kaertchen.pdf'], (int) ceil(count($credentials) / self::CARDS_PER_PAGE));
            $paths = [];
            foreach ($files as $name => $content) {
                $paths[$name] = $dir . '/' . $name;
                if ($this->write($paths[$name], $content) !== strlen($content)) {
                    throw new \moodle_exception('batchissuefailed', 'tool_flexaccess', '', $name);
                }
            }
            $zippath = $dir . '/' . $base . '.zip';
            if (!$this->archive($paths, $zippath)) {
                throw new \moodle_exception('batchissuefailed', 'tool_flexaccess', '', $base . '.zip');
            }
            $this->verify_archive($zippath, $files);

            // Phase 4: persist - every password and the issue, or nothing.
            $transaction = $DB->start_delegated_transaction();
            foreach ($plan->issue as $username => $userid) {
                if (!$this->set_password($userid, $credentials[$username])) {
                    throw new \moodle_exception('batchissuepasswordfailed', 'tool_flexaccess', '', s($username));
                }
            }
            $now = \core\di::get(\core\clock::class)->time();
            $DB->set_field('tool_flexaccess_batch', 'timeissued', $now, ['id' => $batchid]);
            \tool_flexaccess\event\batch_credentials_issued::create([
                'context' => \context_course::instance((int) $batch->courseid),
                'objectid' => $batchid,
                'other' => [
                    'count' => count($credentials),
                    'reissue' => (int) $batch->timeissued > 0,
                    'personalised' => count($plan->personalised),
                    'unavailable' => $plan->unavailable,
                ],
            ])->trigger();
            $transaction->allow_commit();
            return ['zip' => $zippath, 'filename' => $base . '.zip', 'count' => count($credentials)];
        } catch (\Throwable $e) {
            if (isset($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Build one file of the package.
     *
     * @param string $kind excel, list or cards.
     * @param \stdClass $batch Batch.
     * @param array $credentials username => password.
     * @param string $courseurl Course URL.
     * @return string File content.
     */
    protected function render(string $kind, \stdClass $batch, array $credentials, string $courseurl): string {
        switch ($kind) {
            case 'excel':
                return batch_export::excel($batch, $credentials);
            case 'list':
                return batch_export::pdf_list($batch, $credentials);
            default:
                return batch_export::login_cards($batch, $credentials, $courseurl, (string) ($batch->cardtext ?? ''));
        }
    }

    /**
     * Write a file.
     *
     * @param string $path Path.
     * @param string $content Content.
     * @return int|false Bytes written.
     */
    protected function write(string $path, string $content): int|false {
        return file_put_contents($path, $content);
    }

    /**
     * Pack the files.
     *
     * @param array $paths Name in the archive => path.
     * @param string $zippath Archive.
     * @return bool
     */
    protected function archive(array $paths, string $zippath): bool {
        return (bool) get_file_packer('application/zip')->archive_to_pathname($paths, $zippath);
    }

    /**
     * Change one password.
     *
     * @param int $userid Account.
     * @param string $password New password.
     * @return bool
     */
    protected function set_password(int $userid, string $password): bool {
        return \auth_flexaccess\api::set_account_password($userid, $password);
    }

    /**
     * The spreadsheet holds exactly the credential set, nothing more and nothing less.
     *
     * @param string $content XLSX.
     * @param array $credentials Expected set (username => password).
     * @param string $dir Scratch directory.
     * @throws \moodle_exception batchissuefailed.
     */
    private function verify_spreadsheet(string $content, array $credentials, string $dir): void {
        $path = $dir . '/verify.xlsx';
        try {
            if (file_put_contents($path, $content) !== strlen($content)) {
                throw new \RuntimeException('write');
            }
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getActiveSheet();
            $found = [];
            foreach ($sheet->getRowIterator(2, count($credentials) + 2) as $row) {
                $username = (string) $sheet->getCell('A' . $row->getRowIndex())->getValue();
                if ($username !== '') {
                    $found[$username] = (string) $sheet->getCell('B' . $row->getRowIndex())->getValue();
                }
            }
            ksort($found);
            if ($found !== $credentials) {
                throw new \RuntimeException('content');
            }
        } catch (\Throwable $e) {
            throw new \moodle_exception('batchissuefailed', 'tool_flexaccess', '', 'xlsx');
        } finally {
            @unlink($path);
        }
    }

    /**
     * A complete PDF document with at least the expected number of pages.
     *
     * @param string $content PDF.
     * @param int $minpages Expected minimum page count.
     * @throws \moodle_exception batchissuefailed.
     */
    private function verify_pdf(string $content, int $minpages): void {
        $pages = preg_match_all('~/Type\s*/Page(?![a-zA-Z])~', $content);
        if (!str_starts_with($content, '%PDF-') || !str_contains(substr($content, -64), '%%EOF') || $pages < $minpages) {
            throw new \moodle_exception('batchissuefailed', 'tool_flexaccess', '', 'pdf');
        }
    }

    /**
     * The archive holds exactly the three files, byte for byte.
     *
     * @param string $zippath Archive.
     * @param array $files Name => expected content.
     * @throws \moodle_exception batchissuefailed.
     */
    private function verify_archive(string $zippath, array $files): void {
        $zip = new \ZipArchive();
        $ok = is_file($zippath) && $zip->open($zippath, \ZipArchive::RDONLY) === true;
        if ($ok) {
            $ok = $zip->numFiles === count($files);
            foreach ($files as $name => $content) {
                $ok = $ok && $zip->getFromName($name) === $content;
            }
            $zip->close();
        }
        if (!$ok) {
            throw new \moodle_exception('batchissuefailed', 'tool_flexaccess', '', basename($zippath));
        }
    }
}
