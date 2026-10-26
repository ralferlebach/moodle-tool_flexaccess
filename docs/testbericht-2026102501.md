# Testbericht FlexAccess 2026102501 (Re-Audit 2026102401 und Nacharbeitsliste Development, 25.10.2026)

Lokale Nachweisläufe am 25.10.2026 (Uhr der Testumgebung, UTC). 2026102501 ändert gegenüber 2026102500 nur Versionsnummern,
Tests, Test-Fixtures, Workflows der Lasttests und Dokumentation; der PHP-Code der Plugins ist identisch. Läufe, die den
Plugin-Code betreffen und nur auf 2026102500 liefen, sind als solche gekennzeichnet. Alle Zahlen stammen aus den
Protokollen; nichts ist geschätzt. Die Zuordnung zu den Akzeptanzkriterien steht in [abnahme-2026102501.md](abnahme-2026102501.md).

## Gegenstand

| Plugin | Version | Commit (lokal, nicht gepusht) |
|---|---|---|
| auth_flexaccess | 2026102501 (1.2.0) | `d30358a` |
| enrol_flexaccess | 2026102501 (1.2.0) | `cfc6748` |
| mod_flexaccess | 2026102501 (1.2.0) | `8d21fe2` |
| tool_flexaccess | 2026102501 (1.2.0) | `87613d1` (Code); dieser Bericht und der Release-Lock folgen in eigenen Commits |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Umgebungen: PHP 8.3.6; Moodle 4.5.15, 5.0.11, 5.2.4+, 5.3+; PostgreSQL 16.15 (4.5/5.0/5.2) und 17.10 (5.3); MariaDB
10.11.14 (4.5/5.0/5.2) und 11.4.13 (5.3); Playwright 1.62.1 mit Chromium.

## Ergebnisse 2026102501

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| Statische Prüfungen (phplint, validate, savepoints, mustache, codechecker und phpdoc mit `--max-warnings 0`) | 4.5 und 5.3, alle vier Plugins | grün |
| PHPUnit | 4.5 PostgreSQL | auth 227 / enrol 243 / mod 9 / tool 147 Tests, alle OK |
| PHPUnit | 4.5 MariaDB 10.11 | dieselben Zahlen, alle OK |
| PHPUnit | 5.3 PostgreSQL 17 | auth 227 (1 übersprungen) / enrol 243 / mod 9 / tool 147, alle OK (PHPUnit-Deprecation-Hinweise 35/47/4/33) |
| PHPUnit | 5.3 MariaDB 11.4 | dieselben Zahlen, alle OK |
| Family-Resolver-Selbsttests | – | 17/17 |
| Playwright | 4.5 | auth 7, enrol 10, mod 4, tool 26 bestanden (+1 übersprungen: Dark Mode erst ab 5.3) |
| Playwright | 5.3 | auth 7, enrol 10, mod 4, tool 27 bestanden |
| Playwright, 10 Wiederholungen | 4.5 und 5.3 | `auth-journeys` 50/50 und 50/50; `instance-form` 50/50 und 50/50 |
| Zwei-Site-LTI 1.3 | 4.5 und 5.3 (PostgreSQL) | 10/10 und 10/10 |
| Upgrade auf 2026102501 (Moodle 4.5) | aus 2026092900, 2026102400, 2026102401, 2026102500 | alle OK; 0 Zugangsdaten im Archiv; bei Altständen werden Freigaben in OAuth-2-Konten ohne Bindung neu verlangt, pauschal quittierte Einschreibungen wieder geöffnet |
| Upgrade Moodle 5.2 → 5.3 mit FlexAccess-Daten | FlexAccess 2026102501 auf 5.2 → Core 5.3 | OK, 0 Debugzeilen, 2 Konten / 1 Instanz / 2 Einschreibungen erhalten, Kennwortanmeldung vor und nach OK |
| Upgrade Moodle 5.2 → 5.3 und FlexAccess alt → neu | FlexAccess 2026092900 auf 5.2 → Core 5.3 + FlexAccess 2026102501 | OK, Daten und Anmeldung erhalten, Register-Tabelle angelegt |
| `make check` (vorhandenes `makefile`) | 4.5, alle vier Plugins | phpcs ohne Befund, phpdoc ohne Befund, phpcpd „No clones found“, PHPUnit wie oben; Exit 0 |
| Tabellenabdeckung tool_mergeusers | 4.5 / 5.0 / 5.2 / 5.3 | identisch mit `auth/docs/merge_policy.md` §11, keine Lücke |
| „Rot vor dem Fix“ enrol #12 | Regressionstest aus 2026102100 gegen enrol 2026101800, 4.5 PostgreSQL | 17 Tests: 15 Failures, 1 Error (erwartet rot), mit dem Fix grün |

## Ergebnisse 2026102500 (gleicher Plugin-Code)

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| PHPUnit | 5.0 und 5.2 × PostgreSQL 16 / MariaDB 10.11 | auth 226 (1 übersprungen) / enrol 243 / mod 9 / tool 147, alle OK |
| Behat (ohne JavaScript) | 4.5 | auth 1, enrol 13, mod 1, tool 3 Szenarien, alle bestanden |
| 10 Wiederholungen der Zeitgrenzen-Klassen | 4.5 und 5.3 | 11 Klassen je 10/10 ohne Fehler (auf 5.3 meldete das Zählskript „0/10“, weil PHPUnit 11 die Ergebniszeile ohne „OK“ beginnt; alle 10 Läufe ohne Failure/Error) |
| Abfragen und Speicher beim Archivieren | PostgreSQL / MariaDB, 25 bzw. 500 Kollisionen | Lesezugriffe 11 / 10 (PG), 5 / 4 (MariaDB) – unabhängig von der Zahl; Schreibzugriffe 1 (PG), 1 / 10 (MariaDB, Chunks); Speicherzuwachs ≈ 0,28 MB / 4,5–4,8 MB (PG), ≈ 0,25 MB / 3,0–3,3 MB (MariaDB) |

## Übersprungen

- auth `merge_matrix_test::test_collisions_are_archived`, Datensatz „survey answer“, auf 5.0/5.2/5.3: `survey_answers` gibt es
  dort nicht (mod_survey nach 4.5 aus dem Kern entfernt). Auf 4.5 (PostgreSQL und MariaDB) ausgeführt und grün. Zählt nicht
  als bestanden.
- tool Playwright „site administration pages keep their contrast in the dark colour scheme“ auf 4.5: kein Farbmodus vor 5.3.

## Abweichungen und behobene Testfehler

- **auth Playwright „temporary access can be made permanent“** fiel seit 2026102401 sporadisch aus (`net::ERR_ABORTED` bzw.
  „Invalid login“). Ursache laut Server-Log: „Invalid Login Token“ – eine noch laufende Anfrage der vorigen Seite setzte nach
  dem Löschen der Cookies ein neues Sitzungscookie, zwischen Anmeldeformular und Absenden. Kein Fehler im Plugin. Behoben mit
  `freshSession()` (erst `about:blank`, dann Cookies löschen) in allen vier Plugins; danach 2 × 50/50.
- **auth Playwright, Wiederholungen:** `personEmail()` unterschied nur Retries, nicht Wiederholungen (`--repeat-each`); die
  zweite Wiederholung traf auf das Konto der ersten. Behoben.
- **enrol Playwright „keeps window and capacity across save and reopen“** scheiterte bei Wiederholung, weil es den Zustand
  früherer Journeys übernahm; stellt seine Vorbedingung jetzt selbst her (`probe.php openwindow`).
- **Lasttests k6/JMeter** (GitHub, 25.10.2026, 14:01 UTC): Abbruch vor dem Seeding, weil die Geschwister fest von `main`
  aufgelöst wurden (dort 2026092900). Die Workflows lösen jetzt vom Branch des Laufs auf (Eingabe `sibling_ref`, `--wait 600`).
  `seed_large.php` und `seed_capacity.php` lokal auf 4.5 geprüft; ein Lasttestlauf in GitHub steht aus.
- Die erste Ausführung von PHPUnit 5.3/MariaDB 11.4 im Lauf zu 2026102500 scheiterte an einem nach dem Neustart der
  Testumgebung nicht laufenden Datenbankserver (Testumgebung); die Wiederholung war grün.

## Nicht nachgewiesen

- **GitHub-CI auf den hochgeladenen 2026102501-Ständen** (noch nicht hochgeladen). Für 2026102500 liegen die grünen Läufe
  aus der Nacharbeitsliste vor.
- PHP 8.2/8.4 (nur GitHub), WebKit, Behat mit JavaScript, Playwright und LTI auf 5.0/5.2, Playwright und LTI gegen MariaDB.
- **PHPStan**: in keinem Workflow und keinem `makefile` eingerichtet; in dieser Umgebung nicht installierbar.
- `make check` bricht bei fehlenden Werkzeugen nicht ab (Entwicklerwerkzeug); maßgeblich bleibt die Plugin-CI.
- OAuth 2 nur gegen einen lokalen Test-Anbieter; LDAP, CAS, Shibboleth, SAML2, DB, MNet ohne echten Dienst.
- Parallele Abgleiche bzw. LTI-Launch während einer Zusammenführung (in einem PHP-Prozess nicht testbar).
- Offene Einzelpunkte aus enrol #10 und auth #12: siehe Abnahmematrix, Abschnitt „Zusammenfassung der offenen Punkte“.
