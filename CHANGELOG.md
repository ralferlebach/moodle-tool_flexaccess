# Changelog

## 0.1.3 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.3 (keine funktionale Änderung; Campaigns/Follow-up folgen ab 0.5.x).

## 0.1.2 — 2026-08-17
- Scope-Erweiterung (Planung/Doku): leichtgewichtiges **Campaign/Invitation-Modell** und **Follow-up-Konfiguration** aufgenommen (ADR-014); Roadmap 0.5.x + Backlog ergänzt; Umstellung von null_provider auf vollständigen Privacy Provider vorgemerkt.

## 0.1.1 — 2026-08-17
- Version scheme moved to incremental `0.1.x` (release `0.1.1`).
- Added `$plugin->supported = [405, 502]` for matrix consistency; hard dependencies on `auth_flexaccess` + `enrol_flexaccess` confirmed (ADR-010).

## 0.1.0-alpha — 2026-08-17
- Initial architecture scaffold.

## 0.1.0-alpha3 - 2026-08-17
- Add system/course shared access-key requirement for temporary-user entry; secrets are hash-only.
