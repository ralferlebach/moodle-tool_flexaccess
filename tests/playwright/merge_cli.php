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
 *        | durablepair <flex|manual> | pendingcredential | suspendedue <courseid> | tempbinding
 *        | state <userid> <otheruserid>
 * Prints one JSON object.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');

[, $command, $first, $second] = array_pad($argv, 4, 0);
$kind = (string) $first;
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
    case 'durablepair':
        // Two permanent accounts of one person, each with its own course and grade: the source is a permanent
        // FlexAccess account ("flex") or a local account ("manual"), the survivor a permanent FlexAccess account.
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $now = \core\di::get(\core\clock::class)->time();
        $suffix = substr(md5((string) $now . random_string(8)), 0, 8);
        $out = ['suffix' => $suffix, 'password' => 'Keep-Flex-Pass!1'];
        foreach (['to' => 'flexaccess', 'from' => $kind === 'manual' ? 'manual' : 'flexaccess'] as $role => $auth) {
            $username = "durable{$role}{$suffix}";
            $user = create_user_record($username, $role === 'to' ? $out['password'] : 'Other-Pass!1', $auth);
            $DB->update_record('user', (object) ['id' => $user->id, 'firstname' => 'Durable', 'lastname' => "$role $suffix"]);
            if ($auth === 'flexaccess') {
                \auth_flexaccess\local\account_service::create_authenticated(
                    (int) $user->id,
                    \auth_flexaccess\local\account_service::generate_unique_reference()
                );
            }
            $course = create_course((object) ['fullname' => "Durable $role course $suffix", 'shortname' => "DUR$role$suffix",
                'category' => \core_course_category::get_default()->id]);
            \enrol_flexaccess\local\enrol_service::ensure_instance((int) $course->id);
            \enrol_flexaccess\local\enrol_service::admin_enrol((int) $course->id, (int) $user->id);
            $item = new \grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => "Work $role",
                'grademax' => 100], false);
            $item->insert();
            $item->update_final_grade((int) $user->id, $role === 'to' ? 70 : 55, 'test');
            $out[$role] = (int) $user->id;
            $out[$role . 'username'] = $username;
            $out[$role . 'course'] = (int) $course->id;
            $out[$role . 'item'] = (int) $item->id;
        }
        break;
    case 'pendingcredential':
        // An administratively converted account waiting for its password; its set-password mail is marked sent,
        // so a resend would queue a new one (tool #14).
        $now = \core\di::get(\core\clock::class)->time();
        $id = (int) \auth_flexaccess\api::create_temporary_user($now + DAYSECS);
        $email = 'pending.' . $id . '.' . random_string(6) . '@example.org';
        $out = ['id' => $id, 'status' => \auth_flexaccess\api::admin_convert($id, $email, 'Pending', 'Credential')];
        $DB->set_field('auth_flexaccess_mailqueue', 'status', 'sent', ['userid' => $id]);
        break;
    case 'suspendedue':
        // A FlexAccess participant whose enrolment is suspended: recovery would reactivate it.
        $now = \core\di::get(\core\clock::class)->time();
        $id = (int) \auth_flexaccess\api::create_temporary_user($now + DAYSECS, $first);
        \enrol_flexaccess\local\enrol_service::admin_enrol($first, $id);
        $ue = $DB->get_record_sql(
            "SELECT ue.id FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'flexaccess'
              WHERE ue.userid = :u AND e.courseid = :c",
            ['u' => $id, 'c' => $first],
            MUST_EXIST
        );
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $ue->id]);
        $out = ['id' => $id, 'ueid' => (int) $ue->id];
        break;
    case 'tempbinding':
        // A temporary account with an LTI binding, which the page may remove.
        $now = \core\di::get(\core\clock::class)->time();
        $id = (int) \auth_flexaccess\api::create_temporary_user($now + DAYSECS);
        $sub = 'method-' . $id;
        $bindingid = (int) $DB->insert_record('auth_lti_linked_login', (object) [
            'userid' => $id, 'issuer' => 'https://platform.example.invalid',
            'issuer256' => hash('sha256', 'https://platform.example.invalid'), 'sub' => $sub, 'sub256' => hash('sha256', $sub),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $out = ['id' => $id, 'bindingid' => $bindingid];
        break;
    case 'state':
        // Everything a changing action on the three pages could touch for one user (and a pair).
        $reg = \auth_flexaccess\local\merge_registry::get($first, $second);
        $out = [
            'mail' => $DB->count_records('auth_flexaccess_mailqueue', ['userid' => $first]),
            'tokens' => $DB->count_records('auth_flexaccess_token', ['userid' => $first]),
            'bindings' => $DB->count_records('auth_lti_linked_login', ['userid' => $first]),
            'enrolments' => array_values(array_map(
                static fn($r) => [(int) $r->status, (int) $r->timeend],
                $DB->get_records('user_enrolments', ['userid' => $first], 'id', 'id, status, timeend')
            )),
            'account' => $DB->get_record('auth_flexaccess_account', ['userid' => $first], 'accountstate, timeexpires, lockedby'),
            'suspended' => (int) $DB->get_field('user', 'suspended', ['id' => $first]),
            'registry' => $reg ? [$reg->status, (int) $reg->approvedby, (int) $reg->timemodified, (string) $reg->reasons] : null,
            'evidenceopen' => $DB->count_records('auth_flexaccess_mergeevid', ['fromuserid' => $first, 'timeacknowledged' => 0]),
        ];
        break;
    case 'registry':
        $row = \auth_flexaccess\local\merge_registry::get($first, $second);
        $out = ['status' => $row ? $row->status : null, 'reasons' => $row ? $row->reasons : null];
        break;
    case 'newbatch':
        // A complete access list of $second temporary accounts in course $first (tool #15, #16).
        \core\session\manager::set_user(get_admin());
        set_config('allowwidening', 1, 'enrol_flexaccess');
        $name = 'Integrity ' . random_string(6);
        $batchid = (int) \tool_flexaccess\local\batch::create($name, (int) $first, false, (int) $second)['batchid'];
        $out = ['id' => $batchid, 'usernames' => array_values(array_map(
            static fn($m) => (string) $m->username,
            \tool_flexaccess\local\batch::members($batchid)
        ))];
        break;
    case 'batchstate':
        // Everything issuing or importing could change: password hashes, accounts, issue time, audit events.
        $members = \tool_flexaccess\local\batch::members((int) $first);
        $out = [
            'timeissued' => (int) $DB->get_field('tool_flexaccess_batch', 'timeissued', ['id' => $first]),
            'status' => (string) $DB->get_field('tool_flexaccess_batch', 'status', ['id' => $first]),
            'users' => array_values(array_map(static function ($m) use ($DB) {
                $u = $DB->get_record('user', ['id' => $m->userid], 'username, password, email, auth, firstname, lastname');
                return [
                    $u->username, hash('sha256', $u->password), $u->email, $u->auth, $u->firstname, $u->lastname,
                    (int) $m->converted,
                ];
            }, $members)),
            'issued' => $DB->count_records('logstore_standard_log', [
                'eventname' => '\\tool_flexaccess\\event\\batch_credentials_issued', 'objectid' => $first,
            ]),
            'tasks' => $DB->count_records_select('task_adhoc', 'classname LIKE ?', ['%tool_flexaccess%convert%']),
        ];
        break;
    case 'importfixture':
        // A filled export of batch $first with one stray record in row $argv[4], written to the path $argv[3].
        $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $sheet->fromArray([['User', 'Pass', 'First', 'Last', 'Email', 'Profile', 'NewUser']], null, 'A1');
        $sheet->setCellValue('H1', \tool_flexaccess\local\batch_import::SCHEMA_VERSION);
        $r = 2;
        foreach (\tool_flexaccess\local\batch::members((int) $first) as $m) {
            $email = "imported{$r}." . random_string(5) . '@example.org';
            $sheet->fromArray([[$m->username, '', 'Imported', 'Person' . $r, $email]], null, "A$r");
            $r++;
        }
        $sheet->setCellValue('A' . (int) ($argv[4] ?? 50000), 'kurs-stray');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet->getParent()))->save((string) $argv[3]);
        $out = ['path' => (string) $argv[3]];
        break;
    default:
        cli_error('Unknown command.');
}
echo json_encode($out) . "\n";
