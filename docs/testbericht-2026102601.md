# Testbericht FlexAccess 2026102601 (Nacharbeitsauftrag und Scope-Entscheidungen, 26.10.2026)

Lokale Nachweisläufe (L-601) am 26.10.2026 (Uhr der Testumgebung, UTC) auf den Commits unten. Alle Zahlen stammen aus
den Protokollen; nichts ist geschätzt. Zuordnung zu den Kriterien: [abnahme-2026102601.md](abnahme-2026102601.md);
Mengengerüst: [reconciliation-capacity.md](reconciliation-capacity.md).

## Gegenstand

| Plugin | Version | Commit (lokal, nicht gepusht) |
|---|---|---|
| auth_flexaccess | 2026102601 | `569d3e533365544fa4ac42efae18a5b7edfe2513` |
| enrol_flexaccess | 2026102601 | `2a1f421c1bb5b3c43d997b1044683d7b8f31909d` |
| mod_flexaccess | 2026102601 | `c931260802854ec78ab6a30cbcf7f6cd3a5c0ecd` |
| tool_flexaccess | 2026102601 | `9beaa1d22de1436d6f919bc486c89d658be0594f` |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Umgebungen: PHP 8.3.6; Moodle 4.5.15, 5.0.11, 5.2.4+, 5.3+; PostgreSQL 16.15 (4.5/5.0/5.2) und 17.10 (5.3); MariaDB
10.11.14 (4.5/5.0/5.2) und 11.4.13 (5.3); Playwright 1.62.1 mit Chromium; poppler-utils (pdftotext) 24.02.

## Ergebnisse

Code-Commits wie oben; danach folgen nur noch dieser Bericht mit Abnahme- und Kapazitätsdokument (tool) und der
Release-Lock (tool), die keinen Plugin-Code ändern.

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| PHPUnit, alle vier Plugins | 4.5 PostgreSQL 16, 4.5 MariaDB 10.11, 5.0 PostgreSQL, 5.0 MariaDB, 5.2 PostgreSQL, 5.2 MariaDB, 5.3 PostgreSQL 17, 5.3 MariaDB 11.4 | alle 8 grün, kein Failure, Error, Skip oder Incomplete: auth 228 Tests (4.5, mit „survey answer“) bzw. 227 (5.x), enrol 247, mod 9, tool 178; auf 5.x nur PHPUnit-11-Deprecation-Hinweise des Test-Frameworks |
| Credential-Test ohne pdftotext (Rotnachweis tool #18) | 4.5 PostgreSQL, `/usr/bin/pdftotext` entfernt | 1 Failure „pdftotext (poppler-utils) is required …“ |
| Mengengerüst Abgleich (tool #17), `FLEXACCESS_SCALE_LARGE=1` | 4.5 PostgreSQL 16, 5.3 PostgreSQL 17, 4.5 MariaDB 10.11, 5.3 MariaDB 11.4; 0/100/1.000/10.000/100.000 Waisen | alle Budgets eingehalten; PHP-Peak ≤ 716 KB; 100.000 Waisen erster Durchlauf 7,96 s / 10,91 s / 19,88 s / 16,37 s; zweiter Durchlauf ohne Schreibzugriff (Tabelle in reconciliation-capacity.md) |
| Familien-Resolver-Selbsttest | Python 3 | 17 Tests OK |
| Playwright, Chromium | 4.5 PostgreSQL | auth 7, enrol 10, mod 4, tool 32 bestanden + 1 übersprungen (Dark-Mode-Kontrast erst ab 5.3) |
| Playwright, Chromium | 5.3 PostgreSQL 17, `public/`-Webroot | auth 7, enrol 10, mod 4, tool 33 bestanden |
| Playwright tool, Chromium | 4.5 MariaDB 10.11 | 32 bestanden + 1 übersprungen (wie oben) |
| Playwright tool, Chromium | 5.3 MariaDB 11.4, `public/`-Webroot | 33 bestanden |
| Zwei-Site-LTI, getrennt wie im Gate | 4.5 und 5.3, PostgreSQL | Provider 10/10, Consumer 4/4 (Chromium; die fünfte Consumer-Journey ist die Firefox-Variante, nur im Gate) – je Version |
| Zwei-Site-LTI, alle zusammen | 5.3 | 14/14 |
| Deinstallation und Neuinstallation (enrol #15) | 4.5/5.3 × PostgreSQL/MariaDB, Sites aus den Playwright-Läufen, je zwei Zyklen | alle vier identisch (Einzelheiten in abnahme-2026102601, enrol #15): FlexAccess-Zuweisungen 30 → 0, manuelle Zuweisungen 2 → 2, Methoden 9 → 0, Einschreibungen 19 → 0, Tabellen 16 → 6 → 0, Konfiguration 34 → 0; Neuinstallation ohne doppelte Rolle |
| `make check` | 4.5, alle vier Plugins | Exit 0 (phpcs, phpdoc, phpcpd „No clones found“, PHPUnit) |
| Upgrade auf 2026102601 (Moodle 4.5, PostgreSQL) | aus 2026102600 und aus `origin/main` (2026092900) | beide „UPGRADE OK“, alle vier Versionen 2026102601; Teilnehmerrolle danach: aus 2026102600 `enrol_flexaccess#2` für beide Nutzer (seit 2026102502 vergibt FlexAccess die Rolle nur noch mit Komponente; die manuelle Einschreibung vergibt die Studierendenrolle); aus `origin/main` beim Nutzer mit zusätzlicher manueller Einschreibung `(ohne Komponente)#0` und `enrol_flexaccess#2` (die alte komponentenlose Zuweisung bleibt, weil sie nicht beweisbar FlexAccess gehört) |
| Upgrade Moodle 5.2 → 5.3 mit FlexAccess-Daten (tool #11) | 5.2.4+ mit FlexAccess 2026102600 → 5.3+ (`public/`) mit 2026102601 | „UPGRADE OK“, 0 Debug-Zeilen, Datenbestand unverändert, Anmeldung vor und nach dem Upgrade erfolgreich |
| Tabellenabdeckung tool_mergeusers | 4.5 / 5.3 | Lücke 0 (4.5 mit `survey_answers` unter „review“, 5.3 ohne Survey-Tabelle) |
| Release-ZIPs | alle vier | `verify_zips.sh 2026102601`: Lock-Prüfung und Abruf wie im Gate auf den gelieferten ZIPs; SHA-256 im Manifest |

## Rot- und Negativnachweise

| Gegenstand | Lauf | Ergebnis |
|---|---|---|
| tool #18: Test ohne pdftotext | Binary entfernt, `credential_issue_test::test_the_package_holds_one_complete_consistent_credential_set` | 1 Failure „pdftotext (poppler-utils) is required …“ (früher: grünes `Incomplete`) |
| enrol #12: Formular-Journeys gegen den alten Stand | `instance-form.spec.js` gegen enrol 2026101800 (Release-ZIP, Testdateien aktuell), 4.5 PostgreSQL | 3 von 5 rot (Rundlauf mit Fenster und Kapazität; 21.–22. Oktober in Europe/Berlin und America/Los_Angeles, z. B. Tag 26 statt 21 angezeigt); 2 grün (nicht Teil des alten Fehlers) |
| tool #17: Stand vor 2026102601 | gleiche Messung mit dem Code von 2026102600, PostgreSQL 4.5 | PHP-Speicher 100 / 1.000 / 10.000 Waisen: 100 KB / 0,5 MB / 1,6 MB (linear) |
| tool #17: verworfene Varianten | PostgreSQL 4.5, Messung je Variante | ein `INSERT … SELECT … NOT EXISTS`: 10.000 → 4 s, 100.000 → 466 s; `EXISTS` gegen `DISTINCT`-Unterabfrage: 100.000 → 745 s; mit Schlüsselblöcken: 100.000 → 8 s |

## Abweichungen und Befunde während der Läufe

- **Abgebrochene Messläufe hinterließen laufende Datenbankabfragen** (PostgreSQL arbeitete eine abgebrochene Abfrage
  22 Minuten weiter) und verfälschten die nächsten Messungen. Die Abfragen wurden beendet, die Messungen wiederholt; die
  Zahlen oben stammen aus ungestörten Läufen.
- **PHPUnit-Tabellen und PostgreSQL-Statistik:** PHPUnit rollt zurück; PostgreSQL behält tote Zeilen und plant ohne
  aktuelle Statistik für leere Tabellen. Der Test führt nach seinen Massen-Fixtures `ANALYZE` aus (wie Autovacuum im
  Betrieb), die Messskripte zusätzlich `VACUUM ANALYZE` vor der Messung. Die Voraussetzung ist im Kapazitätsdokument
  beschrieben.
- **`find_orphan_restriction_userids()`** ignorierte zunächst seine neue Obergrenze (`get_fieldset_sql` kennt kein
  Limit); vom Test `test_orphan_repair_runs_in_batches_with_a_bounded_list` gefunden, vor dem Commit behoben.

## Nicht nachgewiesen

- **GitHub-CI auf 2026102601** (noch nicht hochgeladen), insbesondere der neue pdftotext-Schritt der Workflows.
- PHP 8.2/8.4 (nur GitHub), WebKit, Behat mit JavaScript; Playwright/LTI auf 5.0/5.2 (nach D2 nicht verlangt);
  Firefox nur im Gate.
- PHPStan (nicht eingerichtet, hier nicht installierbar); NVDA (manuell).
- Lasttests (k6/JMeter): ausgenommen.
- Abgleich oberhalb von 100.000 Waisen (nicht gemessen, nicht zugesagt).
