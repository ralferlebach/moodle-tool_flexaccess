# tool_flexaccess — Technisches Blueprint

## 1. Boundary

```text
/admin/tool/flexaccess/*
        |
        +--> auth_flexaccess\api
        |      accounts / conversion / suspension / deletion / mail queue
        |
        +--> enrol_flexaccess\api
               effective-policy trace / enrol diagnostics
```

No runtime dependency points back to `tool_flexaccess`.

## 2. Planned classes

- `local/account_table` or renderer-backed paginated presentation model.
- `local/account_action_controller` for POST orchestration only.
- `local/mailqueue_table` and `local/mail_action_controller`.
- `local/policy_diagnostic_presenter`.
- `local/reference_query` for strict reference-number search input.

Business rules remain in owning plugins.

## 3. Planned public service needs

`auth_flexaccess` must expose bounded search/detail DTOs plus explicit command methods for conversion, suspension, deletion scheduling, queue status/retry and rate status. `enrol_flexaccess` must expose a read-only policy explanation/trace method.

## 4. Capability map

- `tool/flexaccess:viewdashboard`
- `tool/flexaccess:viewaccounts`
- `tool/flexaccess:manageaccounts`
- `tool/flexaccess:convertaccounts`
- `tool/flexaccess:managemailqueue`
- `tool/flexaccess:viewpolicies`

## 5. Entry-point rules

Every page: `require_login()`, system context, exact capability, `moodle_url`, admin page layout, parameter validation. Every mutation: POST + `require_sesskey()` + command-specific capability + owning-service state validation + PRG redirect.

## 6. No direct writes

Forbidden in tool code: direct writes to `{user}`, `{user_enrolments}`, `{auth_flexaccess_*}`, `{enrol_flexaccess_*}`. Read access should also migrate to public query facades rather than coupling UI to foreign schemas.

## 7. Operational dashboard

Planned metrics: temporary users by state, accounts expiring soon, provisional users awaiting activation, queue depth, failed jobs, rolling-hour send count/capacity, enrolments expiring soon. Queries must be bounded/aggregated and index-backed.


## Öffentliche API-Verträge (Stand 1.0.0-RC1 / 2026082700)

Cross-Plugin-Zugriffe laufen ausschließlich über diese Fassaden; kein Plugin schreibt in die
Domänentabellen eines anderen.

### auth_flexaccess\api

| Methode | Zweck |
| --- | --- |
| `create_temporary_user()` | Temporäres Konto anlegen |
| `create_batch_account()` | Konto für eine anonyme Zugangsliste anlegen |
| `persist_temporary_user()` | Temporäres Konto personalisieren |
| `classify_user()` | Kontotyp bestimmen |
| `set_account_password()` | Passwort setzen; verweigert personalisierte Konten |
| `queue_mail()` | Mail mit fertigem Text einreihen |
| `queue_deferred_mail()` | Mail einreihen, deren Text erst bei Zustellung entsteht |
| `queue_deferred_mail_once()` | Wie oben, unter Lock gegen doppelte Einreihung |
| `deferred_mail_queued()` | Prüft, ob ein identischer Auftrag noch wartet |
| `rollback_batch_account()` | Kompensation nach abgebrochener Bereitstellung |
| `rollback_temporary_user()` | Temporäres Konto zurücknehmen |

**Entfallen in dieser Version:** `send_mail_now()` und `mail_worker::send_now()`. Sie umgingen
Warteschlange, Stundenlimit, Wiederholung und Überwachung. Sämtliche Mail läuft über die Queue.

### Zustände der Mailqueue

```text
queued → sent                      Zustellung erfolgreich, Quittung erfolgreich
queued → sent → ackpending         zugestellt, Quittung ausstehend (kein erneuter Versand)
                → ackfailed        zugestellt, Quittung dauerhaft fehlgeschlagen
queued → failed                    Zustellung endgültig fehlgeschlagen
```

Das Stundenlimit zählt über `timesent`, nicht über den Status: Eine zugestellte, aber unquittierte
Mail ist versendet und muss gegen das Kontingent zählen.

### enrol_flexaccess

`api::offers_quick_registration()`, `offers_magic_login()`, `offers_guest_access()`,
`local\enrol_service::admin_enrol()`, `reserve_and_enrol()`.

### Berechtigungsprüfungen

Kursbezogene Entscheidungen fragen ausschließlich im **Kurskontext**. Eine Prüfung gegen den
Systemkontext hebelt den Rollenwechsel aus, weil Moodle die Administrator-Umgehung nur für den
gewechselten Kontext aussetzt.

## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.
