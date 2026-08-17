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

## 0.5.x campaigns & follow-up (Scope-Erweiterung 0.1.2)
Campaign/Invitation model (reusable access-provisioning records), follow-up mail configuration and
send-status monitoring. First tool-owned configuration data → replace null_provider with a full
Privacy Provider; add audit events. Domain mutations still only via auth/enrol facades. See
`../../docs/Arbeitsplanung.md` and ADR-014.
