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
 * Test helper for the consolidation journey (CI sites only): merge through tool_mergeusers and read results.
 *
 * Usage: php merge_cli.php merge <keepid> <removeid> | grade <itemid> <userid> | registry <removeid> <keepid>
 *        | approve <removeid> <keepid> | user <username> | linked <idp username> | pendingpair <courseid>
 * Prints one JSON object.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');

[, $command, $first, $second] = array_pad($argv, 4, 0);
$first = (int) $first;
$second = (int) $second;
// The merge runs as the site administrator, as in the merge users tool.
\core\session\manager::set_user(get_admin());
switch ($command) {
    case 'merge':
        [$success] = (new \tool_mergeusers\local\user_merger())->merge($first, $second);
        $row = \auth_flexaccess\local\merge_registry::get($second, $first);
        $out = ['success' => (bool) $success, 'status' => $row ? $row->status : null];
        break;
    case 'grade':
        $out = ['finalgrade' => $DB->get_field('grade_grades', 'finalgrade', ['itemid' => $first, 'userid' => $second])];
        break;
    case 'approve':
        // As the administrator would after checking the person's documents (journeys that test the page use it).
        $out = ['approved' => \auth_flexaccess\api::approve_merge(
            $first,
            $second,
            true,
            'documents',
            'Photo ID and student record checked at the help desk.'
        )];
        break;
    case 'user':
        // The account a username belongs to (first argument is the username here).
        $user = $DB->get_record('user', ['username' => $argv[2] ?? ''], 'id, auth, suspended');
        $out = $user ? ['id' => (int) $user->id, 'auth' => $user->auth, 'suspended' => (int) $user->suspended] : ['id' => 0];
        break;
    case 'linked':
        // The account an identity-provider username resolves to - what auth_oauth2 uses at login.
        $issuer = \core\oauth2\issuer::get_record(['name' => 'FlexAccess Test IdP']);
        $link = $issuer ? \auth_oauth2\api::match_username_to_user($argv[2] ?? '', $issuer) : false;
        $out = ['userid' => $link ? (int) $link->get('userid') : 0];
        break;
    case 'pendingpair':
        // A merge whose recorded FlexAccess enrolment could not be taken over (the kept account lost its
        // enrolment), plus a review conflict archived by an earlier version with a credential in it.
        $now = \core\di::get(\core\clock::class)->time();
        $suffix = substr(md5((string) $now . random_string(8)), 0, 8);
        $ids = [];
        foreach (['source' => 'Pending Source', 'keep' => 'Pending Keep'] as $role => $name) {
            $user = create_user_record('pending' . $role . $suffix, 'Pending-Pass!1', 'flexaccess');
            $DB->update_record('user', (object) ['id' => $user->id, 'firstname' => $name, 'lastname' => $suffix]);
            \auth_flexaccess\local\account_service::create_authenticated(
                (int) $user->id,
                \auth_flexaccess\local\account_service::generate_unique_reference()
            );
            \enrol_flexaccess\local\enrol_service::admin_enrol($first, (int) $user->id);
            $ids[$role] = (int) $user->id;
        }
        $instance = $DB->get_record('enrol', ['courseid' => $first, 'enrol' => 'flexaccess'], '*', MUST_EXIST);
        \auth_flexaccess\local\merge_evidence::capture_enrolments($ids['source'], $ids['keep']);
        $DB->delete_records('user_enrolments', ['enrolid' => $instance->id, 'userid' => $ids['source']]);
        enrol_get_plugin('flexaccess')->unenrol_user($instance, $ids['keep']);
        $DB->insert_record('auth_flexaccess_mergeevid', (object) [
            'fromuserid' => $ids['source'], 'touserid' => $ids['keep'], 'tablename' => 'grade_grades', 'kind' => 'review',
            'keyfields' => 'itemid', 'keyvalues' => '["9001"]', 'conflictkey' => 9001,
            'sourcerows' => json_encode([['id' => 1, 'itemid' => 9001, 'userid' => $ids['source'], 'finalgrade' => '55.00000',
                'token' => 'LEGACY-SECRET-PW-3141']]),
            'targetrows' => json_encode([['id' => 2, 'itemid' => 9001, 'userid' => $ids['keep'], 'finalgrade' => '70.00000']]),
            'timeacknowledged' => 0, 'timecreated' => $now,
        ]);
        \auth_flexaccess\api::approve_merge($ids['source'], $ids['keep'], true, 'documents', 'Photo ID checked at the desk.');
        $result = \auth_flexaccess\local\merge_service::reconcile($ids['source'], $ids['keep'], false, null, 'admin');
        $out = ['from' => $ids['source'], 'to' => $ids['keep'], 'name' => 'Pending Source ' . $suffix,
            'enrolid' => (int) $instance->id, 'status' => $result->status, 'reasons' => $result->reasons];
        break;
    case 'registry':
        $row = \auth_flexaccess\local\merge_registry::get($first, $second);
        $out = ['status' => $row ? $row->status : null, 'reasons' => $row ? $row->reasons : null];
        break;
    default:
        cli_error('Unknown command.');
}
echo json_encode($out) . "\n";
