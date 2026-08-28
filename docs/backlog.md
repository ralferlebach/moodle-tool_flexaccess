# Backlog

## Stand nach Session 002 (1.0.0-RC1 / 2026082700)

Erledigt in dieser Sitzung: sämtliche P0- und P1-Punkte der Reviews zu 0.9.35, 0.9.51 und 0.9.61.
Einzelheiten in `docs/sessions/session-002.md`.

Offen bis `MATURITY_STABLE`:

- [ ] Mailqueue-Adminsicht um `ackpending` und `ackfailed` erweitern, mit Reparaturweg für den
      Quittungsfehler und Aufbewahrungsregel für terminale Zeilen.
- [ ] jMeter: Durchsatzmessung und Rate-Limit-Prüfung als getrennte Szenarien.
- [ ] Browser-Abdeckung um den produktionsnahen E-Mail-Verifikationsablauf ergänzen.
- [ ] Optional: Reconciliation-Task für Konten, deren Kompensation fehlschlug.
- [ ] Abschließende Produktivfreigabe durch den Reviewer, danach `MATURITY_STABLE`.


- [ ] Implement public account query/search facade in auth_flexaccess.
- [ ] Implement account detail page and DTO.
- [ ] Implement admin conversion/suspension/deletion command orchestration.
- [ ] Implement queue/rate status and retry facades in auth_flexaccess.
- [ ] Implement mailqueue page with bounded pagination.
- [ ] Implement policy trace facade in enrol_flexaccess and diagnostics page.
- [ ] Emit/verify audit events for every mutation.
- [ ] Add capability-isolation and sesskey Behat coverage.


## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.

## Campaigns & follow-up (0.1.2 scope)
- [ ] Campaign schema + capability/sesskey-guarded CRUD UI (target, modes, window, key, capacity, lifetimes, follow-up rule).
- [ ] Invitation referencing a campaign; access link/code generation.
- [ ] Follow-up template + rules (enabled, after 1/2/3/7/custom days) consuming auth mail facade.
- [ ] Send-status/queue view for scheduled and sent follow-ups (read-only via auth facade).
- [ ] Replace null_provider with a full Privacy Provider; emit audit events for create/update/send.
- [ ] Ensure no direct writes to auth_flexaccess_*/enrol_flexaccess_*/user/user_enrolments.
