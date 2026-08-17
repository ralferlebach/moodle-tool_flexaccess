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


## Zugangsschlüssel-Diagnose

Das Tool darf für einen Kurs anzeigen, ob kein, der System- oder ein Kurs-Zugangsschlüssel wirksam ist. Es darf weder Klartext noch gespeicherten Hash anzeigen, exportieren oder über seine DTOs erhalten. Konfiguration/Rotation bleibt im owning `enrol_flexaccess` bzw. der Enrolment-Instanz.
