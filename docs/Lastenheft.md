# tool_flexaccess — Lastenheft

## Ziel

Bereitstellung einer zentralen Moodle-Administrationsoberfläche für Betrieb, Diagnose und administrative Eingriffe im FlexAccess-Ökosystem.

## Muss-Anforderungen

- Suche nach FlexAccess-Accounts über Referenznummer, Moodle-User-ID, Accounttyp und Lifecycle-Status.
- Paginierte, bounded Listen; keine unbeschränkten Account-Abfragen.
- Detailansicht mit `accounttype`, `accountstate`, Quelle, Erzeugungs-, Ablauf- und Aktivierungszeitpunkten.
- Administrative Umwandlung `temporary user` → `authenticated user` unter Beibehaltung derselben `userid`.
- Suspendierung sowie kontrolliertes Anstoßen von Ablauf/Löschung.
- Mailqueue-Übersicht mit Status, Attempts, `nextrun`, erfolgreichem Versandzeitpunkt und verbleibender Stundenkapazität.
- Kontrollierter Retry fehlgeschlagener FlexAccess-Mails.
- Diagnose wirksamer System-/Kategorie-/Kurs-Policies einschließlich Herkunft/Override-Kette.
- Moodle-Capabilities für lesende und schreibende Operationsbereiche.
- Nachvollziehbarkeit über Events/Core-Logs.

## Abgrenzung

- keine eigene Account-/Token-/Mailqueue-/Policy-Tabelle,
- keine direkte Mutation fachlicher Tabellen der anderen Plugins,
- keine Beteiligung am Login-Runtime-Pfad,
- keine Abhängigkeit von `mod_flexaccess`,
- keine allgemeinen Availability-Funktionen.


## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.

## Scope-Erweiterung 0.1.2 (verbindlich)

`tool_flexaccess` erhält ein leichtgewichtiges **Campaign/Invitation-Modell** (Details: `../../docs/Arbeitsplanung.md`, `../../docs/Pflichtenheft.md` S18, ADR-014):

- **Campaign** = wiederverwendbarer Access-Provisioning-Datensatz (Zielkurs, erlaubte Modi, Access Window, optionaler Schlüssel, Kapazität, Account-/Einschreibungslaufzeit, Follow-up-Regel). Keine Marketing-Funktion.
- **Invitation** referenziert eine Campaign und erzeugt Access-Link/-Code (keine Doppelkonfiguration).
- **Follow-up-Konfiguration** (Template, Regeln, Versandstatus) über die auth-Facade; Mutationen der Fachdomäne weiterhin nur über auth/enrol-Facades.
- **Privacy:** mit eigenen Konfigurationsdaten wird der bisherige `null_provider` durch einen vollständigen Privacy Provider ersetzt; Audit-Events verpflichtend.
