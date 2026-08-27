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
 * Provisioning of batches of course accounts with random credentials.
 *
 * An administrator creates N accounts (temporary/restricted or permanent/full) with random
 * usernames and passwords, enrolled into a target course through the FlexAccess enrol instance.
 * Plain passwords are never stored: they exist only in memory during creation and export. Passwords
 * can be re-issued via {@see reset_credentials()}, which rotates the password of every member that
 * is still batch-managed and skips any member that has been personalised/converted.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class batch {
    /** Batch table. */
    private const TABLE = 'tool_flexaccess_batch';

    /** Batch member table. */
    private const MEMBER_TABLE = 'tool_flexaccess_batch_member';

    /** Hard upper bound on accounts requested in one batch. */
    private const MAX_SYNC_CREATE = 1000;

    /** Absolute minimum length, regardless of what a caller asks for. */
    public const MIN_PASSWORD_LENGTH = 6;

    /** Default length of a generated password; short enough to type, long enough to be unguessable. */
    public const DEFAULT_PASSWORD_LENGTH = 6;

    /** How often a generated password may be re-rolled to satisfy the site's password policy. */
    private const PASSWORD_ATTEMPTS = 50;

    /** Accounts provisioned before committing progress, keeping each unit of work small. */
    public const PROVISION_CHUNK = 50;

    /** Above this many accounts, provisioning is handed to an ad-hoc task instead of the request. */
    public const SYNC_THRESHOLD = 50;

    /** Provisioning states of a batch. */
    public const STATUS_QUEUED = 'queued';

    /** Provisioning is running (synchronously or in the ad-hoc task). */
    public const STATUS_CREATING = 'creating';

    /** All requested accounts exist. */
    public const STATUS_COMPLETE = 'complete';

    /** Provisioning failed and was rolled back. */
    public const STATUS_FAILED = 'failed';

    /** A conversion import is running for this batch. */
    public const STATUS_CONVERTING = 'converting';

    /** Unambiguous alphabet for usernames/passwords (no 0/O/1/l/I). */
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';

    /** Password alphabet (adds unambiguous upper case and symbols). */
    private const PASS_ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789#%+=?';

    /**
     * Load a batch.
     *
     * @param int $id Batch id.
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * A page of batches, newest first.
     *
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size (0 = all).
     * @return \stdClass[]
     */
    public static function all(int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timecreated DESC', '*', $limitfrom, $limitnum);
    }

    /**
     * Batches provisioned for a single course, newest first.
     *
     * @param int $courseid Course id.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size (0 = no limit).
     * @return array Batch records.
     */
    public static function for_course(int $courseid, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['courseid' => $courseid], 'timecreated DESC', '*', $limitfrom, $limitnum);
    }

    /**
     * Number of batches provisioned for a single course.
     *
     * @param int $courseid Course id.
     * @return int
     */
    public static function count_for_course(int $courseid): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Whether the current user may manage batches for the given course.
     *
     * Two roles can: a site manager holding tool/flexaccess:managebatches at system level, and a
     * course teacher holding tool/flexaccess:managecoursebatches in the course context.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function can_manage(int $courseid): bool {
        return has_capability('tool/flexaccess:managebatches', \context_system::instance())
            || has_capability('tool/flexaccess:managecoursebatches', \context_course::instance($courseid));
    }

    /**
     * Require that the current user may manage batches for the given course.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_manage(int $courseid): void {
        if (!self::can_manage($courseid)) {
            throw new \required_capability_exception(
                \context_course::instance($courseid),
                'tool/flexaccess:managecoursebatches',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Whether the current user holds a granular batch capability for the course.
     *
     * Backward compatible: site managers (managebatches) and holders of the legacy coarse course
     * capability (managecoursebatches) keep all granular rights; the granular capability grants only
     * its specific action, for finer role setups.
     *
     * @param int $courseid Course id.
     * @param string $cap Short capability name (e.g. 'issuebatchcredentials').
     * @return bool
     */
    private static function has_batch_cap(int $courseid, string $cap): bool {
        $coursecontext = \context_course::instance($courseid);
        return has_capability('tool/flexaccess:managebatches', \context_system::instance())
            || has_capability('tool/flexaccess:managecoursebatches', $coursecontext)
            || has_capability('tool/flexaccess:' . $cap, $coursecontext);
    }

    /**
     * Require a granular batch capability, throwing the standard exception otherwise.
     *
     * @param int $courseid Course id.
     * @param string $cap Short capability name.
     * @return void
     */
    private static function require_batch_cap(int $courseid, string $cap): void {
        if (!self::has_batch_cap($courseid, $cap)) {
            throw new \required_capability_exception(
                \context_course::instance($courseid),
                'tool/flexaccess:' . $cap,
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Whether the current user may view the course's batches.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function can_view(int $courseid): bool {
        return self::has_batch_cap($courseid, 'viewcoursebatches');
    }

    /**
     * Require batch-view permission for the course.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_view(int $courseid): void {
        self::require_batch_cap($courseid, 'viewcoursebatches');
    }

    /**
     * Require batch-create permission for the course.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_create(int $courseid): void {
        self::require_batch_cap($courseid, 'createcoursebatches');
    }

    /**
     * Whether the current user may create batches for the course.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function can_create(int $courseid): bool {
        return self::has_batch_cap($courseid, 'createcoursebatches');
    }

    /**
     * Require credential-issuing permission for the course (rotating batch passwords).
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_issue(int $courseid): void {
        self::require_batch_cap($courseid, 'issuebatchcredentials');
    }

    /**
     * Require account-conversion permission for the course.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_convert(int $courseid): void {
        self::require_batch_cap($courseid, 'convertbatchaccounts');
    }

    /**
     * Whether the current user may request a batch for the given course.
     *
     * Anyone who can create a batch can also (trivially) request one; in addition, an editing
     * teacher holding tool/flexaccess:requestbatches may request without being able to provision.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function can_request(int $courseid): bool {
        // Anyone who may create a batch can trivially request one, so createcoursebatches counts
        // here too - otherwise a role holding only that capability was refused the page it is
        // supposed to work on.
        return self::can_manage($courseid)
            || self::has_batch_cap($courseid, 'createcoursebatches')
            || has_capability('tool/flexaccess:requestbatches', \context_course::instance($courseid));
    }

    /**
     * Whether the user may open the course batch page at all.
     *
     * The page hosts several operations, each guarded separately. Access is granted when at least
     * one of them is permitted; granular capabilities would otherwise be meaningless, because a
     * role allowed only to view was refused the page outright.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function can_open_course_page(int $courseid): bool {
        return self::can_view($courseid) || self::can_create($courseid) || self::can_request($courseid);
    }

    /**
     * Require permission to open the course batch page.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_course_page(int $courseid): void {
        if (!self::can_open_course_page($courseid)) {
            throw new \required_capability_exception(
                \context_course::instance($courseid),
                'tool/flexaccess:viewcoursebatches',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Require that the current user may request a batch for the given course.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_request(int $courseid): void {
        if (!self::can_request($courseid)) {
            throw new \required_capability_exception(
                \context_course::instance($courseid),
                'tool/flexaccess:requestbatches',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Users who may provision batches for the given course: course managers holding
     * managecoursebatches, plus site-level holders of managebatches. Keyed and de-duplicated by id.
     *
     * @param int $courseid Course id.
     * @return array<int, \stdClass> User records keyed by id.
     */
    public static function managers_for_course(int $courseid): array {
        $recipients = get_users_by_capability(
            \context_course::instance($courseid),
            'tool/flexaccess:managecoursebatches'
        );
        // Holders of the granular create capability are provisioners too; leaving them out meant a
        // request never reached some of the people who could actually fulfil it.
        $recipients += get_users_by_capability(
            \context_course::instance($courseid),
            'tool/flexaccess:createcoursebatches'
        );
        $recipients += get_users_by_capability(
            \context_system::instance(),
            'tool/flexaccess:managebatches'
        );
        return $recipients;
    }

    /**
     * Notify the course's batch provisioners that a list has been requested.
     *
     * Sends both an in-app message and an email (per each recipient's messaging preferences) with a
     * deep link that pre-fills the creation form. Returns the number of recipients notified.
     *
     * @param int $courseid Course id.
     * @param int $requesterid User id of the requester.
     * @param int $count Requested number of accounts.
     * @return int Recipients notified.
     */
    public static function notify_request(int $courseid, int $requesterid, int $count): int {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
        $requester = \core_user::get_user($requesterid);
        $coursename = format_string($course->fullname);
        $createurl = new \moodle_url('/admin/tool/flexaccess/coursebatches.php', [
            'courseid' => $courseid,
            'action' => 'new',
            'count' => $count,
        ]);

        $a = (object) [
            'requester' => fullname($requester),
            'course' => $coursename,
            'count' => $count,
        ];
        $notified = 0;
        foreach (self::managers_for_course($courseid) as $recipient) {
            if ((int) $recipient->id === $requesterid) {
                continue;
            }
            $message = new \core\message\message();
            $message->component = 'tool_flexaccess';
            $message->name = 'batchrequest';
            $message->userfrom = $requester;
            $message->userto = $recipient;
            $message->subject = get_string('batchrequestsubject', 'tool_flexaccess', $coursename);
            $message->fullmessage = get_string('batchrequestbody', 'tool_flexaccess', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html(get_string('batchrequestbody', 'tool_flexaccess', $a));
            $message->smallmessage = get_string('batchrequestsmall', 'tool_flexaccess', $a);
            $message->notification = 1;
            $message->contexturl = $createurl->out(false);
            $message->contexturlname = get_string('batchcreate', 'tool_flexaccess');
            if (message_send($message)) {
                $notified++;
            }
        }
        return $notified;
    }

    /**
     * Total number of batches.
     *
     * @return int
     */
    public static function count_all(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }

    /**
     * Member rows of a batch, ordered alphabetically by username.
     *
     * @param int $batchid Batch id.
     * @return \stdClass[]
     */
    public static function members(int $batchid): array {
        global $DB;
        return $DB->get_records(self::MEMBER_TABLE, ['batchid' => $batchid], 'username ASC');
    }

    /**
     * Generate a random, printable password from the unambiguous alphabet.
     *
     * @param int $length Password length (minimum 8).
     * @return string
     */
    public static function generate_password(int $length = self::DEFAULT_PASSWORD_LENGTH): string {
        global $CFG;
        // Respect the site's password policy. A generated password that the policy would reject
        // leaves the account unusable the moment the user tries to change it, and silently
        // undercuts a security setting the administrator deliberately made (for example a
        // 12-character minimum, or required character classes).
        // The requested length is a floor, never a ceiling: the site policy always wins, so a site
        // demanding twelve characters still gets twelve even when six were asked for.
        $length = max(self::MIN_PASSWORD_LENGTH, $length, (int) ($CFG->minpasswordlength ?? 0));
        $alphabet = self::PASS_ALPHABET;
        $max = strlen($alphabet) - 1;
        for ($attempt = 0; $attempt < self::PASSWORD_ATTEMPTS; $attempt++) {
            $out = '';
            for ($i = 0; $i < $length; $i++) {
                $out .= $alphabet[random_int(0, $max)];
            }
            $errors = '';
            if (!function_exists('check_password_policy') || check_password_policy($out, $errors)) {
                return $out;
            }
        }
        // The alphabet cannot satisfy this policy (for example it demands characters we never
        // generate). Failing loudly is right: silently handing out a password the site rejects
        // would produce accounts nobody can use.
        throw new \moodle_exception('batchpasswordpolicy', 'tool_flexaccess');
    }

    /**
     * Create a batch of accounts, enrol them and record membership.
     *
     * Small batches are provisioned synchronously. Larger ones (above {@see SYNC_THRESHOLD}) are
     * handed to an ad-hoc task so the web request never has to create hundreds of accounts: the
     * batch is returned immediately in status 'queued' and filled in the background. Credentials are
     * issued separately afterwards, so nothing is lost by not provisioning inline.
     *
     * @param string $name Batch label.
     * @param int $courseid Target course id.
     * @param bool $permanent Whether to create permanent authenticated accounts.
     * @param int $count Number of accounts (1..1000).
     * @param string $usernameprefix Username prefix (sanitised).
     * @param int $passwordlength Password length.
     * @param int|null $timeexpires Expiry for temporary accounts (0/null = plugin default).
     * @param int|null $now Current time.
     * @return array{batchid:int, credentials:array<string,string>, status:string} Batch id, the
     *     username=>password map (empty when queued) and the resulting provisioning status.
     */
    public static function create(
        string $name,
        int $courseid,
        bool $permanent,
        int $count,
        string $usernameprefix = 'kurs',
        int $passwordlength = self::DEFAULT_PASSWORD_LENGTH,
        ?int $timeexpires = null,
        ?int $now = null,
        string $cardtext = ''
    ): array {
        global $DB, $USER;
        $now = $now ?? time();
        $count = max(1, min(self::MAX_SYNC_CREATE, $count));
        $prefix = self::sanitise_prefix($usernameprefix);
        $async = $count > self::SYNC_THRESHOLD;

        $batchid = (int) $DB->insert_record(self::TABLE, (object) [
            'name' => $name !== '' ? $name : $prefix,
            'courseid' => $courseid,
            'permanent' => $permanent ? 1 : 0,
            'membercount' => 0,
            'requestedcount' => $count,
            'status' => $async ? self::STATUS_QUEUED : self::STATUS_CREATING,
            'cardtext' => $cardtext,
            'timecreated' => $now,
            'usermodified' => (int) $USER->id,
        ]);

        if ($async) {
            $task = new \tool_flexaccess\task\provision_batch();
            $task->set_custom_data([
                'batchid' => $batchid,
                'count' => $count,
                'permanent' => $permanent,
                'prefix' => $prefix,
                'passwordlength' => $passwordlength,
                'timeexpires' => $timeexpires,
            ]);
            \core\task\manager::queue_adhoc_task($task);
            return ['batchid' => $batchid, 'credentials' => [], 'status' => self::STATUS_QUEUED];
        }

        $credentials = self::provision_members($batchid, $count, $permanent, $prefix, $passwordlength, $timeexpires, $now);
        return ['batchid' => $batchid, 'credentials' => $credentials, 'status' => self::STATUS_COMPLETE];
    }

    /**
     * Provision the still-missing accounts of a batch and record them as members.
     *
     * Idempotent and resumable: only the difference between the requested count and the members
     * that already exist is created, so a retried ad-hoc task resumes instead of producing
     * duplicate accounts or enrolments. `membercount` is written after each chunk, so it reflects
     * real committed progress rather than a guess.
     *
     * Deliberately NOT wrapped in one big transaction. A transaction spanning up to 1000 user
     * creations would be held open far too long, and Moodle's rollback() re-throws - which would
     * make the FAILED state unreachable and leave a failed batch stuck on CREATING forever.
     * Instead each member is written only after its account and enrolment succeeded, so an
     * interrupted run leaves a smaller but consistent batch that the retry simply completes.
     *
     * @param int $batchid Batch to fill.
     * @param int $count Total number of accounts the batch should end up with.
     * @param bool $permanent Whether to create permanent authenticated accounts.
     * @param string $prefix Sanitised username prefix.
     * @param int $passwordlength Password length.
     * @param int|null $timeexpires Expiry for temporary accounts.
     * @param int|null $now Current time.
     * @return array<string,string> Username => plain password map (never persisted).
     */
    public static function provision_members(
        int $batchid,
        int $count,
        bool $permanent,
        string $prefix,
        int $passwordlength = 10,
        ?int $timeexpires = null,
        ?int $now = null
    ): array {
        global $DB;
        $now = $now ?? time();
        $batch = self::get($batchid);
        if (!$batch) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        $courseid = (int) $batch->courseid;
        $DB->set_field(self::TABLE, 'status', self::STATUS_CREATING, ['id' => $batchid]);
        $DB->set_field(self::TABLE, 'statusmessage', null, ['id' => $batchid]);

        $existing = $DB->count_records(self::MEMBER_TABLE, ['batchid' => $batchid]);
        $missing = max(0, $count - $existing);
        $credentials = [];

        try {
            while ($missing > 0) {
                $chunk = min(self::PROVISION_CHUNK, $missing);
                $made = 0;
                for ($i = 0; $i < $chunk; $i++) {
                    $username = self::unique_username($prefix);
                    $password = self::generate_password($passwordlength);
                    $userid = 0;
                    try {
                        $userid = \auth_flexaccess\api::create_batch_account(
                            $username,
                            $password,
                            '',
                            '',
                            $permanent,
                            $timeexpires,
                            $now
                        );
                        \enrol_flexaccess\local\enrol_service::admin_enrol($courseid, $userid, !$permanent, $now);
                        $DB->insert_record(self::MEMBER_TABLE, (object) [
                            'batchid' => $batchid,
                            'userid' => $userid,
                            'username' => $username,
                        ]);
                    } catch (\Throwable $e) {
                        // Per-member compensation: the account exists but nothing references it yet.
                        // Without this it would be invisible to the resumable batch, and a retry
                        // would create a second account instead of completing this one.
                        if ($userid > 0) {
                            self::compensate_member($batchid, $userid);
                        }
                        throw $e;
                    }
                    $credentials[$username] = $password;
                    $made++;
                }
                $existing += $made;
                $DB->set_field(self::TABLE, 'membercount', $existing, ['id' => $batchid]);
                $missing -= $made;
            }
        } catch (\Throwable $e) {
            self::mark_failed($batchid, $e);
            throw $e;
        }

        $DB->set_field(self::TABLE, 'membercount', $existing, ['id' => $batchid]);
        $DB->set_field(self::TABLE, 'status', self::STATUS_COMPLETE, ['id' => $batchid]);
        return $credentials;
    }

    /**
     * Undo a half-provisioned member so no orphaned account is left behind.
     *
     * If the rollback itself fails, the original error must still surface - it is the one that
     * describes what actually went wrong - but the leftover account is recorded rather than lost.
     *
     * @param int $batchid Batch the member belongs to.
     * @param int $userid Account created for the member.
     * @return void
     */
    private static function compensate_member(int $batchid, int $userid): void {
        $removed = false;
        $reason = '';
        try {
            if (method_exists('\auth_flexaccess\api', 'rollback_batch_account')) {
                $removed = \auth_flexaccess\api::rollback_batch_account($userid);
            }
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        if ($removed) {
            return;
        }
        // Silently swallowing this would hide an account nobody can account for. Record it so the
        // leftover is visible on the batch and can be cleaned up deliberately.
        debugging(
            "FlexAccess: konnte Konto $userid nach fehlgeschlagener Bereitstellung nicht entfernen. $reason",
            DEBUG_NORMAL
        );
        self::note_cleanup_failure($batchid, $userid);
    }

    /**
     * Note on the batch that a leftover account could not be removed.
     *
     * @param int $batchid Batch id.
     * @param int $userid Account that remained.
     * @return void
     */
    private static function note_cleanup_failure(int $batchid, int $userid): void {
        global $DB;
        try {
            $DB->set_field(
                self::TABLE,
                'statusmessage',
                get_string('batchcleanupfailed', 'tool_flexaccess', $userid),
                ['id' => $batchid]
            );
        } catch (\Throwable $ignored) {
            unset($ignored);
        }
    }

    /**
     * Record that provisioning failed, together with the reason.
     *
     * Runs outside any transaction, so the state is guaranteed to persist: a failed batch must
     * never stay on CREATING, which would look like work still in progress that never finishes.
     *
     * @param int $batchid Batch id.
     * @param \Throwable $e The failure.
     * @return void
     */
    private static function mark_failed(int $batchid, \Throwable $e): void {
        global $DB;
        $DB->set_field(self::TABLE, 'status', self::STATUS_FAILED, ['id' => $batchid]);
        $DB->set_field(
            self::TABLE,
            'statusmessage',
            \core_text::substr($e->getMessage(), 0, 255),
            ['id' => $batchid]
        );
    }

    /**
     * Reset every still-managed member's password to a fresh value and return the new credentials.
     *
     * This is the secure way to issue login sheets: plain passwords are never persisted, so issuing
     * always rolls new passwords. Members that have been personalised/converted are skipped.
     *
     * @param int $batchid Batch id.
     * @param int $passwordlength Password length.
     * @return array<string,string> username => new plain password, alphabetical by username.
     */
    public static function reset_credentials(int $batchid, int $passwordlength = 10): array {
        $credentials = [];
        foreach (self::members($batchid) as $member) {
            // Never rotate the password of a member that has left batch management (personalised /
            // converted to a permanent account): doing so would be a credential takeover.
            // The stored flag is the authority; set_account_password() enforces the same rule again.
            if (!empty($member->converted)) {
                continue;
            }
            $password = self::generate_password($passwordlength);
            if (\auth_flexaccess\api::set_account_password((int) $member->userid, $password)) {
                $credentials[$member->username] = $password;
            }
        }
        return $credentials;
    }

    /**
     * Mark a batch as having a conversion import in progress.
     *
     * @param int $batchid Batch id.
     * @param int $total Number of rows to process.
     * @return void
     */
    public static function mark_converting(int $batchid, int $total): void {
        global $DB;
        $DB->set_field(self::TABLE, 'status', self::STATUS_CONVERTING, ['id' => $batchid]);
        $DB->set_field(
            self::TABLE,
            'statusmessage',
            get_string('batchconvertprogress', 'tool_flexaccess', (object) ['done' => 0, 'total' => $total]),
            ['id' => $batchid]
        );
    }

    /**
     * Write back how far a running conversion has got, so progress is visible while it runs.
     *
     * @param int $batchid Batch id.
     * @param int $done Rows converted so far.
     * @return void
     */
    public static function report_convert_progress(int $batchid, int $done): void {
        global $DB;
        $batch = self::get($batchid);
        if (!$batch) {
            return;
        }
        $total = (int) $batch->requestedcount ?: (int) $batch->membercount;
        $DB->set_field(
            self::TABLE,
            'statusmessage',
            get_string('batchconvertprogress', 'tool_flexaccess', (object) ['done' => $done, 'total' => $total]),
            ['id' => $batchid]
        );
    }

    /**
     * Record the outcome of a finished conversion import.
     *
     * @param int $batchid Batch id.
     * @param int $converted Rows converted.
     * @param int $skipped Rows skipped or failed.
     * @return void
     */
    public static function finish_converting(int $batchid, int $converted, int $skipped): void {
        global $DB;
        $DB->set_field(self::TABLE, 'status', self::STATUS_COMPLETE, ['id' => $batchid]);
        $DB->set_field(
            self::TABLE,
            'statusmessage',
            get_string(
                'batchconvertdone',
                'tool_flexaccess',
                (object) ['converted' => $converted, 'skipped' => $skipped]
            ),
            ['id' => $batchid]
        );
    }

    /**
     * Human-readable provisioning status of a batch, including progress while it is being filled.
     *
     * @param \stdClass $batch Batch record.
     * @return string Localised status label.
     */
    public static function status_label(\stdClass $batch): string {
        $status = (string) ($batch->status ?? self::STATUS_COMPLETE);
        $label = get_string('batchstatus' . $status, 'tool_flexaccess');
        if ($status === self::STATUS_CONVERTING && !empty($batch->statusmessage)) {
            // A running conversion reports its own row-level progress.
            return $label . ' (' . s((string) $batch->statusmessage) . ')';
        }
        if ($status === self::STATUS_FAILED && !empty($batch->statusmessage)) {
            // Show the administrator why it failed instead of a bare "failed".
            $label .= ' (' . s((string) $batch->statusmessage) . ')';
        }
        // Progress is only shown while work is actually running: membercount is committed per
        // chunk, so the number is real. A queued batch has not started and gets no fake counter.
        if ($status === self::STATUS_CREATING) {
            $label .= ' (' . get_string('batchprogress', 'tool_flexaccess', (object) [
                'done' => (int) $batch->membercount,
                'total' => (int) ($batch->requestedcount ?: $batch->membercount),
            ]) . ')';
        }
        return $label;
    }

    /**
     * Sanitise a username prefix to lower-case letters and digits.
     *
     * @param string $prefix Raw prefix.
     * @return string
     */
    private static function sanitise_prefix(string $prefix): string {
        $prefix = \core_text::strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $prefix));
        return $prefix !== '' ? substr($prefix, 0, 20) : 'kurs';
    }

    /**
     * Produce a username unique in the user table.
     *
     * @param string $prefix Username prefix.
     * @return string
     */
    private static function unique_username(string $prefix): string {
        global $DB, $CFG;
        $max = strlen(self::ALPHABET) - 1;
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= self::ALPHABET[random_int(0, $max)];
            }
            $username = $prefix . '-' . $suffix;
        } while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]));
        return $username;
    }
}
