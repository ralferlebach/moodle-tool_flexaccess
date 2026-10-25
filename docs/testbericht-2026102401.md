# Testbericht FlexAccess 2026102401 (Re-Audit-Nacharbeiten v2 vom 24.10.2026)

Lokaler Nachweislauf auf dem committeten Stand der vier Plugins (Branch `work`, nicht gepusht), 24.10.2026,
19:51–20:54 UTC (Uhr der Testumgebung). Die Playwright-Suite von auth auf 4.5 wurde danach zweimal wiederholt
(siehe „Abweichungen“). Alle Zahlen stammen aus den Protokollen der Läufe; nichts ist geschätzt.

## Gegenstand

| Plugin | Version | Commit (lokal) |
|---|---|---|
| auth_flexaccess | 2026102401 (1.2.0) | `d53ddd7` |
| enrol_flexaccess | 2026102401 (1.2.0) | `0be4dfd` |
| mod_flexaccess | 2026102401 (1.2.0) | `289ece5` |
| tool_flexaccess | 2026102401 (1.2.0) | `aa393ce` (Code); dieser Bericht und der Release-Lock folgen in eigenen Commits |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Die lokalen Commit-SHAs entstehen beim Hochladen der ZIPs auf GitHub neu. Der Release-Lock
`.github/flexaccess-family.lock.json` pinnt auth, enrol und mod deshalb über ihren **Inhalt** (siehe
[release-gate.md](release-gate.md)).

## Umgebungen

PHP 8.3.6; Moodle 4.5.15 (Build 20261005), 5.0.11 (Build 20261005), 5.2.4+ (Build 20261021),
5.3+ (Build 20261013); PostgreSQL 16.15 (4.5, 5.0, 5.2) und 17.10 (5.3); MariaDB 10.11.14 (4.5, 5.0, 5.2)
und 11.4.13 (5.3); Playwright 1.62.1 mit Chromium; Behat mit BrowserKit (ohne JavaScript).

## Ergebnisse

| Prüfung | Umgebung | Plugin | Ergebnis |
|---|---|---|---|
| PHPUnit | Moodle 4.5, PostgreSQL 16 | auth | OK (215 Tests, 1169 Assertions) |
| PHPUnit | Moodle 4.5, PostgreSQL 16 | enrol | OK (241 Tests, 1357 Assertions) |
| PHPUnit | Moodle 4.5, PostgreSQL 16 | mod | OK (7 Tests, 24 Assertions) |
| PHPUnit | Moodle 4.5, PostgreSQL 16 | tool | OK (145 Tests, 712 Assertions) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | auth | OK (215 Tests, 1161 Assertions; 1 übersprungen; 33 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, PostgreSQL 17 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | auth | OK (215 Tests, 1169 Assertions) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | enrol | OK (241 Tests, 1357 Assertions) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | mod | OK (7 Tests, 24 Assertions) |
| PHPUnit | Moodle 4.5, MariaDB 10.11.14 | tool | OK (145 Tests, 712 Assertions) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | auth | OK (215 Tests, 1161 Assertions; 1 übersprungen; 33 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.3, MariaDB 11.4.13 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | auth | OK (215 Tests, 1157 Assertions; 1 übersprungen; 33 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, PostgreSQL 16 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | auth | OK (215 Tests, 1157 Assertions; 1 übersprungen; 33 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.0, MariaDB 10.11.14 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | auth | OK (215 Tests, 1157 Assertions; 1 übersprungen; 33 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, PostgreSQL 16 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | auth | OK (215 Tests, 1157 Assertions; 1 übersprungen; 33 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | enrol | OK (241 Tests, 1357 Assertions; 46 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | mod | OK (7 Tests, 24 Assertions; 3 PHPUnit-Deprecation-Hinweise) |
| PHPUnit | Moodle 5.2, MariaDB 10.11.14 | tool | OK (145 Tests, 712 Assertions; 32 PHPUnit-Deprecation-Hinweise) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | auth | 1 Szenario (1 bestanden), 3 Schritte (3 bestanden) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | enrol | 13 Szenarien (13 bestanden), 120 Schritte (120 bestanden) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | mod | 1 Szenario (1 bestanden), 4 Schritte (4 bestanden) |
| Behat (ohne JS) | Moodle 4.5, PostgreSQL 16 | tool | 3 Szenarien (3 bestanden), 33 Schritte (33 bestanden) |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | auth | 7 bestanden (Wiederholung; Erstlauf 6 bestanden, 1 fehlgeschlagen – siehe Abweichungen) |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | enrol | 10 bestanden |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | mod | 4 bestanden |
| Playwright (Chromium) | Moodle 4.5, PostgreSQL 16 | tool | 1 übersprungen, 23 bestanden |
| Zwei-Site-LTI 1.3 (Playwright) | Moodle 4.5, PostgreSQL 16 | enrol/auth | 10 bestanden |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | auth | 7 bestanden |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | enrol | 10 bestanden |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | mod | 4 bestanden |
| Playwright (Chromium) | Moodle 5.3, PostgreSQL 17 | tool | 24 bestanden |
| Zwei-Site-LTI 1.3 (Playwright) | Moodle 5.3, PostgreSQL 17 | enrol/auth | 10 bestanden |

Statische Prüfungen (moodle-plugin-ci 4: phplint, validate, savepoints, mustache, codechecker und phpdoc
jeweils mit `--max-warnings 0`) auf 4.5 und 5.3: **grün** für alle vier Plugins. Selbsttests des
Family-Resolvers: 17 von 17 grün.

Übersprungen: auf Moodle 5.0, 5.2 und 5.3 der Kollisionsfall „survey answer“ (`survey_answers` gibt es ab
5.0 nicht mehr, das Umfragemodul wurde aus dem Kern entfernt); in Playwright auf 4.5 der Fall für den dunklen
Farbmodus, den es erst ab 5.3 gibt. Die PHPUnit-Deprecation-Hinweise ab Moodle 5.0 (PHPUnit 11) betreffen
ausschließlich Metadaten in Docblocks.

## Upgrade

| Ausgangsstand | Ergebnis |
|---|---|
| 2026092900 (GitHub `main`) mit Kurs, temporärem Teilnehmer, entfernter Gradebook-Rolle und tool_mergeusers-Protokoll | Upgrade OK auf 2026102401; Teilnehmerrolle wieder in `gradebookroles`; Merge aus dem Protokoll bestätigt (`mergeconfirmed`), Prüfaufgabe eingereiht; Nachweis- und Bindungsfelder sowie `auth_flexaccess_mergeevid` angelegt; 0 Freigaben ohne Nachweis |
| 2026102400 (vorige Auslieferung) mit gesicherter FlexAccess-Einschreibung, gesicherter Bewertungskollision und je einer wartenden Freigabe mit Nachweis in ein OAuth-2- und ein manuelles Konto | Upgrade OK; Einschreibungseintrag als `enrolment`, Bewertung als `review`, Schlüsselwerte übernommen (`[77]`, `[88]`); die Freigabe in das OAuth-2-Konto muss neu erteilt werden (ihr fehlt die festgehaltene Bindung), die in das manuelle Konto bleibt gültig |

Hinweis: Die Testvorbereitung entfernt die Teilnehmerrolle künstlich aus `gradebookroles`; ab 2026102100
setzt kein Upgrade-Schritt sie erneut (auf echten Sites ist sie seit 2026101700 registriert). Die Altwerte
`pending`/`approved` im zweiten Lauf stammen ebenfalls aus der Testvorbereitung.

## Nachweis je Nacharbeit

| Punkt | Nachweis in diesem Lauf |
|---|---|
| N1 – Eigentumsnachweis für jede Zusammenführung (auth #13) | `merge_matrix_test` (51 Fälle) in allen acht PHPUnit-Umgebungen: Matrix mit Freigabepflicht für jede Richtung (u. a. temporär → temporär, provisorisch → dauerhaft); `test_temporary_source_needs_an_ownership_proof` (ohne Nachweis abgewiesen, beide Konten samt Einschreibungen, Kontozeilen und Bewertung unverändert; mit Nachweis kein Ablauf, keine Beschränkungsrolle, kein Statuswechsel übernommen); `test_session_holder_cannot_approve` (provisorische Quelle ohne Nachweis abgewiesen; angemeldete Besucherin kann nicht freigeben, Merge in ein fremdes Konto abgewiesen); `test_three_identities` (Plan, jeder Schritt einzeln freigegeben). Playwright `merge-review.spec.js` auf 4.5 und 5.3: „a temporary visitor is never merged without an ownership proof“. Doku auth `docs/merge_policy.md` §3–4 |
| N2 – Einschreibungen nur bei Erfolg quittiert (auth #13, enrol #9) | `test_recorded_enrolment_without_survivor_enrolment_stays_open` (Ziel-Einschreibung gelöscht: `review`/`enrolmentspending`, Eintrag offen, Quelldaten erhalten; Wiederholung ohne Wirkung; nach Wiederherstellung genau eine Übernahme mit dem späteren, begrenzten Ende; erneute Prüfung idempotent), `test_recorded_enrolment_without_instance_needs_a_decision` (Methode fehlt: `review` bis zur dokumentierten Entscheidung), `test_refused_merge_keeps_no_evidence` (Rollback); je PostgreSQL und MariaDB auf 4.5, 5.0, 5.2, 5.3 |
| N3 – alle Kollisionstabellen | `test_collisions_are_archived` (12 Tabellen: 9 review, 3 benign, jeweils mit nicht kollidierender Zeile, die nur umzieht), `test_unwrapped_table_merger_refuses_the_merge` (Lücke weist ab); `cli/mergeusers_coverage.php` auf 4.5, 5.0, 5.2, 5.3: Ausgabe dieses Laufs identisch mit der Tabelle in `docs/merge_policy.md` §11, keine Lücke |
| N4 – institutionelle Anmeldungen | PHPUnit: OAuth-2-Ziel mit Verknüpfung (Anmeldung löst vor und nach dem Merge auf dasselbe Konto auf), ohne Verknüpfung (`ssounverified`), unbekanntes Auth-Plugin (`ssounsupported`), geänderte Bindung nach der Freigabe (`ssochanged`). Playwright auf 4.5 und 5.3: echte OAuth-2-Anmeldung über einen lokalen Anbieter, Zusammenführung des FlexAccess-Kontos in das institutionelle Konto, erneute Anmeldung – dieselbe Moodle-ID, Kurs des entfernten Kontos erreichbar |

## Grenzen

- **OAuth 2 gegen einen Testanbieter.** Der Anbieter ist ein lokaler Node-Server über http
  (`127.0.0.1:8091`); weil Moodle für Aussteller https verlangt, schreibt die Testvorbereitung die Endpunkte
  direkt in die Datenbank und leert die cURL-Sperrlisten – nur auf der Testsite. Ein produktiver
  Identitätsanbieter ist nicht getestet.
- **LDAP, CAS, Shibboleth, SAML2, DB, MNet** sind nicht als echter Dienst getestet; geprüft wird ihre
  Bindung über den Moodle-Benutzernamen.
- **Gleichzeitige Abgleiche** desselben Paars sind über eine Sperre je Paar serialisiert; in einem
  PHP-Prozess ist das nicht testbar (Sperren sind je Datenbanksitzung wiedereintrittsfähig).
- **GitHub-CI auf den hochgeladenen Ständen.** Alle Läufe sind lokal; erst das Release-Gate
  (`family-release.yml`) auf den hochgeladenen Commits belegt die Artefaktidentität in CI und PHP 8.2/8.4.
- PHP 8.2 und 8.4, Firefox/WebKit, Behat mit JavaScript, Behat und Playwright auf 5.0/5.2, Playwright und LTI
  gegen MariaDB.

## Abweichungen

- Im Nachweislauf scheiterte auf 4.5 in der auth-Suite der Fall „temporary access can be made permanent“: Die
  Navigation zur Anmeldeseite nach dem Dauerhaft-Machen wurde vom Browser abgebrochen (`net::ERR_ABORTED`),
  bevor eine Prüfung lief. Zwei Wiederholungen der Suite liefen vollständig (7/7); auf 5.3 lief sie im
  Nachweislauf vollständig. Code für das Dauerhaft-Machen wurde in dieser Version nicht geändert. Die Ursache
  ist nicht abschließend geklärt; vermutlich eine noch laufende Weiterleitung beim Aufruf der nächsten Seite.
- Vor dem Nachweislauf schlug auf 5.3 ein PHPUnit-Fall fehl: Auf Moodle 5.x löst auth_oauth2 eine Verknüpfung
  nur auf, wenn `confirmtokenexpires` 0 oder in der Zukunft ist. FlexAccess prüft seitdem jede Verknüpfung
  über `auth_oauth2\api::match_username_to_user()` selbst; die Testhilfe legt Verknüpfungen wie Moodle an.
