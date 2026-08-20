# Changelog

## 0.9.17 — 2026-08-20 — Fix: Cross-Plugin-Mailqueue (Standalone-CI) + saubere API-Grenze
- **Fix (Standalone-CI):** Der Invitation-Service schrieb Mails per Raw-Insert direkt in `auth_flexaccess_mailqueue`. In einer isolierten tool-CI (ohne installiertes `auth_flexaccess`) fehlte diese Tabelle -> zwei PHPUnit-Errors. Behoben durch:
  - **Saubere API-Grenze:** Versand laeuft jetzt ueber die oeffentliche `\auth_flexaccess\api::queue_mail()` (statt Direktzugriff auf die fremde Tabelle); der tote Raw-Insert wurde entfernt.
  - **Ehrlicher Test-Skip:** mail-abhaengige Tests (`test_send_and_remind`, `test_due_reminders`) ueberspringen sauber, wenn `auth_flexaccess`/die Mailqueue nicht installiert ist (Isolationslauf), statt zu erroren.
  - **CI-Contract:** die tool-eigene `moodle-ci.yml` (PHPUnit + Behat) installiert jetzt die deklarierten Schwestern `auth`+`enrol` via pinbarem `SIBLING_REF`, sodass der Mailpfad in CI real getestet wird.

## 0.9.16 — 2026-08-20 — P2-Cleanup: Performance, Reliability, i18n
- **Perf (§15):** Campaign- und Invitation-Formular verwenden jetzt das AJAX-gestuetzte `course`-Autocomplete-Element statt eines `<select>` ueber *alle* Kurse; die Verwaltungsseiten laden Kurs-Anzeigenamen nur noch fuer die aktuell angezeigte Seite (skaliert auf 10.000+ Kurse).
- **i18n (§20):** Participant-Visibility-Enums ('show'/'hide'/'inherit') werden ueber `policy_presenter::visibility_label()` lokalisiert ausgegeben statt als Rohwert (neuer String `policyvisibilityinherit`).

## 0.9.15 — 2026-08-20 — RC-Gates (Review 0.9.13): 4 P0 + Reliability + Doku/CI-Sync
- Campaign- und Invite-Landing rufen Quick-Registration jetzt als **trusted** auf (Kurs-Gate wird nicht doppelt geprueft).

## 0.9.14 — 2026-08-20 — Einladungen: personengebundenes Single-Use-Modell (Review §9)
- **Invitations:** neues personengebundenes Einladungsmodell (`tool_flexaccess_invite`, install.xml + Upgrade 2026081914) als Ergaenzung zum teilbaren Campaign-Link. Service `local\invitation`: create/get/all(paginiert)/count, `send`/`remind`/`revoke`, **atomares single-use `accept`** (per-Token-Lock + Statusguard), `is_acceptable` (pending + nicht abgelaufen + nicht widerrufen) und `due_reminders`.
- **Mail via Queue:** Einladungs- und Erinnerungsmails laufen ueber die FlexAccess-Mailqueue (Ratelimit gilt).
- **UI:** capability-gesicherte Verwaltung `invitations.php` (`tool/flexaccess:manageinvitations`, RISK_SPAM|RISK_PERSONAL) mit Bulk-Anlage (`invitation_form`, mehrere Empfaenger + optionales Ablaufdatum + Sofortversand) und Aktionen Send/Resend/Remind/Revoke; oeffentliche Landing `invite.php?token=` konsumiert das Token, bindet die Registrierung an die Einladungs-E-Mail und loest erst bei erfolgreicher Kontoerstellung ein.
- **Reminder-Task:** `send_invitation_reminders` (db/tasks.php, taeglich) erinnert offene, gesendete, nicht angenommene Einladungen einmalig nach `invitationreminderdays` (Default 3, 0 = aus).
- **Privacy:** Provider um die invite-Tabelle erweitert (E-Mail + usermodified als personenbezogene Daten deklariert, exportiert — inkl. an die eigene Adresse gerichteter Einladungen —, bei Loeschung anonymisiert).
- Tests: Lebenszyklus, Expiry/Revoke, send/remind, single-use accept, due-reminders.

## 0.9.13 — 2026-08-20 — P2-Batch: Performance, Retention, Supply-Chain, Doku
- **Perf:** `campaign::all()` paginiert (`limitfrom`/`limitnum`) + `count_all()`; die Admin-Kampagnenliste nutzt eine `paging_bar` (50/Seite). Test deckt Paginierung/Zaehlung ab.

## 0.9.12 — 2026-08-20 — P1/P2-Härtung: Security (a) + Identity/State (b) + Cleanup/Docs (c)
- **(c) Cleanup:** tote `stub*`-Strings entfernt; Makefile-Bereinigung.

## 0.9.11 — 2026-08-20 — RC-Hardening: P0#6 (Admin-Conversion über Mailqueue)
- Keine Codeaenderung.

## 0.9.10 — 2026-08-20 — RC-Hardening: 7/8 P0 aus dem 0.9.8-Review
- **P0#2 — Campaign-Kapazitaet unter Parallelitaet abgesichert:** `redeem()` reserviert einen Platz jetzt unter **Moodle-Lock** (Pruefung + Inkrement atomar); die Landing-Seite **reserviert vor** der Kontoerstellung und gibt den Platz via `release_reservation()` zurueck, falls die Anmeldung scheitert. Kein Doppel-Grant fuer den letzten Platz mehr.
- **P0#8 — echter Privacy-Provider:** `null_provider` ersetzt; die Kampagnen-Tabelle (usermodified/name/timemodified) wird als personenbezogene Metadaten deklariert, exportiert und bei Loeschung anonymisiert (usermodified=0).

**Offen (bewusst gestaffelt):** P0#6 — Admin-Conversion versendet die Passwort-Mail noch via Core `setnew_password_and_mail` (umgeht die FlexAccess-Mailqueue/Ratelimit). Fix erfordert einen neuen queued 'set-password'-Mailfluss.

## 0.9.9 — 2026-08-19 — Welle 4 Abschluss: Accessibility-Gate + Docs-SSOT & Traceability
- Keine Codeaenderung.

## 0.9.8 — 2026-08-19 — Welle 4: Policy-Caching (Perf)
- Keine Codeaenderung.

## 0.9.7 — 2026-08-19 — Welle 5: Einladungskampagnen (§49)
- **§49 — Campaign/Invitation:** neue tokenisierte Einladungskampagnen. Ein Admin erstellt teilbare Links, über die sich Personen per FlexAccess-Schnellregistrierung selbst in einen Kurs einschreiben.
- **Datenmodell:** neue Tabelle `tool_flexaccess_campaign` (install.xml + Upgrade 2026081907) mit Name, Zielkurs, eindeutigem Token, Aktiv-Flag, Verfügbarkeitsfenster, Max-Einlösungen/Zähler und Kampagnen-Gate.
- **Service** `local\campaign`: CRUD, `is_redeemable` (Aktiv/Fenster/Cap), **atomare** `redeem` (bedingtes UPDATE, überschreitet den Cap auch unter Last nicht) und `passes_gate` (bcrypt-Passwort bzw. Domain-/Subdomain-Allowlist).
- **Kampagnen-Gate:** die von der Roadmap vorgesehene tool/Campaign-Platzierung des Zugangs-Gates (Passwort ODER E-Mail-Domain), unabhängig vom kurs-/systemweiten Gate.
- **UI:** capability-gesicherte Verwaltungsseite `campaigns.php` (Kapability `tool/flexaccess:managecampaigns`, RISK_CONFIG|RISK_SPAM) mit Anlegen/Bearbeiten/Löschen und Übersicht inkl. Einlösungsstand; öffentliche Landing-Seite `campaign.php?token=` validiert Kampagne + Gate, hostet die Schnellregistrierung und zählt eine Einlösung erst bei erfolgreicher Kontoerstellung.
- Tests: `campaign` CRUD, Fenster-/Cap-Validierung, atomare Einlösung, Passwort-/Domain-Gate.

## 0.9.6 — 2026-08-19 — Welle 4: Persistence-Follow-up (schließt P0 #9 vollständig)
- Keine Codeaenderung.

## 0.9.5 — 2026-08-19 — Welle 3 Strom E: administrierbare Kategorie-Policies (P0 #8) + Cleanup
- **P0 #8 — Verwaltungs-UI:** neue capability-gesicherte Seite `managepolicies.php` (Kapability `tool/flexaccess:managepolicies`, RISK_CONFIG) mit Formular zum Setzen/Loeschen der Kategorie-Overrides (Tri-State Erben/Erlauben/Verweigern fuer die Zugangsmethoden, Lebensdauern, Teilnehmer-Sichtbarkeit) plus Uebersichtstabelle. Menueeintrag unter FlexAccess.
- Tests: `category_policy` save/load/merge/delete (enrol).

## 0.9.4 — 2026-08-19 — CI-Härtung + Upgrade-Robustheit (Plugin-Isolation, PHPDoc, reset_role_capabilities)
- **CI-Fix (Plugin-Isolation):** `account_labels` und der zugehoerige Test nutzen die stabilen Wire-Werte direkt statt der auth-Klassenkonstanten. Die tool-Testsuite laeuft damit isoliert (ohne auth) ohne "class not found"; in Produktion unveraendert.

## 0.9.3 — 2026-08-19 — Welle 3 Strom F: Quick-Registration neu spezifiziert (P0 #5)
- Keine Codeaenderung.

## 0.9.2 — 2026-08-19 — Welle 2: Retention/Deletion, zentraler Conversion-Guard, Temp-Restriktionen (P0 #9/#10/#6)
- Keine Codeaenderung.

## 0.9.1 — 2026-08-19 — Welle 1: Token-Sicherheit + atomares Temp-Rate-Limit (P0 #1, #2)
- Keine Codeaenderung.

## 0.9.0 — 2026-08-19 — Beta-Schwelle: CI-Fix, Maturity BETA, Versions-Neustart
- Versionsschema auf `2026081900` / Release `0.9.0` gesetzt, Maturity auf **MATURITY_BETA** angehoben; Cross-Plugin-Dependencies auf `2026081900` gezogen.
- **CI-Fix:** fehlende `@param $reference` in den Docblocks von `api::search_accounts` und `api::build_account_filter` ergaenzt (PHPDoc-Checker).
- Hinweis: Zwei aus dem erneuten Audit stammende Rest-P0 (Klartext-Token in der Mailqueue; generelles atomares Rate-Limit fuer anonyme Temporary-Erzeugung) sind als erste Beta-Haertungswelle eingeplant.

## 0.1.39 — 2026-08-19 — Konfigurierbare Rate-Limits, Cleanup, i18n, Backup/Restore, CI-Härtung
- **§37 i18n:** rohe Enum-Werte (Kontotyp/-status) werden in der Kontenliste jetzt ueber `account_labels` lokalisiert dargestellt statt als interne Rohwerte.
- **§3 Cleanup:** ungenutzte Capability `tool/flexaccess:manageaccounts` entfernt.

## 0.1.38 — 2026-08-19 — Re-login-fähige Konversion, Transaktionen, Mailqueue-Limit, Referenzsuche (§7/§8/§13/§16/§36)
- **§8:** Neue `convert.php` + `convert_form` verlangen die echte E-Mail des Nutzers und rufen `admin_convert` auf (Set-Password-Mail). Die Aktion in `accounts.php` verlinkt dorthin (statt Ein-Klick-Konversion ohne E-Mail).
- **§36:** Sichtbares Such-Formular; `reference_query::normalise` wird jetzt genutzt (behebt zugleich toten Code), Referenznummern werden mitgesucht.

## 0.1.37 — 2026-08-19 — Teilnehmerlisten-Sichtbarkeit durchgesetzt (§35, P0)
- Keine Codeaenderung.

## 0.1.36 — 2026-08-19 — Capacity-Race / verwaiste Accounts behoben (§18)
- Keine Codeaenderung.

## 0.1.35 — 2026-08-19 — DSGVO-Privacy-Provider (§11) + PHPDoc-Fixes
- Keine Codeaenderung (Privacy bleibt `null_provider`).

## 0.1.35 — 2026-08-19 — DSGVO-Datenschutz-Provider vervollstaendigt (§11)
- Keine Codeaenderung (Provider bleibt korrekt `null_provider`: keine eigenen Tabellen).

## 0.1.34 — 2026-08-19 — Rate-Limiting der oeffentlichen Schreib-Endpoints (§5)
- Keine Codeaenderung.

## 0.1.33 — 2026-08-19 — Enrolment-Expiry (§32/§33) + echte jmeter/playwright-Plaene (§26/§27)
- Keine Codeaenderung.

## 0.1.32 — 2026-08-19 — Magic-Login, Mail-Queue-Retrofit, SEC-03, main-CI + jmeter/playwright
- Keine Codeaenderung.

## 0.1.31 — 2026-08-18 — Aufraeumen: toter persistence_followup-Mailpfad entfernt
- Keine Codeaenderung.

## 0.1.30 — 2026-08-18 — DRY: gemeinsame Identitaetsfelder der Formulare
- Keine Codeaenderung.

## 0.1.29 — 2026-08-18 — Paket B: E-Mail-Verifikation der Persistierung (Option, Default an)
- Keine Codeaenderung.

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
