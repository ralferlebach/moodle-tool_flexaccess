# Testbericht FlexAccess 2026102400 (Re-Audit vom 24.10.2026)

Lokaler Nachweislauf auf dem committeten Stand der vier Plugins (Branch `work`, nicht gepusht), 24.10.2026,
14:09–15:09 UTC; die beiden Zwei-Site-LTI-Läufe und die Playwright-Läufe auf 5.3 wurden um 15:10–15:40 UTC
wiederholt (siehe „Abweichungen“). Alle Zahlen stammen aus den Protokollen der Läufe; nichts ist geschätzt.

## Gegenstand

| Plugin | Version | Commit (lokal) |
|---|---|---|
| auth_flexaccess | 2026102400 (1.2.0) | `7afd5bb` |
| enrol_flexaccess | 2026102400 (1.2.0) | `fee1ea2` (Code), `9f5a093` (nur LTI-Journey, siehe unten) |
| mod_flexaccess | 2026102400 (1.2.0) | `82a00c7` |
| tool_flexaccess | 2026102400 (1.2.0) | `29fdc94` (Code); dieser Bericht und der Release-Lock folgen in eigenen Commits |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Die lokalen Commit-SHAs entstehen beim Hochladen der ZIPs auf GitHub neu. Deshalb pinnt der Release-Lock
`.github/flexaccess-family.lock.json` auth, enrol und mod über ihren **Inhalt** (siehe
[release-gate.md](release-gate.md)); er wurde gegen die ausgelieferten ZIPs geprüft (Abschnitt „Lock“).

## Umgebungen

PHP 8.3.6; Moodle 4.5.15 (Build 20261005), 5.0.11 (Build 20261005), 5.2.4+ (Build 20261021),
5.3+ (Build 20261013); PostgreSQL 16.15 (4.5, 5.0, 5.2) und 17.10 (5.3); MariaDB 10.11.14 (4.5, 5.0, 5.2)
und 11.4.13 (5.3, offizielles Binärarchiv, SHA-256 gegen `sha256sums.txt` geprüft); Playwright 1.62.1 mit
Chromium; Behat mit BrowserKit (ohne JavaScript).

## Ergebnisse

| Prüfung | Umgebung | Plugin | Ergebnis |
|---|---|---|---|
| PHPUnit | Moodle 4.5, PostgreSQL 16 | auth | OK (193 Tests, 919 Assertions) |
| PHPUnit | Moodle 4.5, PostgreSQL 16 | enrol | OK (241 Tests, 1357 Assertions) |
| PHPUnit | Moodle 4.5, PostgreSQL 16 | mod | OK (7 Tests, 24 Assertions) |
| PHPUnit | Moodle 4.5, PostgreSQL 16 | tool | OK (145 Tests, 712 Assertions) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | auth | OK (193 Tests, 923 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | auth | OK (193 Tests, 919 Assertions) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | enrol | OK (241 Tests, 1357 Assertions) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | mod | OK (7 Tests, 24 Assertions) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | tool | OK (145 Tests, 712 Assertions) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | auth | OK (193 Tests, 923 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | auth | OK (193 Tests, 919 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | auth | OK (193 Tests, 919 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | auth | OK (193 Tests, 919 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | auth | OK (193 Tests, 919 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | auth | 1 scenario (1 passed)  3 steps (3 passed) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | enrol | 13 scenarios (13 passed)  120 steps (120 passed) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | mod | 1 scenario (1 passed)  4 steps (4 passed) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | tool | 3 scenarios (3 passed)  33 steps (33 passed) |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | auth | 7 passed |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | enrol | 10 passed |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | mod | 4 passed |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | tool | 1 skipped, 21 passed |
| Zwei-Site-LTI 1.3 (Playwright) | Moodle 4.5, PostgreSQL 16 | enrol/auth | 10 passed |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | auth | 7 passed |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | enrol | 10 passed |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | mod | 4 passed |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | tool | 22 passed |
| Zwei-Site-LTI 1.3 (Playwright) | Moodle 5.3, PostgreSQL 17 | enrol/auth | 10 passed |

Statische Prüfungen (moodle-plugin-ci 4: phplint, validate, savepoints, mustache, codechecker und phpdoc
jeweils mit `--max-warnings 0`) auf 4.5 und 5.3: **grün** für alle vier Plugins. Selbsttests des
Family-Resolvers: 17 von 17 grün.

Die PHPUnit-Deprecation-Hinweise ab Moodle 5.0 (PHPUnit 11) betreffen ausschließlich Metadaten in
Docblocks (`@covers`, `@dataProvider`); ihre Umstellung auf Attribute ist zurückgestellt. Der übersprungene
Playwright-Fall auf 4.5 prüft den dunklen Farbmodus, den es erst ab Moodle 5.3 gibt.

## Upgrade

| Ausgangsstand | Ergebnis |
|---|---|
| 2026092900 (GitHub `main`) mit Kurs, temporärem Teilnehmer, entfernter Gradebook-Rolle und tool_mergeusers-Protokoll | Upgrade OK auf 2026102400; Teilnehmerrolle wieder in `gradebookroles`; Merge aus dem Protokoll bestätigt (`mergeconfirmed`, Nachweis 1), Prüfaufgabe eingereiht; Nachweisfelder und `auth_flexaccess_mergeevid` angelegt; 0 Freigaben ohne Nachweis; Einschreibedauer in 0 Methoden abweichend |
| 2026102100 (vorige Auslieferung) mit einer Freigabe ohne Nachweis | Upgrade OK; die Freigabe ohne Nachweis gilt nicht mehr (`approvedby` geleert, 0 gültige Freigaben ohne Nachweis); Nachweisfelder und Tabelle angelegt |

Hinweis zum zweiten Lauf: Die Testvorbereitung entfernt die Teilnehmerrolle künstlich aus
`gradebookroles`; ab 2026102100 gibt es keinen Upgrade-Schritt, der sie erneut setzt (auf echten Sites ist
sie seit 2026101700 registriert). Der Altwert `approved` stammt ebenfalls aus der Testvorbereitung.

## Nachweis je Issue

| Issue | Nachweis in diesem Lauf |
|---|---|
| auth #13 | `merge_matrix_test` (30 Tests, echte Zusammenführungen über tool_mergeusers) und `lti_merge_test` (14) auf 4.5, 5.0, 5.2, 5.3 × PostgreSQL/MariaDB; Playwright `merge-review.spec.js` (Vorschau, Freigabe mit Nachweis, echter Merge, Anmeldung mit behaltenem Kennwort) auf 4.5 und 5.3; Tabellenabdeckung in auth `docs/merge_policy.md` |
| auth #12, #14, enrol #10 | Zwei-Site-LTI 1.3 (zwei Moodle-Instanzen, signierte OIDC-Launches über TLS): 10/10 auf 4.5 und 5.3 – AUTO_ONLY-Dublette, Freigabe, Merge, stabiler Relaunch, NRPS-Sync nach dem Merge, Notenrückgabe; nur PostgreSQL |
| enrol #9, tool #10 | `enrolment_lifecycle_test` (16), `core_time_semantics_test` (26), `recovery_postcondition_test` (13) in allen acht PHPUnit-Umgebungen; Playwright-Recovery-Journeys (tool) auf 4.5 und 5.3 |
| enrol #12, #13 | `instance_form_roundtrip_test` (24, u. a. 21.–22. Oktober in Berlin/Los Angeles, Sommerzeitende, ungültige Grenzen, Kapazität 0/17, reale Sperre an T−1/T/Ende), `instance_config_test`; Playwright `instance-form.spec.js` (5 Fälle) auf 4.5 und 5.3 |
| Clock: auth #15, enrol #14, mod #6, tool #13 | `clock_test` in auth (9), enrol (19), tool (21) und mod `lib_test` (3) in allen acht PHPUnit-Umgebungen |
| enrol #8 | `gradebook_role_test` (9: Grader Report, Kursabschluss, Notenimport, LTI-Gradebook-Services, Upgrade-Schritt) in allen acht Umgebungen; echtes Upgrade von 2026092900 (oben) |
| tool #12, #11 | Release-Lock im Release, gegen die ZIPs geprüft (unten); Release-Matrix lokal nachgebildet: 4.5/5.0/5.2/5.3 × PostgreSQL/MariaDB (PHPUnit), PHP nur 8.3 |

## Lock

Die vier ZIPs wurden wie ein Web-Upload in frische Repositories entpackt und committet (Ausführungsrechte
entfernt); der Befehl `lock` des Release-Gates fand für auth, enrol und mod genau den gelockten Inhalt am
Branch-Kopf, prüfte den Inhalt von tool_flexaccess ohne die Lock-Datei und lud die festgestellten Commits
(`fetch`). Protokoll: siehe Abschlussmeldung der Auslieferung.

## Nicht nachgewiesen

- **GitHub-CI auf den hochgeladenen Ständen.** Alle Läufe sind lokal; GitHub `main` trägt weiterhin
  2026092900. Erst das Release-Gate (`family-release.yml`) auf den hochgeladenen Commits belegt die
  Artefaktidentität in CI und PHP 8.2/8.4.
- PHP 8.2 und 8.4, Firefox/WebKit, Behat mit JavaScript, Behat und Playwright auf 5.0/5.2, Playwright und LTI
  gegen MariaDB.

## Abweichungen

- Der erste Zwei-Site-LTI-Lauf scheiterte auf 4.5 und 5.3 im Merge-Journey, weil die Freigabe jetzt eine
  Nachweisart und eine Beschreibung verlangt (auth #13) und die Journey sie nicht ausfüllte; die übrigen vier
  Fälle liefen deshalb nicht. Nach der Anpassung der Journey (enrol `9f5a093`, nur Testcode) liefen beide
  Suiten vollständig (10/10).
- Der erste Playwright-Lauf auf 5.3 brach bei der Installation ab: Der PHPUnit-Lauf gegen MariaDB hatte im
  gemeinsam genutzten `vendor/` die Composer-Metadaten auf einen anderen Moodle-Baum umgeschrieben
  (Testumgebung, kein Plugin-Fehler). Nach der Korrektur liefen alle vier Suiten auf 5.3 (oben).
