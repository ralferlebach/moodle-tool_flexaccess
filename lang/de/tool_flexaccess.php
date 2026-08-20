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
$string['batch:accounttype'] = 'Account-Typ';
$string['batch:accounttype_help'] = 'Vorläufige Accounts sind eingeschränkt und laufen ab; dauerhafte Accounts sind vollwertige Accounts (ihre E-Mail ist ein Platzhalter, bis Sie sie personalisieren).';
$string['batch:col_email'] = 'E-Mail';
$string['batch:col_firstname'] = 'Vorname';
$string['batch:col_lastname'] = 'Nachname';
$string['batch:col_newusername'] = 'Neue Nutzerkennung (optional)';
$string['batch:col_password'] = 'Passwort';
$string['batch:col_profilefields'] = 'Profilfelder';
$string['batch:col_username'] = 'Nutzerkennung';
$string['batch:convert'] = 'In dauerhafte Accounts umwandeln';
$string['batch:convert_intro'] = 'Laden Sie die für diesen Stapel heruntergeladene Export-Datei hoch, ausgefüllt mit Vorname, Nachname und E-Mail (und optional einer neuen Nutzerkennung) je Account. Jeder zugeordnete Account wird zu einem vollwertigen, dauerhaften Account und erhält eine Passwort-setzen-E-Mail an seine neue Adresse.';
$string['batch:convertsummary'] = '{$a->converted} Account(s) umgewandelt, {$a->skipped} übersprungen.';
$string['batch:count'] = 'Anzahl Accounts';
$string['batch:countrange'] = 'Geben Sie eine Zahl zwischen 1 und 1000 ein.';
$string['batch:course'] = 'Zielkurs';
$string['batch:create'] = 'Stapel erstellen';
$string['batch:created'] = '{$a} Account(s) erstellt.';
$string['batch:downloadall'] = 'Zugangsdaten erzeugen & alles herunterladen (ZIP)';
$string['batch:downloadcards'] = 'Nur Login-Kärtchen';
$string['batch:downloadexcel'] = 'Nur Excel';
$string['batch:downloadnote'] = 'Jeder Download vergibt frische Passwörter, sodass alle Dateien eines Downloads dieselben Zugangsdaten enthalten. Speichern Sie sie, bevor Sie die Seite verlassen.';
$string['batch:downloadopen'] = 'Öffnen / herunterladen';
$string['batch:downloadpdflist'] = 'Nur PDF-Liste';
$string['batch:err_convert'] = '„{$a->username}" konnte nicht umgewandelt werden ({$a->status}).';
$string['batch:err_noemail'] = 'Keine E-Mail für „{$a}" angegeben; übersprungen.';
$string['batch:err_notfound'] = 'Kein passender Account in diesem Stapel für Nutzerkennung „{$a}".';
$string['batch:err_rename'] = '„{$a->username}" umgewandelt, aber Umbenennung in „{$a->target}" nicht möglich (belegt oder ungültig); Nutzerkennung bleibt die E-Mail.';
$string['batch:expiry'] = 'Läuft ab nach';
$string['batch:expiry_help'] = 'Wie lange die vorläufigen Accounts gültig bleiben. Leer lassen für den Standardwert. Bei dauerhaften Accounts ohne Wirkung.';
$string['batch:name'] = 'Stapelname';
$string['batch:passwordlength'] = 'Passwortlänge';
$string['batch:rule_email'] = 'E-Mail-Adresse verwenden';
$string['batch:rule_emaillocal'] = 'Teil der E-Mail vor dem @ verwenden';
$string['batch:rule_firstlast'] = 'vorname.nachname';
$string['batch:rule_keep'] = 'Erzeugte Nutzerkennung beibehalten';
$string['batch:summary'] = '{$a->count} {$a->type}-Account(s), eingeschrieben in {$a->course}.';
$string['batch:type_permanent'] = 'Dauerhaft / vollwertig';
$string['batch:type_temporary'] = 'Vorläufig / eingeschränkt';
$string['batch:uploadfile'] = 'Ausgefüllter Export (.xlsx)';
$string['batch:usernameprefix'] = 'Präfix der Nutzerkennung';
$string['batch:usernameprefix_help'] = 'Jede Nutzerkennung besteht aus diesem Präfix und einem zufälligen Suffix, z. B. „kurs-ab3k7m".';
$string['batch:usernamerule'] = 'Regel für Nutzerkennung';
$string['batch:usernamerule_help'] = 'Wie die neue Nutzerkennung gewählt wird, wenn die Spalte „Neue Nutzerkennung" in einer Zeile leer bleibt. Ein Wert in dieser Spalte hat immer Vorrang.';
$string['batches'] = 'Account-Stapel';
$string['batches_intro'] = 'Erzeugen Sie eine Liste von Kurs-Accounts mit zufälligen Nutzerkennungen und Passwörtern, eingeschrieben in einen Kurs. Laden Sie die Zugangsdaten als Excel-Tabelle, druckbare Liste und druckbare Login-Kärtchen herunter.';
$string['batches_none'] = 'Es wurden noch keine Account-Stapel erstellt.';
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
$string['flexaccess:managebatches'] = 'FlexAccess-Kurs-Accounts stapelweise bereitstellen';
$string['flexaccess:managecampaigns'] = 'FlexAccess-Einladungskampagnen verwalten';
$string['flexaccess:manageinvitations'] = 'FlexAccess-Einladungen verwalten';
$string['flexaccess:managemailqueue'] = 'FlexAccess-Mail-Warteschlange verwalten';
$string['flexaccess:managepolicies'] = 'FlexAccess-Kategorie-Policies verwalten';
$string['flexaccess:viewaccounts'] = 'FlexAccess-Konten ansehen';
$string['flexaccess:viewdashboard'] = 'FlexAccess-Dashboard ansehen';
$string['flexaccess:viewpolicies'] = 'FlexAccess-Policy-Diagnose ansehen';
$string['gatedomain'] = 'Erlaubte E-Mail-Domains';
$string['gatenone'] = 'Kein zusätzliches Gate';
$string['gatepassword'] = 'Gemeinsames Passwort';
$string['invitations'] = 'Einladungen';
$string['invitations_intro'] = 'Versenden Sie personengebundene Einmal-Einladungen an bestimmte E-Mail-Adressen. Jede Einladung kann gesendet, erneut gesendet, erinnert und widerrufen werden und wird eingelöst, sobald die empfangende Person sich registriert.';
$string['invitations_none'] = 'Es wurden noch keine Einladungen erstellt.';
$string['invite:actions'] = 'Aktionen';
$string['invite:course'] = 'Zielkurs';
$string['invite:create'] = 'Einladungen erstellen';
$string['invite:created'] = '{$a} Einladung(en) erstellt.';
$string['invite:emailbody'] = 'Sie wurden zu einem Kurs eingeladen. Erstellen Sie zum Annehmen Ihr Konto über diesen Einmal-Link (er kann ablaufen): {$a}';
$string['invite:emails'] = 'E-Mail-Adressen der Empfänger';
$string['invite:emails_help'] = 'Eine Adresse pro Zeile (oder durch Komma/Semikolon getrennt). Jede gültige Adresse wird zu einer eigenen Einmal-Einladung.';
$string['invite:emailsubject'] = 'Sie sind zu einem Kurs eingeladen';
$string['invite:expiry'] = 'Läuft ab nach';
$string['invite:expiry_help'] = 'Wie lange jede Einladung nach Erstellung gültig bleibt. Leer lassen für keinen Ablauf.';
$string['invite:intro'] = 'Sie wurden als {$a} zu diesem Kurs eingeladen. Erstellen Sie unten Ihr Konto, um anzunehmen.';
$string['invite:noemails'] = 'Bitte geben Sie mindestens eine gültige E-Mail-Adresse ein.';
$string['invite:recipient'] = 'Empfänger';
$string['invite:remind'] = 'Erinnern';
$string['invite:reminded'] = 'Erinnerung eingereiht.';
$string['invite:reminderbody'] = 'Dies ist eine Erinnerung, dass Ihre Kurs-Einladung noch aussteht. Erstellen Sie zum Annehmen Ihr Konto über diesen Link: {$a}';
$string['invite:remindersubject'] = 'Erinnerung: Ihre Kurs-Einladung wartet';
$string['invite:remindfailed'] = 'Für diese Einladung konnte keine Erinnerung gesendet werden.';
$string['invite:resend'] = 'Erneut senden';
$string['invite:revoke'] = 'Widerrufen';
$string['invite:revoked'] = 'Einladung widerrufen.';
$string['invite:send'] = 'Senden';
$string['invite:sendnow'] = 'Sofort senden';
$string['invite:sent'] = 'Einladung zum Versand eingereiht.';
$string['invite:sentcol'] = 'Gesendet';
$string['invite:status_accepted'] = 'Angenommen';
$string['invite:status_pending'] = 'Ausstehend';
$string['invite:status_reserved'] = 'In Bearbeitung';
$string['invite:status_revoked'] = 'Widerrufen';
$string['invite:statuscol'] = 'Status';
$string['invite:title'] = 'Kurs-Einladung';
$string['invite:unavailable'] = 'Dieser Einladungslink ist ungültig, wurde bereits verwendet oder ist abgelaufen.';
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
$string['policyvisibilityinherit'] = 'Erben';
$string['policyvisibilityshow'] = 'Anzeigen';
$string['privacy:metadata'] = 'Das Administrationstool speichert keine eigenen personenbezogenen Daten. Personenbezogene Daten verbleiben in den fachlich zuständigen FlexAccess-Plugins.';
$string['privacy:metadata:batch'] = 'Informationen zu bereitgestellten Account-Stapeln.';
$string['privacy:metadata:batch:name'] = 'Der Stapelname.';
$string['privacy:metadata:batch:timecreated'] = 'Der Erstellungszeitpunkt des Stapels.';
$string['privacy:metadata:batch:usermodified'] = 'Die Person, die den Stapel erstellt hat.';
$string['privacy:metadata:batchmember'] = 'Die Accounts, die zu einem bereitgestellten Stapel gehören.';
$string['privacy:metadata:batchmember:userid'] = 'Die Nutzer-ID des Stapel-Accounts.';
$string['privacy:metadata:batchmember:username'] = 'Die Nutzerkennung des Stapel-Accounts.';
$string['privacy:metadata:campaign'] = 'Informationen zu FlexAccess-Einladungskampagnen.';
$string['privacy:metadata:campaign:name'] = 'Der Kampagnenname.';
$string['privacy:metadata:campaign:timemodified'] = 'Der Zeitpunkt der letzten Änderung der Kampagne.';
$string['privacy:metadata:campaign:usermodified'] = 'Die Administratorin/der Administrator, die/der die Kampagne zuletzt geändert hat.';
$string['privacy:metadata:invite'] = 'Informationen zu personengebundenen FlexAccess-Einladungen.';
$string['privacy:metadata:invite:email'] = 'Die E-Mail-Adresse, an die die Einladung gesendet wurde.';
$string['privacy:metadata:invite:timecreated'] = 'Der Zeitpunkt der Erstellung der Einladung.';
$string['privacy:metadata:invite:usermodified'] = 'Die Person, die die Einladung erstellt oder zuletzt geändert hat.';
$string['task:invitationreminders'] = 'FlexAccess-Einladungserinnerungen senden';
