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
$string['policiesintro'] = 'Geplante Diagnosen umfassen den wirksamen Zugangsschlüssel-Bereich temporärer Nutzer (kein/System/Kurs), ohne Geheimnisse oder Hashes preiszugeben.';
$string['policiesnocourse'] = 'Hängen Sie ?courseid=NNN an diese Seite an, um die wirksame FlexAccess-Policy eines Kurses zu prüfen.';
$string['policyproperty'] = 'Eigenschaft';
$string['policyvalue'] = 'Wert';
$string['policytargetenabled'] = 'FlexAccess-Einschreibung aktiviert';
$string['policyallowtemporary'] = 'Temporärer Zugang erlaubt';
$string['policyallowquick'] = 'Schnellregistrierung erlaubt';
$string['policyallowguest'] = 'Gastzugang angeboten';
$string['policyallownormallogin'] = 'Normaler Login angeboten';
$string['policyavailablefrom'] = 'Verfügbar ab';
$string['policyavailableuntil'] = 'Verfügbar bis';
$string['policymaxparticipants'] = 'Maximale Teilnehmerzahl';
$string['policyvisibility'] = 'Teilnehmerlisten-Sichtbarkeit';
$string['policyaccesskeyscope'] = 'Zugangsschlüssel-Bereich';
$string['policyunbounded'] = 'Keine Grenze';
$string['policyunlimited'] = 'Unbegrenzt';
$string['accountname'] = 'Name';
$string['accountemail'] = 'E-Mail';
$string['accounttype'] = 'Typ';
$string['accountstate'] = 'Status';
$string['accountactions'] = 'Aktionen';
$string['accountconvert'] = 'In authentifiziert umwandeln';
$string['accountconverted'] = 'Das Konto wurde in einen authentifizierten Nutzer umgewandelt.';
$string['accountnotconverted'] = 'Das Konto konnte nicht umgewandelt werden (bereits authentifiziert oder nicht vorhanden).';
$string['accountsnone'] = 'Keine FlexAccess-Konten entsprechen dem aktuellen Filter.';
$string['dashaccounts'] = 'Konten';
$string['dashmail'] = 'Mail-Warteschlange';
$string['dashtotal'] = 'Konten gesamt';
$string['dashprovisional'] = 'Provisorisch';
$string['dashexpired'] = 'Abgelaufen';
$string['dashnextdue'] = 'Nächste Mail fällig';
$string['dashnone'] = 'Keine geplant';
$string['accounttypetemporary'] = 'Temporäre Nutzer';
$string['accounttypeauthenticated'] = 'Authentifizierte Nutzer';
$string['mqsummary'] = 'In Warteschlange: {$a->queued}, gesendet: {$a->sent}, fehlgeschlagen: {$a->failed}.';
$string['mqrecipient'] = 'Empfänger';
$string['mqtype'] = 'Typ';
$string['mqstatus'] = 'Status';
$string['mqattempts'] = 'Versuche';
$string['mqnextrun'] = 'Nächster Lauf';
$string['mqqueued'] = 'In Warteschlange';
$string['mqsent'] = 'Gesendet';
$string['mqfailed'] = 'Fehlgeschlagen';
$string['mqnone'] = 'Keine Einträge entsprechen dem aktuellen Filter.';
