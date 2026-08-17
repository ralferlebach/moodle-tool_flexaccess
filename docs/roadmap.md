# Roadmap

## 0.1.x scaffold
Admin navigation, capabilities, null privacy provider, documentation and no-op pages.

## 0.2.x accounts
Bounded account search/detail plus admin conversion/suspend/delete orchestration.

## 0.3.x operations
Mailqueue/throttle dashboard, retry operations and expiry warnings.

## 0.4.x diagnostics
Policy explanation, enrolment diagnostics, event/log links and bulk-safe operational tooling.


## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.
