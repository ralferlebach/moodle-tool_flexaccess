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
 * Language strings.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accountactions'] = 'Actions';
$string['accountconvert'] = 'Convert to authenticated';
$string['accountconverted'] = 'The account was converted to an authenticated user.';
$string['accountemail'] = 'E-mail';
$string['accountname'] = 'Name';
$string['accountnotconverted'] = 'The account could not be converted (already authenticated or missing).';
$string['accounts'] = 'Accounts';
$string['accountsnone'] = 'No FlexAccess accounts match the current filter.';
$string['accountstate'] = 'State';
$string['accounttype'] = 'Type';
$string['accounttypeauthenticated'] = 'Authenticated users';
$string['accounttypetemporary'] = 'Temporary users';
$string['authunavailable'] = 'The auth_flexaccess plugin is not installed, so account data is unavailable.';
$string['dashaccounts'] = 'Accounts';
$string['dashboard'] = 'Dashboard';
$string['dashexpired'] = 'Expired';
$string['dashmail'] = 'Mail queue';
$string['dashnextdue'] = 'Next mail due';
$string['dashnone'] = 'None scheduled';
$string['dashprovisional'] = 'Provisional';
$string['dashtotal'] = 'Total accounts';
$string['flexaccess:convertaccounts'] = 'Convert FlexAccess accounts to authenticated users';
$string['flexaccess:manageaccounts'] = 'Manage FlexAccess accounts';
$string['flexaccess:managemailqueue'] = 'Manage the FlexAccess mail queue';
$string['flexaccess:viewaccounts'] = 'View FlexAccess accounts';
$string['flexaccess:viewdashboard'] = 'View the FlexAccess dashboard';
$string['flexaccess:viewpolicies'] = 'View FlexAccess policy diagnostics';
$string['mailqueue'] = 'Mail queue';
$string['mqattempts'] = 'Attempts';
$string['mqfailed'] = 'Failed';
$string['mqnextrun'] = 'Next run';
$string['mqnone'] = 'No mail-queue entries match the current filter.';
$string['mqqueued'] = 'Queued';
$string['mqrecipient'] = 'Recipient';
$string['mqsent'] = 'Sent';
$string['mqstatus'] = 'Status';
$string['mqsummary'] = 'Queued: {$a->queued}, sent: {$a->sent}, failed: {$a->failed}.';
$string['mqtype'] = 'Type';
$string['pluginname'] = 'FlexAccess administration';
$string['policies'] = 'Policy diagnostics';
$string['policiesintro'] = 'Planned diagnostics include the effective temporary-user access-key scope (none/system/course) without exposing secret values or hashes.';
$string['policiesnocourse'] = 'Append ?courseid=NNN to this page to inspect the effective FlexAccess policy for a course.';
$string['policyaccesskeyscope'] = 'Access-key scope';
$string['policyallowguest'] = 'Guest access offered';
$string['policyallownormallogin'] = 'Normal login offered';
$string['policyallowquick'] = 'Quick registration allowed';
$string['policyallowtemporary'] = 'Temporary access allowed';
$string['policyavailablefrom'] = 'Available from';
$string['policyavailableuntil'] = 'Available until';
$string['policymaxparticipants'] = 'Maximum participants';
$string['policyproperty'] = 'Property';
$string['policytargetenabled'] = 'FlexAccess enrolment enabled';
$string['policyunbounded'] = 'No limit';
$string['policyunlimited'] = 'Unlimited';
$string['policyvalue'] = 'Value';
$string['policyvisibility'] = 'Participant-list visibility';
$string['privacy:metadata'] = 'The administration tool stores no personal data of its own. Personal data remains with the owning FlexAccess plugins.';
$string['stubaccounts'] = 'Account search/detail scaffold. Production code will use the public auth_flexaccess query and mutation services.';
$string['stubdashboard'] = 'FlexAccess administration scaffold. Operational metrics and actions are not implemented yet.';
$string['stubmailqueue'] = 'Mail queue scaffold. Queue status, hourly capacity and retry actions will be provided by auth_flexaccess services.';
$string['stubpolicies'] = 'Policy diagnostics scaffold. Effective policy traces will be provided by enrol_flexaccess services.';
