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
 * Privacy provider for tool_flexaccess.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_flexaccess\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The administration tool records the administrator who last modified each invitation campaign.
 *
 * @package    tool_flexaccess
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** Campaign table. */
    private const TABLE = 'tool_flexaccess_campaign';

    /** Invitation table. */
    private const INVITE_TABLE = 'tool_flexaccess_invite';

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'usermodified' => 'privacy:metadata:campaign:usermodified',
            'name' => 'privacy:metadata:campaign:name',
            'timemodified' => 'privacy:metadata:campaign:timemodified',
        ], 'privacy:metadata:campaign');
        $collection->add_database_table(self::INVITE_TABLE, [
            'usermodified' => 'privacy:metadata:invite:usermodified',
            'email' => 'privacy:metadata:invite:email',
            'timecreated' => 'privacy:metadata:invite:timecreated',
        ], 'privacy:metadata:invite');
        return $collection;
    }

    /**
     * Contexts containing data for a user: the system context when they modified any campaign.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $email = (string) $DB->get_field('user', 'email', ['id' => $userid]);
        if (
            $DB->record_exists(self::TABLE, ['usermodified' => $userid])
                || $DB->record_exists(self::INVITE_TABLE, ['usermodified' => $userid])
                || ($email !== '' && $DB->record_exists(self::INVITE_TABLE, ['email' => $email]))
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users within a (system) context: administrators who modified campaigns.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach ([self::TABLE, self::INVITE_TABLE] as $table) {
            $userids = $DB->get_fieldset_select($table, 'DISTINCT usermodified', 'usermodified <> 0');
            if ($userids) {
                $userlist->add_users(array_map('intval', $userids));
            }
        }
    }

    /**
     * Export the campaigns a user last modified.
     *
     * @param approved_contextlist $contextlist Approved contexts for a user.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $campaigns = $DB->get_records(self::TABLE, ['usermodified' => $userid], 'timemodified ASC');
            foreach ($campaigns as $campaign) {
                writer::with_context($context)->export_data(
                    ['tool_flexaccess', 'campaign', (string) $campaign->id],
                    (object) [
                        'name' => $campaign->name,
                        'timemodified' => \core_privacy\local\request\transform::datetime((int) $campaign->timemodified),
                    ]
                );
            }
            $email = (string) $DB->get_field('user', 'email', ['id' => $userid]);
            $invites = $DB->get_records_select(
                self::INVITE_TABLE,
                'usermodified = :uid' . ($email !== '' ? ' OR email = :email' : ''),
                $email !== '' ? ['uid' => $userid, 'email' => $email] : ['uid' => $userid],
                'timecreated ASC'
            );
            foreach ($invites as $invite) {
                writer::with_context($context)->export_data(
                    ['tool_flexaccess', 'invitation', (string) $invite->id],
                    (object) [
                        'email' => $invite->email,
                        'status' => $invite->status,
                        'timecreated' => \core_privacy\local\request\transform::datetime((int) $invite->timecreated),
                    ]
                );
            }
        }
    }

    /**
     * Delete data for all users in a context: anonymise the modifier on every campaign.
     *
     * Campaigns are institutional configuration, not the user's own data, so we null the modifier
     * reference rather than delete the campaign.
     *
     * @param \context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->set_field_select(self::TABLE, 'usermodified', 0, 'usermodified <> 0');
            $DB->set_field_select(self::INVITE_TABLE, 'usermodified', 0, 'usermodified <> 0');
        }
    }

    /**
     * Delete data for a user across approved contexts: anonymise their modifier references.
     *
     * @param approved_contextlist $contextlist Approved contexts for a user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $email = (string) $DB->get_field('user', 'email', ['id' => $userid]);
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->set_field(self::TABLE, 'usermodified', 0, ['usermodified' => $userid]);
                $DB->set_field(self::INVITE_TABLE, 'usermodified', 0, ['usermodified' => $userid]);
                if ($email !== '') {
                    $DB->set_field(self::INVITE_TABLE, 'email', '', ['email' => $email]);
                }
            }
        }
    }

    /**
     * Delete data for a set of users within a context: anonymise their modifier references.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select(self::TABLE, 'usermodified', 0, "usermodified $insql", $params);
        $DB->set_field_select(self::INVITE_TABLE, 'usermodified', 0, "usermodified $insql", $params);
        $emails = $DB->get_fieldset_select('user', 'email', "id $insql", $params);
        foreach (array_filter($emails) as $email) {
            $DB->set_field(self::INVITE_TABLE, 'email', '', ['email' => $email]);
        }
    }
}
