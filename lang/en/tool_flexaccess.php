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
