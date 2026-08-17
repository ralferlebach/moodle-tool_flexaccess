# Backlog

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
