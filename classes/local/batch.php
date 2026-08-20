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
 * Plain passwords are never stored: they exist only in memory during creation and export, and can be
 * re-issued on demand via {@see reset_credentials()} (which resets every member's password).
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
        $count = max(1, min(1000, $count));
        $prefix = self::sanitise_prefix($usernameprefix);

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
            $password = self::generate_password($passwordlength);
            \auth_flexaccess\api::set_account_password((int) $member->userid, $password);
            $credentials[$member->username] = $password;
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
