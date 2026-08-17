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
- keine Abhängigkeit von `mod_flexaccessactivation`,
- keine allgemeinen Availability-Funktionen.


## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.
