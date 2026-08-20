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

$string['accountactions'] = 'Aktionen';
$string['accountconvert'] = 'In authentifiziert umwandeln';
$string['accountconverted'] = 'Das Konto wurde in einen authentifizierten Nutzer umgewandelt.';
$string['accountemail'] = 'E-Mail';
$string['accountname'] = 'Name';
$string['accountnotconverted'] = 'Das Konto konnte nicht umgewandelt werden (bereits authentifiziert oder nicht vorhanden).';
$string['accounts'] = 'Accounts';
$string['accountsearch'] = 'Name, E-Mail oder Referenznummer suchen';
$string['accountsnone'] = 'Keine FlexAccess-Konten entsprechen dem aktuellen Filter.';
$string['accountstate'] = 'Status';
$string['accountstate_active'] = 'Aktiv';
$string['accountstate_ephemeral'] = 'Flüchtig';
$string['accountstate_expired'] = 'Abgelaufen';
$string['accountstate_provisional'] = 'Vorläufig';
$string['accountstate_suspended'] = 'Gesperrt';
$string['accounttype'] = 'Typ';
$string['accounttype_authenticated'] = 'Authentifiziert';
$string['accounttype_temporary'] = 'Temporär';
$string['accounttypeauthenticated'] = 'Authentifizierte Nutzer';
$string['accounttypetemporary'] = 'Temporäre Nutzer';
$string['authunavailable'] = 'Das Plugin auth_flexaccess ist nicht installiert, daher sind keine Kontodaten verfügbar.';
$string['campaignbadgate'] = 'Diese Einladung ist eingeschränkt. Bitte prüfen Sie das Zugangspasswort oder verwenden Sie eine zulässige E-Mail-Adresse.';
$string['campaignbadwindow'] = 'Das Enddatum muss nach dem Startdatum liegen.';
$string['campaignclosed'] = 'Geschlossen';
$string['campaigncopylink'] = 'Link öffnen';
$string['campaigncourse'] = 'Zielkurs';
$string['campaigndeleted'] = 'Kampagne gelöscht.';
$string['campaignenabled'] = 'Aktiviert';
$string['campaignfrom'] = 'Verfügbar ab';
$string['campaigngate'] = 'Zugangs-Gate';
$string['campaigngatedomains'] = 'Erlaubte E-Mail-Domains';
$string['campaigngatepassword'] = 'Gemeinsames Passwort';
$string['campaignintro'] = 'Sie wurden eingeladen, {$a} beizutreten. Erstellen Sie unten ein Konto, um zu starten.';
$string['campaigninvitation'] = 'Kurs-Einladung';
$string['campaignlink'] = 'Einladungslink';
$string['campaignmax'] = 'Maximale Einlösungen';
$string['campaignmax_help'] = 'Die Anzahl erfolgreicher Anmeldungen, die diese Kampagne erlaubt. 0 = unbegrenzt.';
$string['campaignname'] = 'Kampagnenname';
$string['campaignnew'] = 'Neue Kampagne';
$string['campaignopen'] = 'Offen';
$string['campaignredemptions'] = 'Einlösungen';
$string['campaigns'] = 'Einladungskampagnen';
$string['campaigns_intro'] = 'Erstellen Sie teilbare Einladungslinks, über die sich Personen per FlexAccess-Schnellregistrierung selbst in einen Kurs einschreiben. Jeder Link kann zeitlich befristet, begrenzt und durch ein gemeinsames Passwort oder erlaubte E-Mail-Domains geschützt werden.';
$string['campaigns_none'] = 'Es wurden noch keine Kampagnen erstellt.';
$string['campaignsaved'] = 'Kampagne gespeichert.';
$string['campaignstatus'] = 'Status';
$string['campaignunavailable'] = 'Dieser Einladungslink ist ungültig oder nicht mehr verfügbar.';
$string['campaignuntil'] = 'Verfügbar bis';
$string['convertemail'] = 'E-Mail-Adresse';
$string['convertemail_help'] = 'Die echte E-Mail-Adresse der Person hinter diesem temporären Konto. Der Link zum Setzen des Passworts wird hierhin gesendet.';
$string['convertfirstname'] = 'Vorname';
$string['convertheading'] = 'Temporäres Konto umwandeln';
$string['convertintro'] = 'Geben Sie die echte E-Mail-Adresse der Person ein. Ihre Identität wird aktualisiert und sie erhält einen Link zum Setzen eines Passworts, um sich erneut anmelden zu können.';
$string['convertinvalidemail'] = 'Geben Sie eine gültige E-Mail-Adresse ein.';
$string['convertlastname'] = 'Nachname';
$string['convertstatus:emailtaken'] = 'Diese E-Mail-Adresse wird bereits von einem anderen Konto verwendet.';
$string['convertstatus:invalidemail'] = 'Die E-Mail-Adresse ist ungültig.';
$string['convertstatus:notapplicable'] = 'Dieses Konto ist kein temporäres Konto mehr.';
$string['dashaccounts'] = 'Konten';
$string['dashboard'] = 'Übersicht';
$string['dashexpired'] = 'Abgelaufen';
$string['dashmail'] = 'Mail-Warteschlange';
$string['dashnextdue'] = 'Nächste Mail fällig';
$string['dashnone'] = 'Keine geplant';
$string['dashprovisional'] = 'Provisorisch';
$string['dashtotal'] = 'Konten gesamt';
$string['flexaccess:convertaccounts'] = 'FlexAccess-Konten in authentifizierte Nutzer umwandeln';
$string['flexaccess:managecampaigns'] = 'FlexAccess-Einladungskampagnen verwalten';
$string['flexaccess:managemailqueue'] = 'FlexAccess-Mail-Warteschlange verwalten';
$string['flexaccess:managepolicies'] = 'FlexAccess-Kategorie-Policies verwalten';
$string['flexaccess:viewaccounts'] = 'FlexAccess-Konten ansehen';
$string['flexaccess:viewdashboard'] = 'FlexAccess-Dashboard ansehen';
$string['flexaccess:viewpolicies'] = 'FlexAccess-Policy-Diagnose ansehen';
$string['gatedomain'] = 'Erlaubte E-Mail-Domains';
$string['gatenone'] = 'Kein zusätzliches Gate';
$string['gatepassword'] = 'Gemeinsames Passwort';
$string['mailqueue'] = 'Mail-Warteschlange';
$string['managepolicies'] = 'Kategorie-Policies verwalten';
$string['managepolicies_edit'] = 'Kategorie-Überschreibung bearbeiten';
$string['managepolicies_intro'] = 'Legen Sie FlexAccess-Policy-Überschreibungen je Kurskategorie fest. Kategorien erben die Systemvorgaben, sofern hier nicht überschrieben; ein Kurs kann seine Kategorie überschreiben. Bleiben alle Felder auf „Erben", wird die Überschreibung entfernt.';
$string['managepolicies_none'] = 'Es sind noch keine Kategorie-Überschreibungen definiert.';
$string['mqattempts'] = 'Versuche';
$string['mqfailed'] = 'Fehlgeschlagen';
$string['mqnextrun'] = 'Nächster Lauf';
$string['mqnone'] = 'Keine Einträge entsprechen dem aktuellen Filter.';
$string['mqqueued'] = 'In Warteschlange';
$string['mqrecipient'] = 'Empfänger';
$string['mqsent'] = 'Gesendet';
$string['mqstatus'] = 'Status';
$string['mqsummary'] = 'In Warteschlange: {$a->queued}, gesendet: {$a->sent}, fehlgeschlagen: {$a->failed}.';
$string['mqtype'] = 'Typ';
$string['pluginname'] = 'FlexAccess-Administration';
$string['policies'] = 'Policy-Diagnose';
$string['policiesintro'] = 'Geplante Diagnosen umfassen den wirksamen Zugangsschlüssel-Bereich temporärer Nutzer (kein/System/Kurs), ohne Geheimnisse oder Hashes preiszugeben.';
$string['policiesnocourse'] = 'Hängen Sie ?courseid=NNN an diese Seite an, um die wirksame FlexAccess-Policy eines Kurses zu prüfen.';
$string['policyaccesskeyscope'] = 'Zugangsschlüssel-Bereich';
$string['policyallow'] = 'Erlauben';
$string['policyallowguest'] = 'Gastzugang angeboten';
$string['policyallownormallogin'] = 'Normaler Login angeboten';
$string['policyallowquick'] = 'Schnellregistrierung erlaubt';
$string['policyallowtemporary'] = 'Temporärer Zugang erlaubt';
$string['policyavailablefrom'] = 'Verfügbar ab';
$string['policyavailableuntil'] = 'Verfügbar bis';
$string['policycategory'] = 'Kurskategorie';
$string['policydeleted'] = 'Kategorie-Überschreibung entfernt.';
$string['policydeny'] = 'Verweigern';
$string['policyinherit'] = 'Erben';
$string['policymaxparticipants'] = 'Maximale Teilnehmerzahl';
$string['policyproperty'] = 'Eigenschaft';
$string['policyprovisionallifetime'] = 'Lebensdauer provisorischer Konten';
$string['policysaved'] = 'Kategorie-Policy gespeichert.';
$string['policytargetenabled'] = 'FlexAccess-Einschreibung aktiviert';
$string['policytemporarylifetime'] = 'Lebensdauer temporärer Konten';
$string['policyunbounded'] = 'Keine Grenze';
$string['policyunlimited'] = 'Unbegrenzt';
$string['policyvalue'] = 'Wert';
$string['policyvisibility'] = 'Teilnehmerlisten-Sichtbarkeit';
$string['policyvisibilityhide'] = 'Verbergen';
$string['policyvisibilityshow'] = 'Anzeigen';
$string['privacy:metadata'] = 'Das Administrationstool speichert keine eigenen personenbezogenen Daten. Personenbezogene Daten verbleiben in den fachlich zuständigen FlexAccess-Plugins.';
$string['stubaccounts'] = 'Stub für Accountsuche und -details. Produktivcode verwendet die öffentlichen Query- und Mutationsservices von auth_flexaccess.';
$string['stubdashboard'] = 'FlexAccess-Administrations-Stub. Betriebskennzahlen und Aktionen sind noch nicht implementiert.';
$string['stubmailqueue'] = 'Stub für die Mail-Warteschlange. Queue-Status, Stundenkontingent und Retry-Aktionen werden über Services von auth_flexaccess bereitgestellt.';
$string['stubpolicies'] = 'Stub für die Policy-Diagnose. Effektive Policy-Traces werden über Services von enrol_flexaccess bereitgestellt.';
