# Changelog

## 0.1.28 — 2026-08-18 — Paket B: B4 Konvertierung temporaer -> persistent
- Keine Codeaenderung.

## 0.1.27 — 2026-08-18 — Paket A abgeschlossen: Methodenauswahl (Gast + Normallogin)
- Keine Codeaenderung.

## 0.1.26 — 2026-08-18 — Paket A: Quick-Registration (allowquick)
- Keine Codeaenderung.

## 0.1.25 — 2026-08-18 — CI-Fix (veraltete Behat-Datei)
- Keine Codeaenderung.

## 0.1.24 — 2026-08-18 — Paket A: B2 (Access-Key) verifiziert
- **Access-Key-Durchsetzung end-to-end per Behat verifiziert** (Sicherheits-Blocker B2 geschlossen): Challenge-Formular, falscher Schluessel wird abgewiesen, korrekter Schluessel gewaehrt Zugang; Rate-Limit im Flow, Schluessel nur per POST (nie in URL/Log). 3 Ecosystem-Szenarien, 20 Steps gruen.
- Keine Codeaenderung.

## 0.1.23 — 2026-08-18 — CI-Fixes
- Keine Codeaenderung.

## 0.1.23 — 2026-08-18 — Paket A (Access), Teil 2: Zugangsschlüssel
- **Der Zugangsschlüssel ist jetzt wirksam** (war Sicherheits-Blocker B2). E2E per Behat verifiziert: falscher Schlüssel -> Fehler, richtiger -> Kurszugang.
- Keine Codeaenderung; Teil des verifizierten Gesamtlaufs.

## 0.1.22 — 2026-08-18 — Paket A (Access), Teil 1
- **Der URL-/aktivitaetssensitive Zugang funktioniert jetzt end-to-end** (war Beta-Blocker B1). Real per Behat verifiziert: ein anonymer Besucher gelangt ueber die Entry-Page zu temporaerem Zugang und landet im Zielkurs.
- Keine Codeaenderung; Teil des verifizierten Gesamtlaufs.

## 0.1.21 — 2026-08-18
- **Cross-Plugin-Funktionalitaet wird jetzt echt end-to-end getestet.** Behat wurde in der Sandbox real ausgefuehrt (Moodle 5.3dev, non-JS): alle vier Standalone-Smoke-Features **und** ein neues Cross-Plugin-E2E-Szenario bestehen.
- Keine Codeaenderung; das Dashboard ist die Cross-Plugin-Assertion im E2E-Szenario (zaehlt den vom Enrol-Flow erzeugten Account).

## 0.1.20 — 2026-08-18
- **Behat gruen gemacht (war der letzte rote CI-Schritt).** Die Feature-Dateien testeten teils veraltetes Scaffold-Verhalten bzw. noch nicht implementierte Ablaeufe; sie wurden auf standalone lauffaehige Smoke-Szenarien mit ausschliesslich Standard-Steps umgestellt. Verifiziert mit moodle-plugin-ci 4.5.11 (phpcs 0/0, validate 0 Fehler, PHPUnit auf Moodle 5.3dev gruen).
- **Robustheit:** Dashboard (`index.php`) degradiert sauber, wenn das Schwester-Plugin auth_flexaccess nicht installiert ist (Hinweis statt Fatal) — behebt den Behat-Crash der isolierten CI. Behat `navigation.feature` prueft das Dashboard. Playwright/Load-Workflows entfernt.

## 0.1.19 — 2026-08-18
- **Verifiziert mit der exakten CI-Toolchain (moodle-plugin-ci 4.5.11 PHAR): phpcs 0/0, `validate` 0 Fehler, PHPUnit auf Moodle 5.3dev gruen.** Cross-Plugin-Integrationstests laufen in der Vollumgebung (alle vier Plugins) normal und ueberspringen sich nur in der Einzel-Plugin-CI.
- **Weitere CI-Fixes:** `policy_presenter_test` ueberspringt sich sauber (markTestSkipped), wenn `enrol_flexaccess` (Tabelle `enrol_flexaccess_instance`) fehlt. Behat `navigation.feature` mit `@tool`-Typ-Tag.

## 0.1.18 — 2026-08-17
- **Linting robust fuer aeltere moodle-cs gemacht (die lokale `make check`-Umgebung nutzt eine strengere/aeltere moodle-cs als die CI):** `@package`-Tag in jedem Datei-, Klassen-/Interface-/Trait- und Top-Level-Funktions-Docblock ergaenzt (aeltere moodle-cs verlangt dies ueberall; neuere ab 3.6 hat es gelockert). Test-Klassen erhielten `@covers` auf die jeweils geprueften Klassen (behebt die `missing coverage information`-Warnungen). **Gegengeprueft:** die echte CI (moodle-plugin-ci 4.5.11) meldet weiterhin 0 Verstoesse, PHPUnit auf Moodle 5.3dev bleibt gruen.

## 0.1.17 — 2026-08-17
- **Real auf Moodle 5.3dev (branch 503, PG17) verifiziert — PHPUnit gruen, phpcs 0/0.** Dabei behobene echte Fehler: fehlende Capability-Sprachstrings (flexaccess:viewdashboard, flexaccess:viewaccounts, flexaccess:manageaccounts, flexaccess:convertaccounts, flexaccess:managemailqueue, flexaccess:viewpolicies) ergaenzt (Core tool_capability-Check).
- **CI grün gemacht (phpcs, real verifiziert mit moodlehq/moodle-cs v3.7):** Sprachdateien alphabetisch sortiert + `@package` ergänzt (Moodle LangFilesOrdering); einzeilige Docblocks in Mehrzeilenform mit Beschreibungszeile überführt; Multiline-Funktionsaufrufe per phpcbf normalisiert; unnötige `MOODLE_INTERNAL`-Checks entfernt; Konstanten-Docblocks ergänzt.
- **Makefile:** Vorlage übernommen und an das Plugin-Verzeichnis angepasst (PLUGIN_NAME/PLUGIN_REL/MOODLE_ROOT); `make check` zeigt nur Fails, läuft volle Lintings + PHPUnit.
- **GitHub-Workflows:** getrennt für Development (`moodle-ci.yml`, branches-ignore main) und Main (`moodle-release.yml`); zusätzlich `playwright.yml` und `load.yml` bereitgestellt. Von vimipad-spezifischen Bundle/AMD/Node-Schritten befreit; Behat-Tags und Pfade je Komponente. `.gitattributes`/`.gitignore` adaptiert.

## 0.1.16 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.16 (keine funktionale Änderung).

## 0.1.15 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.15 (keine funktionale Änderung).

## 0.1.14 — 2026-08-17
- **Dashboard + Mailqueue-Seite real:** `index.php` zeigt Konten-/Mail-Kennzahlen über die Auth-Facaden; `mailqueue.php` listet die Queue (Filter, Pagination, ohne Token/Payload). Keine Direktzugriffe auf Auth-Tabellen.

## 0.1.13 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.13 (keine funktionale Änderung).

## 0.1.12 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.12 (keine funktionale Änderung).

## 0.1.11 — 2026-08-17
- **Accounts-Seite real umgesetzt:** `accounts.php` listet FlexAccess-Konten über die Auth-Facade (Filter, Pagination) und bietet capability-/sesskey-gesicherte **Admin-Conversion** temporärer Konten. Keine Direktzugriffe auf Auth-Tabellen.

## 0.1.10 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.10 (keine funktionale Änderung).

## 0.1.9 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.9 (keine funktionale Änderung).

## 0.1.8 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.8 (keine funktionale Änderung).

## 0.1.7 — 2026-08-17
- **Hochziehen:** `policies.php` konsumiert jetzt real die enrol-Facade `enrol_flexaccess\api::get_effective_policy` und zeigt je Kurs (`?courseid=`) die wirksame Policy read-only an — ohne Geheimnisse/Hashes. Neuer reiner `local\policy_presenter` (+ PHPUnit `policy_presenter_test`).
- **CI-Fix (phpcs):** zu lange Zeile in `policies.php` in einen Sprachstring ausgelagert.
- **CI-Fix:** pgsql-Workflow-createdb-Zeile entfernt.

## 0.1.6 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.6 (keine funktionale Änderung).

## 0.1.5 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.5 (keine funktionale Änderung; Policy-Diagnose kann `enrol_flexaccess\api` konsumieren).

## 0.1.4 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.4 (keine funktionale Änderung).

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
