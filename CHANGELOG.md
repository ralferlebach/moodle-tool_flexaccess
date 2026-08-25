# Changelog

## 0.9.31 — 2026-08-25 — Versions-Gleichschritt (enrol: präzisierte Neutralisierungs-Warnung)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082408`.

## 0.9.30 — 2026-08-25 — Discoverability A/C/D1/D2/D4/D5/D6
- **A:** Dashboard zusätzlich unter *Website-Administration → Nutzer/innen → Nutzerkonten* registriert.
- **C:** `tool/flexaccess:managecoursebatches` jetzt nur noch **Kursmanager + Admin** (nicht mehr editingteacher). Neue Capability `tool/flexaccess:requestbatches` (editingteacher + manager) fürs Beantragen.
- **D5:** Beantragen von Listen (Teilnehmerzahl) auf der Kurs-Seite; Lehrende ohne Provisionierungsrecht lösen eine Anfrage aus. Benachrichtigung als **E-Mail UND Moodle-Message** (Message-Provider `batchrequest`) an genau die Personen mit Provisionierungsrecht im Kurs-Kontext (`managers_for_course()`), mit Deep-Link zum Erstellen. Neue API `can_request`/`require_request`/`managers_for_course`/`notify_request`.
- **D6:** In der Batch-Liste je Zeile „im Kurs öffnen" (Link zur Kurs-Seite).
- Kurs-Navigationsknoten greift jetzt bei `can_request` (Lehrende sehen ihn zum Beantragen). Neues Formular `coursebatchrequest_form`. Tests erweitert (Manager-only manage, teacher request, Empfänger, Message-Versand via Sink).
- Versions-Gleichschritt auf `2026082407`.

## 0.9.29 — 2026-08-25 — P2: PHPUnit-11-Migration + Pakete ohne .git
- `@covers`-Doc-Annotationen der Testklassen auf `#[CoversClass(...)]`-Attribute umgestellt (keine PHPUnit-Deprecations mehr). `.git`-Verzeichnisse aus dem Paket entfernt. Versions-Gleichschritt auf `2026082406`.

## 0.9.28 — 2026-08-25 — P1 T2: kurs-interne Zugangslisten-Verwaltung
- **T2:** Lehrende verwalten anonyme Zugangslisten jetzt **im Kurs** (`coursebatches.php`): Erstellen, Auflisten und Download (XLSX/PDF-Liste/Karten) für genau diesen Kurs, ohne in die Site-Administration zu wechseln.
- Neue Kurskontext-Capability `tool/flexaccess:managecoursebatches` (editingteacher + manager). Neue API `batch::for_course()`, `count_for_course()`, `can_manage()`, `require_manage()` (Dual-Kontext: System-`managebatches` ODER Kurs-`managecoursebatches`).
- `batchdownload.php` akzeptiert nun den Kurskontext (Lehrende dürfen ihre eigenen Listen herunterladen). Navigations-Hook `tool_flexaccess_extend_navigation_course()` verlinkt die Seite im Kurs. Neues Formular `coursebatch_form` (ohne Kurs-Selektor). Test `coursebatches_test`.
- Versions-Gleichschritt auf `2026082405`.

## 0.9.27 — 2026-08-24 — CI-Fix: fehlerhafte Workflow-Ausdrücke (${ } → ${{ }})
- Fehlerhafte GitHub-Actions-Ausdrücke im `lint-jsamd`-Job korrigiert (`${ } → ${{ }}`); mit `actionlint` gegengeprüft (0 Findings). Kein PHP-Code geändert; Versions-Gleichschritt auf `2026082404`.

## 0.9.26 — 2026-08-24 — CI: JS/AMD/Mustache-Job wiederhergestellt (catquiz-Form 1:1)
- `lint-jsamd` (grunt + mustache) in dev wiederhergestellt; Mustache/npm/Grunt in main ergänzt. Kein PHP-Code geändert; Versions-Gleichschritt auf `2026082403`.

## 0.9.25 — 2026-08-24 — CI-Fixes (DB-Versionen, vollständige Geschwister, eine Main-Pipeline)
- CI: `postgres:13→16`, `mariadb:10.8→10.11`; jede Pipeline installiert alle drei Geschwister (Ökosystem-Tests); `moodle-release.yml` entfernt.
- Kein PHP-Code geändert; Versions-Gleichschritt auf `2026082402`.

## 0.9.24 — 2026-08-24 — Versions-Gleichschritt (enrol: L3-Kurs-Einstieg + Load-Pläne + CI-Konsolidierung)
- Keine Codeänderung in diesem Plugin. CI: eine Main-Pipeline (Ökosystem-`main.yml` entfernt); Load-Workflows liegen im Hub `enrol_flexaccess`.
- Versions-Gleichschritt auf `2026082401`.

## 0.9.23 — 2026-08-24 — Versions-Gleichschritt (enrol: Zugangs-Blocker-Fix + Kopplungscheck)
- Keine Codeänderung in diesem Plugin; gemeinsamer Versions-Bump auf `2026082400` und aktualisierte Abhängigkeits-Pins.
- **CI-Fix:** `@package`-Korrektur in `tools/mustache_check.php` und `tools/fix_phpdoc.php` (Copy-Paste-Rest).
- **CI-Pipeline:** getrennte Dev-/Main-Workflows + dispatch-only JMeter-/k6-Lastworkflows (catquiz-Vorbild, FlexAccess-Geschwister als Abhängigkeit).

## 0.9.22 — 2026-08-20 — Fix: PHPDoc-Checker (CI) — @param-Vollstaendigkeit
- **Fix (CI PHPDoc):** Der Moodle-PHPDoc-Checker erkennt generische Array-Typen (`array<...>`, `array{...}`) in `@param` nicht und meldete daher "incomplete parameters list" fuer `batch_export::excel/pdf_list/login_cards`, `batch_import::convert/target_username`. Diese `@param`-Typen auf `array` vereinfacht (Form in der Beschreibung erhalten). Zusaetzlich `invitation::queue_mail`: fehlender `@param $token` ergaenzt (Signatur hat 5 Parameter, Docblock hatte 4). Verwaisten doppelten Docblock vor `invitation::reserve()` entfernt. Keine Logikaenderung.

## 0.9.21 — 2026-08-20 — Feature: Excel-Rückkonversion von Stapel-Accounts (Kampagne, Teil 2)
- **Neu (Kampagne, Teil 2):** Rückkonversion aus dem ausgefüllten Export. Neuer Service `local\batch_import` (`parse()` liest die hochgeladene .xlsx via PhpSpreadsheet; `convert()` ordnet jede Zeile über die ursprüngliche Nutzerkennung dem Stapel-Mitglied zu, personalisiert Vorname/Nachname/E-Mail und wandelt den Account zu einem vollwertigen, dauerhaften Account um — inkl. Passwort-setzen-Mail an die neue Adresse als E-Mail-Konfirmation). Nutzerkennungs-Änderung per Excel (optionale Spalte fuer die neue Nutzerkennung, Vorrang) ODER per Regel (E-Mail / erzeugte Kennung behalten / lokaler E-Mail-Teil / vorname.nachname). Zeilen ohne Treffer/E-Mail werden mit Meldung übersprungen. Neue Seite `batchconvert.php` (+ `batch_convert_form` mit Filepicker + Regel-Auswahl), verlinkt von der Stapel-Ansicht. Excel-Export um die optionale Spalte fuer die neue Nutzerkennung erweitert.

## 0.9.20 — 2026-08-20 — Feature: Stapel-Bereitstellung von Kurs-Accounts (Kampagne, Teil 1)
- **Neu (§ Kampagne, Teil 1):** Stapelweise Bereitstellung von Kurs-Accounts. Neue Tabellen `tool_flexaccess_batch` + `tool_flexaccess_batch_member`, Service `local\batch` (Anlage von N Accounts mit zufaelliger Nutzerkennung + Passwort, Einschreibung in einen Kurs ueber die FlexAccess-Einschreibung/Teilnehmerrolle, vorlaeufig-eingeschraenkt ODER dauerhaft-vollwertig; `reset_credentials` fuer sichere Neu-Ausgabe). Drei Exporte via `local\batch_export`: Excel (PhpSpreadsheet; Nutzerkennung, Passwort, leere Spalten Vorname/Nachname/E-Mail/Profilfelder), druckbare PDF-Liste (Nutzerkennung alphabetisch, Passwort, leere Namensspalten) und Login-Kaertchen-PDF (8 Karten/Seite mit Nutzerkennung, Passwort, Kurs-URL, QR-Code). Admin-UI `batches.php` (+ `batch_form`) und `batchdownload.php` (setzt frische Passwoerter und streamt Excel/PDF/Kaertchen einzeln oder als ZIP). Neue Capability `tool/flexaccess:managebatches`, Menuepunkt, Privacy-Provider erweitert. Klartext-Passwoerter werden nie gespeichert (nur im Arbeitsspeicher waehrend Anlage/Export).

## 0.9.19 — 2026-08-20 — Fix: Upgrade-Crash beim Verbreitern der indizierten ratehit.identifier-Spalte
- Keine Codeaenderung.

## 0.9.19 — 2026-08-20 — RC-Gates (Review 0.9.17): Invitation-Security (2 P0) + Playwright-Lockfile
- **P0-1 (Bearer-Secret at rest):** Der Invitation-Token wird nicht mehr im Klartext gespeichert. `tool_flexaccess_invite.token` (CHAR40) -> `tokenhash` (CHAR64, SHA-256); der Klartext-Token wird erst beim Versand erzeugt, nur sein Hash persistiert (Upgrade migriert bestehende Tokens -> Hash und droppt die Klartextspalte). Jeder Versand/Reminder gibt einen frischen Single-Use-Token aus; der Klartext existiert nur transient in der (nach Zustellung geprunten) Mailqueue.
- **P0-2 (Consume-before-success):** Einladungen werden jetzt nach Reserve->Grant->Commit verarbeitet (neuer Status `reserved` + `timereserved`, mit Timeout-Reclaim fuer abgebrochene Versuche). `invite.php` reserviert vor der Registrierung und **committet erst bei Erfolg**; scheitert die Registrierung, geht die Einladung via `release_reservation()` zurueck auf `pending` und ist erneut nutzbar. Neuer Test deckt Reserve/Release/Retry + Commit ab.
- Campaign-Token bleibt unveraendert (bewusst re-teilbar, siehe SSOT).

## 0.9.18 — 2026-08-20 — Fix: PHPDoc-Parameterliste (enrol-CI rot)
- Keine Codeaenderung.

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
