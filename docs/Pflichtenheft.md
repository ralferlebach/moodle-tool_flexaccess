# tool_flexaccess — Pflichtenheft

## Architektur

`tool_flexaccess` hängt von `auth_flexaccess` und `enrol_flexaccess` ab. Es konsumiert deren öffentliche Facades/DTOs; umgekehrt hängt kein FlexAccess-Runtime-Plugin vom Tool ab.

## Seiten

- `index.php`: Dashboard mit Account-/Queue-Kennzahlen und Warnzuständen.
- `accounts.php`: Filter/Search + Pagination.
- `account.php?id=...`: Detail und Aktionen; in der Stub-Phase noch nicht aktiv.
- `mailqueue.php`: Queue-/Throttle-Status, Filter, kontrollierter Retry.
- `policies.php`: read-only Policy-Diagnose für Kurs/Kategorie/User.

## Sicherheitsmodell

Systemkontext-Capabilities werden getrennt für Dashboard, Account-Lesen, Account-Verwaltung, Konvertierung, Mailqueue und Policy-Diagnose geprüft. Schreibaktionen erfolgen ausschließlich per POST mit `sesskey`, konkreter Objekt-ID und erneuter Zustandsprüfung im owning service.

Referenznummern sind Such-/Identifikationsmerkmale, keine Secrets. Sie berechtigen nie allein zu einer Mutation.

## Daten/Performance

Das Tool hat im MVP keine eigene DB-Tabelle. Alle Listen verwenden paginierte Query-Facades der owning plugins mit expliziten Sortier-/Filterfeldern. Keine N+1-Detailabfragen; benötigte Anzeigeinformationen werden batchweise/DTO-basiert geliefert.

## Privacy

Solange keine eigenen Nutzerdaten persistiert werden, `null_provider`. Angezeigte personenbezogene Daten sind durch `RISK_PERSONAL`-Capabilities geschützt. Export/Löschung bleiben Aufgabe der owning plugins.

## Tests

- PHPUnit für Filter-/Input-Helfer und Berechtigungslogik.
- Behat für Navigation, Capability-Trennung, Suche/Filter, POST-/sesskey-Aktionen und Fehlerpfade.
- Integrationstests müssen sicherstellen, dass das Tool keine fachlichen Tabellen direkt mutiert.


## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.
