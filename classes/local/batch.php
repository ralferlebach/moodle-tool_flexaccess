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
 * is still batch-managed and skips any member that has been personalised/converted (P0-1).
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

    /** Hard upper bound on accounts provisioned in a single synchronous request. */
    private const MAX_SYNC_CREATE = 1000;

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
        return self::can_manage($courseid)
            || has_capability('tool/flexaccess:requestbatches', \context_course::instance($courseid));
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
            $message->subject = get_string('batchrequest:subject', 'tool_flexaccess', $coursename);
            $message->fullmessage = get_string('batchrequest:body', 'tool_flexaccess', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html(get_string('batchrequest:body', 'tool_flexaccess', $a));
            $message->smallmessage = get_string('batchrequest:small', 'tool_flexaccess', $a);
            $message->notification = 1;
            $message->contexturl = $createurl->out(false);
            $message->contexturlname = get_string('batch:create', 'tool_flexaccess');
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
    public static function generate_password(int $length = 10): string {
        $length = max(8, $length);
        $alphabet = self::PASS_ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    /**
     * Create a batch of accounts, enrol them and record membership.
     *
     * @param string $name Batch label.
     * @param int $courseid Target course id.
     * @param bool $permanent Whether to create permanent authenticated accounts.
     * @param int $count Number of accounts (1..1000).
     * @param string $usernameprefix Username prefix (sanitised).
     * @param int $passwordlength Password length.
     * @param int|null $timeexpires Expiry for temporary accounts (0/null = plugin default).
     * @param int|null $now Current time.
     * @return array{batchid:int, credentials:array<string,string>} New batch id and username=>password map.
     */
    public static function create(
        string $name,
        int $courseid,
        bool $permanent,
        int $count,
        string $usernameprefix = 'kurs',
        int $passwordlength = 10,
        ?int $timeexpires = null,
        ?int $now = null
    ): array {
        global $DB, $USER;
        $now = $now ?? time();
        $count = max(1, min(self::MAX_SYNC_CREATE, $count));
        $prefix = self::sanitise_prefix($usernameprefix);

        // Wrap the whole provisioning in a transaction: if any account creation/enrolment fails
        // mid-run, the entire batch is rolled back instead of leaving a partial, inconsistent batch.
        $transaction = $DB->start_delegated_transaction();
        try {
            $batchid = (int) $DB->insert_record(self::TABLE, (object) [
                'name' => $name !== '' ? $name : $prefix,
                'courseid' => $courseid,
                'permanent' => $permanent ? 1 : 0,
                'membercount' => 0,
                'timecreated' => $now,
                'usermodified' => (int) $USER->id,
            ]);

            $credentials = [];
            $created = 0;
            for ($i = 0; $i < $count; $i++) {
                $username = self::unique_username($prefix);
                $password = self::generate_password($passwordlength);
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
                $credentials[$username] = $password;
                $created++;
            }
            $DB->set_field(self::TABLE, 'membercount', $created, ['id' => $batchid]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        return ['batchid' => $batchid, 'credentials' => $credentials];
    }

    /**
     * Reset every member's password to a fresh random value and return the new credentials.
     *
     * This is the secure way to re-issue login sheets: plain passwords are never persisted, so a
     * re-download simply rolls new passwords (safe because batch accounts are freshly provisioned).
     *
     * @param int $batchid Batch id.
     * @param int $passwordlength Password length.
     * @return array<string,string> username => new plain password, alphabetical by username.
     */
    public static function reset_credentials(int $batchid, int $passwordlength = 10): array {
        $credentials = [];
        foreach (self::members($batchid) as $member) {
            // Never rotate the password of a member that has left batch management (personalised /
            // converted to a permanent account): doing so would be a credential takeover (P0-1).
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
