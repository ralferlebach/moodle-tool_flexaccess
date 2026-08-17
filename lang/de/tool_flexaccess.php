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
$string['pluginname'] = 'FlexAccess-Administration';
$string['dashboard'] = 'Übersicht';
$string['accounts'] = 'Accounts';
$string['mailqueue'] = 'Mail-Warteschlange';
$string['policies'] = 'Policy-Diagnose';
$string['stubdashboard'] = 'FlexAccess-Administrations-Stub. Betriebskennzahlen und Aktionen sind noch nicht implementiert.';
$string['stubaccounts'] = 'Stub für Accountsuche und -details. Produktivcode verwendet die öffentlichen Query- und Mutationsservices von auth_flexaccess.';
$string['stubmailqueue'] = 'Stub für die Mail-Warteschlange. Queue-Status, Stundenkontingent und Retry-Aktionen werden über Services von auth_flexaccess bereitgestellt.';
$string['stubpolicies'] = 'Stub für die Policy-Diagnose. Effektive Policy-Traces werden über Services von enrol_flexaccess bereitgestellt.';
$string['privacy:metadata'] = 'Das Administrationstool speichert keine eigenen personenbezogenen Daten. Personenbezogene Daten verbleiben in den fachlich zuständigen FlexAccess-Plugins.';
