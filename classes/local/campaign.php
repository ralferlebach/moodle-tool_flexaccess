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
 * Tokenised FlexAccess invitation campaigns.
 *
 * A campaign is an admin-created, shareable link (bearing a random token) that invites visitors to
 * self-register into a specific course through the FlexAccess quick-registration flow. It can be
 * time-boxed, capped by a maximum number of redemptions, and gated by a shared password or an
 * allowed email domain (the campaign-level placement of the quick-registration gate).
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaign {
    /** Table name. */
    private const TABLE = 'tool_flexaccess_campaign';

    /**
     * Generate a random URL-safe campaign token.
     *
     * @return string
     */
    public static function generate_token(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Hash a campaign token for storage.
     *
     * The token is a bearer secret: anyone holding it can redeem the campaign. Only its hash is
     * stored, so a database leak (or an administrator browsing the table) does not hand out working
     * links, and a lost link can be rotated but never recovered.
     *
     * @param string $token Clear token.
     * @return string SHA-256 hash.
     */
    public static function hash_token(string $token): string {
        return hash('sha256', trim($token));
    }

    /**
     * Hash a shared gate password for storage (empty stays empty).
     *
     * @param string $password Clear password.
     * @return string
     */
    public static function hash_password(string $password): string {
        $password = trim($password);
        return $password === '' ? '' : password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Load a campaign by id.
     *
     * @param int $id Campaign id.
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Load a campaign by its token.
     *
     * @param string $token Campaign token.
     * @return \stdClass|null
     */
    public static function get_by_token(string $token): ?\stdClass {
        global $DB;
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $record = $DB->get_record(self::TABLE, ['tokenhash' => self::hash_token($token)]);
        return $record ?: null;
    }

    /**
     * A page of campaigns, newest first.
     *
     * @param int $limitfrom Offset (0 = start).
     * @param int $limitnum Page size (0 = no limit).
     * @return \stdClass[]
     */
    public static function all(int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timecreated DESC', '*', $limitfrom, $limitnum);
    }

    /**
     * Total number of campaigns.
     *
     * @return int
     */
    public static function count_all(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }

    /**
     * Create a campaign from normalised form data.
     *
     * The plaintext token is returned to the caller so it can be shown to the administrator once;
     * it is not stored and cannot be recovered later - only rotated.
     *
     * @param array $data Field values (name, courseid, enabled, window, maxredemptions, gate*).
     * @return array{id:int, token:string} New campaign id and its one-time plaintext token.
     */
    public static function create(array $data): array {
        global $DB, $USER;
        $now = time();
        $token = self::unique_token();
        $record = self::normalise($data);
        $record->tokenhash = self::hash_token($token);
        $record->redemptioncount = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->usermodified = (int) $USER->id;
        return ['id' => (int) $DB->insert_record(self::TABLE, $record), 'token' => $token];
    }

    /**
     * Issue a new token for a campaign, invalidating the previous link.
     *
     * The only supported way to recover from a lost link: the old hash is replaced, so any link
     * already handed out stops working immediately.
     *
     * @param int $id Campaign id.
     * @return string|null The new plaintext token, or null if the campaign does not exist.
     */
    public static function rotate_token(int $id): ?string {
        global $DB, $USER;
        if (!self::get($id)) {
            return null;
        }
        $token = self::unique_token();
        $DB->set_field(self::TABLE, 'tokenhash', self::hash_token($token), ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
        $DB->set_field(self::TABLE, 'usermodified', (int) $USER->id, ['id' => $id]);
        return $token;
    }

    /**
     * Update an existing campaign. The token and redemption count are preserved.
     *
     * @param int $id Campaign id.
     * @param array $data Field values.
     * @return void
     */
    public static function update(int $id, array $data): void {
        global $DB, $USER;
        $existing = self::get($id);
        if (!$existing) {
            return;
        }
        $record = self::normalise($data);
        $record->id = $id;
        // A newly submitted password replaces the stored hash; an empty field keeps the existing one.
        if ($record->gatemode !== 'password' || ($record->gatepasswordhash ?? '') === '') {
            unset($record->gatepasswordhash);
            if ($record->gatemode !== 'password') {
                $record->gatepasswordhash = null;
            }
        }
        $record->timemodified = time();
        $record->usermodified = (int) $USER->id;
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Delete a campaign.
     *
     * @param int $id Campaign id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Whether a campaign may currently be redeemed (enabled, within window, redemptions remaining).
     *
     * @param \stdClass $campaign Campaign record.
     * @param int|null $now Current time.
     * @return bool
     */
    public static function is_redeemable(\stdClass $campaign, ?int $now = null): bool {
        $now = $now ?? time();
        if (empty($campaign->enabled)) {
            return false;
        }
        if ((int) $campaign->timeavailablefrom > 0 && $now < (int) $campaign->timeavailablefrom) {
            return false;
        }
        if ((int) $campaign->timeavailableuntil > 0 && $now > (int) $campaign->timeavailableuntil) {
            return false;
        }
        if ((int) $campaign->maxredemptions > 0 && (int) $campaign->redemptioncount >= (int) $campaign->maxredemptions) {
            return false;
        }
        return true;
    }

    /**
     * Atomically reserve one redemption of a campaign.
     *
     * A per-campaign Moodle lock serialises the check-and-increment, so a burst of concurrent
     * requests for the last slot cannot over-reserve: the redeemability check and the increment
     * happen inside the lock. Callers must reserve BEFORE creating any account/enrolment and, if the
     * downstream flow then fails, call {@see release_reservation()} to give the slot back.
     *
     * @param int $id Campaign id.
     * @param int|null $now Current time.
     * @return bool True when a slot was reserved.
     */
    public static function redeem(int $id, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_flexaccess_campaign');
        $lock = $lockfactory->get_lock('campaign_' . $id, 10);
        if (!$lock) {
            return false;
        }
        try {
            $campaign = self::get($id);
            if (!$campaign || !self::is_redeemable($campaign, $now)) {
                return false;
            }
            $DB->set_field(self::TABLE, 'redemptioncount', (int) $campaign->redemptioncount + 1, ['id' => $id]);
            $DB->set_field(self::TABLE, 'timemodified', $now, ['id' => $id]);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Return a previously reserved slot (compensation when the downstream sign-up fails).
     *
     * @param int $id Campaign id.
     * @param int|null $now Current time.
     * @return void
     */
    public static function release_reservation(int $id, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_flexaccess_campaign');
        $lock = $lockfactory->get_lock('campaign_' . $id, 10);
        if (!$lock) {
            return;
        }
        try {
            $count = (int) $DB->get_field(self::TABLE, 'redemptioncount', ['id' => $id]);
            if ($count > 0) {
                $DB->set_field(self::TABLE, 'redemptioncount', $count - 1, ['id' => $id]);
                $DB->set_field(self::TABLE, 'timemodified', $now, ['id' => $id]);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether a redemption attempt satisfies the campaign gate.
     *
     * @param \stdClass $campaign Campaign record.
     * @param string $email Applicant email.
     * @param string $password Shared password supplied (password gate only).
     * @return bool
     */
    public static function passes_gate(\stdClass $campaign, string $email, string $password): bool {
        switch ($campaign->gatemode) {
            case 'password':
                $hash = trim((string) $campaign->gatepasswordhash);
                if ($hash === '') {
                    return false;
                }
                return password_verify($password, $hash);
            case 'domain':
                return self::domain_allowed($email, (string) $campaign->gatedomains);
            default:
                return true;
        }
    }

    /**
     * Whether the email's domain is in the allowed list (exact or subdomain).
     *
     * @param string $email Email address.
     * @param string $domains Newline/comma separated domain list.
     * @return bool
     */
    private static function domain_allowed(string $email, string $domains): bool {
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $emaildomain = \core_text::strtolower(trim(substr($email, $at + 1)));
        if ($emaildomain === '') {
            return false;
        }
        $allowed = preg_split('/[\s,]+/', \core_text::strtolower($domains), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($allowed as $domain) {
            $domain = ltrim(trim($domain), '@');
            if ($domain !== '' && ($emaildomain === $domain || str_ends_with($emaildomain, '.' . $domain))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalise raw form data into a storable record (without id/token/timestamps).
     *
     * @param array $data Raw data.
     * @return \stdClass
     */
    private static function normalise(array $data): \stdClass {
        $gatemode = in_array($data['gatemode'] ?? 'none', ['none', 'password', 'domain'], true)
            ? $data['gatemode'] : 'none';
        $record = (object) [
            'name' => trim((string) ($data['name'] ?? '')),
            'courseid' => (int) ($data['courseid'] ?? 0),
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'timeavailablefrom' => max(0, (int) ($data['timeavailablefrom'] ?? 0)),
            'timeavailableuntil' => max(0, (int) ($data['timeavailableuntil'] ?? 0)),
            'maxredemptions' => max(0, (int) ($data['maxredemptions'] ?? 0)),
            'gatemode' => $gatemode,
            'gatedomains' => $gatemode === 'domain' ? trim((string) ($data['gatedomains'] ?? '')) : null,
        ];
        $newpassword = trim((string) ($data['gatepassword'] ?? ''));
        if ($gatemode === 'password' && $newpassword !== '') {
            $record->gatepasswordhash = self::hash_password($newpassword);
        }
        return $record;
    }

    /**
     * Produce a token guaranteed unique in the table.
     *
     * @return string
     */
    private static function unique_token(): string {
        global $DB;
        do {
            $token = self::generate_token();
        } while ($DB->record_exists(self::TABLE, ['tokenhash' => self::hash_token($token)]));
        return $token;
    }
}
