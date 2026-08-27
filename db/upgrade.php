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
 * Upgrade steps for tool_flexaccess.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the tool_flexaccess plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_tool_flexaccess_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026081907) {
        $table = new xmldb_table('tool_flexaccess_campaign');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('token', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timeavailablefrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeavailableuntil', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('maxredemptions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('redemptioncount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('gatemode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'none');
            $table->add_field('gatepasswordhash', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('gatedomains', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $table->add_index('token_uix', XMLDB_INDEX_UNIQUE, ['token']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026081907, 'tool', 'flexaccess');
    }

    if ($oldversion < 2026081914) {
        $table = new xmldb_table('tool_flexaccess_invite');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('campaignid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('token', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timesent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeaccepted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timereminded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('remindercount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $table->add_index('invitetoken_uix', XMLDB_INDEX_UNIQUE, ['token']);
            $table->add_index('status_sent_ix', XMLDB_INDEX_NOTUNIQUE, ['status', 'timesent']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026081914, 'tool', 'flexaccess');
    }

    if ($oldversion < 2026081919) {
        $table = new xmldb_table('tool_flexaccess_invite');
        if ($dbman->table_exists($table)) {
            // Add the hashed-token column and a reservation timestamp.
            $tokenhash = new xmldb_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'token');
            if (!$dbman->field_exists($table, $tokenhash)) {
                $dbman->add_field($table, $tokenhash);
            }
            $timereserved = new xmldb_field(
                'timereserved',
                XMLDB_TYPE_INTEGER,
                '10',
                null,
                XMLDB_NOTNULL,
                null,
                '0',
                'timesent'
            );
            if (!$dbman->field_exists($table, $timereserved)) {
                $dbman->add_field($table, $timereserved);
            }
            // Migrate any existing plaintext tokens into their SHA-256 hash, then drop the old index
            // and the plaintext column so no bearer secret remains at rest.
            $tokenfield = new xmldb_field('token');
            if ($dbman->field_exists($table, $tokenfield)) {
                $rs = $DB->get_recordset_select(
                    'tool_flexaccess_invite',
                    "token IS NOT NULL AND token <> ''",
                    null,
                    '',
                    'id, token'
                );
                foreach ($rs as $row) {
                    $DB->set_field(
                        'tool_flexaccess_invite',
                        'tokenhash',
                        hash('sha256', $row->token),
                        ['id' => $row->id]
                    );
                }
                $rs->close();
                $oldindex = new xmldb_index('invitetoken_uix', XMLDB_INDEX_UNIQUE, ['token']);
                if ($dbman->index_exists($table, $oldindex)) {
                    $dbman->drop_index($table, $oldindex);
                }
                $dbman->drop_field($table, $tokenfield);
            }
            // Make tokenhash NOT NULL and uniquely indexed.
            $dbman->change_field_notnull($table, new xmldb_field(
                'tokenhash',
                XMLDB_TYPE_CHAR,
                '64',
                null,
                XMLDB_NOTNULL,
                null,
                null
            ));
            $newindex = new xmldb_index('invitetokenhash_uix', XMLDB_INDEX_UNIQUE, ['tokenhash']);
            if (!$dbman->index_exists($table, $newindex)) {
                $dbman->add_index($table, $newindex);
            }
        }
        upgrade_plugin_savepoint(true, 2026081919, 'tool', 'flexaccess');
    }

    if ($oldversion < 2026081920) {
        $batch = new xmldb_table('tool_flexaccess_batch');
        if (!$dbman->table_exists($batch)) {
            $batch->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $batch->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $batch->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $batch->add_field('permanent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $batch->add_field('membercount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $batch->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $batch->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $batch->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $batch->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $dbman->create_table($batch);
        }
        $member = new xmldb_table('tool_flexaccess_batch_member');
        if (!$dbman->table_exists($member)) {
            $member->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $member->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $member->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $member->add_field('username', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $member->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $member->add_key('batchid_fk', XMLDB_KEY_FOREIGN, ['batchid'], 'tool_flexaccess_batch', ['id']);
            $member->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $member->add_index('batch_username_ix', XMLDB_INDEX_NOTUNIQUE, ['batchid', 'username']);
            $dbman->create_table($member);
        }
        upgrade_plugin_savepoint(true, 2026081920, 'tool', 'flexaccess');
    }

    if ($oldversion < 2026082415) {
        $dbman = $DB->get_manager();
        // Mark batch members that have left batch management (personalised/converted), so
        // their password can never be rotated again by a batch credential re-issue.
        $table = new xmldb_table('tool_flexaccess_batch_member');
        $field = new xmldb_field('converted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'username');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026082415, 'tool', 'flexaccess');
    }
    if ($oldversion < 2026082420) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('tool_flexaccess_batch');
        // Asynchronous provisioning state: how many accounts were requested and how far we got.
        $requested = new xmldb_field('requestedcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'membercount');
        if (!$dbman->field_exists($table, $requested)) {
            $dbman->add_field($table, $requested);
        }
        $status = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'complete', 'requestedcount');
        if (!$dbman->field_exists($table, $status)) {
            $dbman->add_field($table, $status);
        }
        // Existing batches were provisioned synchronously and are therefore complete.
        $DB->execute("UPDATE {tool_flexaccess_batch} SET requestedcount = membercount WHERE requestedcount = 0");
        upgrade_plugin_savepoint(true, 2026082420, 'tool', 'flexaccess');
    }
    if ($oldversion < 2026082424) {
        $dbman = $DB->get_manager();
        // Persist why a batch failed, so an administrator can act on it instead of guessing.
        $table = new xmldb_table('tool_flexaccess_batch');
        $field = new xmldb_field('statusmessage', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026082424, 'tool', 'flexaccess');
    }
    if ($oldversion < 2026082425) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('tool_flexaccess_campaign');
        // The campaign token is a bearer secret and must not sit in the database in clear.
        // No DEFAULT: XMLDB rejects '' as a default on a CHAR NOT NULL column (it emits a debugging
        // message and silently rewrites it), so the column is added nullable-free without one.
        $field = new xmldb_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Drop the old unique index before removing its column, then index the hash instead.
        $oldindex = new xmldb_index('token_uix', XMLDB_INDEX_UNIQUE, ['token']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        // Migrate existing links so they keep working, then destroy the plaintext.
        $old = new xmldb_field('token', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $old)) {
            $rs = $DB->get_recordset_select('tool_flexaccess_campaign', "token <> ''", null, '', 'id, token');
            foreach ($rs as $record) {
                $DB->set_field(
                    'tool_flexaccess_campaign',
                    'tokenhash',
                    hash('sha256', (string) $record->token),
                    ['id' => $record->id]
                );
            }
            $rs->close();
            $dbman->drop_field($table, $old);
        }
        $newindex = new xmldb_index('tokenhash_uix', XMLDB_INDEX_UNIQUE, ['tokenhash']);
        if (!$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }
        upgrade_plugin_savepoint(true, 2026082425, 'tool', 'flexaccess');
    }
    if ($oldversion < 2026082427) {
        $dbman = $DB->get_manager();
        // Per-member record of why a conversion attempt failed, so a partial import is diagnosable
        // row by row instead of only as an aggregate count.
        $table = new xmldb_table('tool_flexaccess_batch_member');
        $field = new xmldb_field('converterror', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'converted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026082427, 'tool', 'flexaccess');
    }
    if ($oldversion < 2026082434) {
        $dbman = $DB->get_manager();
        // A resend must not destroy a link that already works: the newly minted token is parked here
        // and only replaces the live one once the mail has actually been delivered.
        $table = new xmldb_table('tool_flexaccess_invite');
        $field = new xmldb_field('pendingtokenhash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'tokenhash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026082434, 'tool', 'flexaccess');
    }
    return true;
}
