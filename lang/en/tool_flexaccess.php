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
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'FlexAccess administration';
$string['dashboard'] = 'Dashboard';
$string['accounts'] = 'Accounts';
$string['mailqueue'] = 'Mail queue';
$string['policies'] = 'Policy diagnostics';
$string['stubdashboard'] = 'FlexAccess administration scaffold. Operational metrics and actions are not implemented yet.';
$string['stubaccounts'] = 'Account search/detail scaffold. Production code will use the public auth_flexaccess query and mutation services.';
$string['stubmailqueue'] = 'Mail queue scaffold. Queue status, hourly capacity and retry actions will be provided by auth_flexaccess services.';
$string['stubpolicies'] = 'Policy diagnostics scaffold. Effective policy traces will be provided by enrol_flexaccess services.';
$string['privacy:metadata'] = 'The administration tool stores no personal data of its own. Personal data remains with the owning FlexAccess plugins.';
$string['policiesintro'] = 'Planned diagnostics include the effective temporary-user access-key scope (none/system/course) without exposing secret values or hashes.';
$string['policiesnocourse'] = 'Append ?courseid=NNN to this page to inspect the effective FlexAccess policy for a course.';
$string['policyproperty'] = 'Property';
$string['policyvalue'] = 'Value';
$string['policytargetenabled'] = 'FlexAccess enrolment enabled';
$string['policyallowtemporary'] = 'Temporary access allowed';
$string['policyallowquick'] = 'Quick registration allowed';
$string['policyallowguest'] = 'Guest access offered';
$string['policyallownormallogin'] = 'Normal login offered';
$string['policyavailablefrom'] = 'Available from';
$string['policyavailableuntil'] = 'Available until';
$string['policymaxparticipants'] = 'Maximum participants';
$string['policyvisibility'] = 'Participant-list visibility';
$string['policyaccesskeyscope'] = 'Access-key scope';
$string['policyunbounded'] = 'No limit';
$string['policyunlimited'] = 'Unlimited';
$string['accountname'] = 'Name';
$string['accountemail'] = 'E-mail';
$string['accounttype'] = 'Type';
$string['accountstate'] = 'State';
$string['accountactions'] = 'Actions';
$string['accountconvert'] = 'Convert to authenticated';
$string['accountconverted'] = 'The account was converted to an authenticated user.';
$string['accountnotconverted'] = 'The account could not be converted (already authenticated or missing).';
$string['accountsnone'] = 'No FlexAccess accounts match the current filter.';
$string['dashaccounts'] = 'Accounts';
$string['dashmail'] = 'Mail queue';
$string['dashtotal'] = 'Total accounts';
$string['dashprovisional'] = 'Provisional';
$string['dashexpired'] = 'Expired';
$string['dashnextdue'] = 'Next mail due';
$string['dashnone'] = 'None scheduled';
$string['accounttypetemporary'] = 'Temporary users';
$string['accounttypeauthenticated'] = 'Authenticated users';
$string['mqsummary'] = 'Queued: {$a->queued}, sent: {$a->sent}, failed: {$a->failed}.';
$string['mqrecipient'] = 'Recipient';
$string['mqtype'] = 'Type';
$string['mqstatus'] = 'Status';
$string['mqattempts'] = 'Attempts';
$string['mqnextrun'] = 'Next run';
$string['mqqueued'] = 'Queued';
$string['mqsent'] = 'Sent';
$string['mqfailed'] = 'Failed';
$string['mqnone'] = 'No mail-queue entries match the current filter.';
