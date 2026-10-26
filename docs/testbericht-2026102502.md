# Testbericht FlexAccess 2026102502 (Produktivcode-Audit und Nacharbeitsliste, 25.10.2026)

Lokale Nachweisläufe am 25.10.2026 (Uhr der Testumgebung, UTC). Alle Zahlen stammen aus den Protokollen; nichts ist
geschätzt. Die Zuordnung zu den Akzeptanzkriterien steht in [abnahme-2026102502.md](abnahme-2026102502.md).

## Gegenstand

| Plugin | Version | Commit (lokal, nicht gepusht) |
|---|---|---|
| auth_flexaccess | 2026102502 (1.2.0) | `fb36d6c` |
| enrol_flexaccess | 2026102502 (1.2.0) | `308acd0` |
| mod_flexaccess | 2026102502 (1.2.0) | `71e978c` |
| tool_flexaccess | 2026102502 (1.2.0) | `07d506c` (Code); dieser Bericht und der Release-Lock folgen in eigenen Commits |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Umgebungen: PHP 8.3.6; Moodle 4.5.15, 5.0.11, 5.2.4+, 5.3+; PostgreSQL 16.15 (4.5/5.0/5.2) und 17.10 (5.3); MariaDB
10.11.14 (4.5/5.0/5.2) und 11.4.13 (5.3); Playwright 1.62.1 mit Chromium.

## Ergebnisse

Abschlusslauf auf den Commits oben: 25.10.2026, 23:35 bis 26.10.2026, 00:06 UTC (statische Prüfungen, PHPUnit 4.5/5.3,
MariaDB 4.5, `make check`, Upgrades 5.2 → 5.3, Tabellenabdeckung, Family-Selbsttests). Die Browser-, LTI-, Versions- und
Deinstallationsläufe davor liefen am 25.10.2026 auf demselben Code; danach änderten sich nur noch die Abhängigkeitserklärung
in `auth/version.php` (von den späteren Läufen abgedeckt) sowie zwei Testdateien (siehe „Abweichungen“).

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| Statische Prüfungen (phplint, validate, savepoints, mustache, codechecker und phpdoc mit `--max-warnings 0`) | 4.5 und 5.3, alle vier Plugins | grün |
| PHPUnit | 4.5 PostgreSQL 16 | auth 227 / enrol 247 / mod 9 / tool 176 Tests, alle OK |
| PHPUnit | 4.5 MariaDB 10.11 | dieselben Zahlen, alle OK |
| PHPUnit | 5.3 PostgreSQL 17 | auth 227 (1 übersprungen) / enrol 247 / mod 9 / tool 176, alle OK (PHPUnit-Deprecation-Hinweise 35/47/4/38) |
| PHPUnit | 5.3 MariaDB 11.4 | dieselben Zahlen, alle OK |
| PHPUnit | 5.0 und 5.2 × PostgreSQL 16 / MariaDB 10.11 | auth 227 (1 übersprungen) / enrol 247 / mod 9 / tool 176, alle OK |
| Family-Resolver-Selbsttests | – | 17/17 |
| Playwright | 4.5 PostgreSQL | auth 7, enrol 10, mod 4, tool 31 bestanden (+1 übersprungen: Dark Mode erst ab 5.3); neu: `method-contract.spec.js` 3, `batch-integrity.spec.js` 2 |
| Playwright | 5.3 PostgreSQL | auth 7, enrol 10, mod 4, tool 32 bestanden |
| Playwright | 4.5 MariaDB 10.11 | `method-contract.spec.js` und `batch-integrity.spec.js`: 5/5 |
| Zwei-Site-LTI 1.3 | 4.5 und 5.3 (PostgreSQL), je frisch aufgesetzt | 14/14 und 14/14 (bisher 10) |
| Upgrade auf 2026102502 (Moodle 4.5) | aus 2026092900, 2026102400, 2026102500, 2026102501 | alle OK; Teilnehmerrolle danach: Nutzer nur mit FlexAccess `enrol_flexaccess#2`, Nutzer mit zusätzlicher manueller Einschreibung `(ohne Komponente)#0` und `enrol_flexaccess#2`; 0 Zugangsdaten im Archiv |
| Upgrade Moodle 5.2 → 5.3 mit FlexAccess-Daten | FlexAccess 2026102501 auf 5.2 → Core 5.3 + 2026102502; und 2026102502 auf 5.2 → Core 5.3 | OK, 0 Debugzeilen, 2 Konten / 1 Instanz / 2 Einschreibungen erhalten, Kennwortanmeldung vor und nach OK |
| Deinstallation und Neuinstallation (enrol #15) | 4.5 PostgreSQL (Site aus dem Upgradetest), 5.3 PostgreSQL 17 (Playwright-Site), je zwei Zyklen | Reihenfolge tool, mod → enrol → auth über `admin/cli/uninstall_plugins.php`; FlexAccess-Zuweisungen 2 → 0 bzw. 20 → 0, manuelle Zuweisungen 3 → 3 bzw. 2 → 2, Teilnehmerrolle bleibt (manuell genutzt), Einschränkungsrolle entfernt; Neuinstallation ohne doppelte Rolle; auth erst nach Umstellen der Konten (Core-Regel) |
| `make check` (vorhandenes `makefile`) | 4.5, alle vier Plugins | phpcs ohne Befund, phpdoc ohne Befund, phpcpd „No clones found“, PHPUnit wie oben; Exit 0 |
| Tabellenabdeckung tool_mergeusers | 4.5 / 5.3 | keine Lücke |
| Abfragebudget Waisen-Durchlauf (tool #17) | 4.5 PostgreSQL / MariaDB, 0/100/1000/10000 Waisen | PostgreSQL Lesen 6, Schreiben 0/1/2/20; MariaDB Lesen 4, Schreiben 0/2/20/200; zweiter Lauf jeweils 0 Schreibzugriffe |

## Rot-Nachweise (neue Tests gegen 2026102501)

| Issue | Lauf | Ergebnis gegen 2026102501 | mit 2026102502 |
|---|---|---|---|
| tool #14 | Playwright `method-contract.spec.js`, 4.5 | 3 von 3 rot (GET mit gültigem Sesskey änderte Daten) | 3/3 grün |
| tool #15 | Browser-Nachstellung `batchdownload.php` (POST, Administrator) | HTTP 404 „Call to undefined function send_file()“; danach `timeissued` gesetzt und beide Kennwort-Hashes geändert – Kennwörter rotiert, kein Paket | Playwright lädt das Paket; Wiederholung abgewiesen |
| tool #15 | `credential_issue_test::test_reset_credentials_is_all_or_nothing` | rot (ein Konto still ausgelassen) | grün |
| tool #16 | `batch_import_schema_test` (15 Tests) | 8 rot (A2003, A50000, Zeile ohne Benutzername, manipulierte Dimension, hoch komprimiert, zu viele Zellen, zweites Blatt, Bestand unverändert) | 15/15 grün |
| tool #17 | Abfragezählung des Waisen-Durchlaufs (PostgreSQL) | 0/100/1000/10000 Waisen: Lesen 3/104/1004/10004, Schreiben 0/100/1000/10000 | Lesen 6/6/6/6, Schreiben 0/1/2/20; zweiter Lauf 0 |
| enrol #15 | `uninstall_test::test_no_dependency_cycle_blocks_uninstall` mit `version.php` von auth 2026102501 | rot: „Dependency cycle among: auth_flexaccess, enrol_flexaccess“ | grün |
| enrol #15 | `admin/cli/uninstall_plugins.php` nach Entfernen von tool und mod | mit der Schleife: „Can not be uninstalled: enrol_flexaccess“ | „Will be uninstalled“, Deinstallation erfolgreich |

## Abweichungen und behobene Testfehler

- **`recovery_postcondition_test::test_missing_participant_role`** entfernte die Teilnehmerrolle komponentenlos; seit
  der Herkunft `enrol_flexaccess` (enrol #15) traf das die Zuweisung nicht mehr. Test entfernt jetzt die geschützte
  Zuweisung. Kein Fehler im Produktivcode.
- **`reconciliation_scale_test`** erwartete zunächst das Schreibbudget von PostgreSQL (100 Zeilen je Insert); MariaDB
  schreibt 50 Zeilen je Anweisung. Budget an den Treiber angepasst (`ceil(n / 50) + 1`).
- **LTI-Journey „AGS …“**: Der Kursabschluss wird in Moodle unmittelbar mit dem Aktivitätsabschluss aggregiert (einziges
  Kriterium); die Erwartung „Kurs wartet noch nach Aktivitätsabschluss“ war falsch und wurde durch „vorher nicht
  abgeschlossen, nachher abgeschlossen und genau einmal geliefert“ ersetzt. Die verzögerte Cron-Ausführung setzt die
  Wartezeit der gescheiterten Ad-hoc-Aufgabe auf „fällig“ (`nextruntime = jetzt − 1`), statt eine Zukunftszeit an den
  Runner zu übergeben (der sonst bis dahin wartet).

## Nicht nachgewiesen

- **GitHub-CI** auf den 2026102502-Ständen (noch nicht hochgeladen).
- PHP 8.2/8.4 (nur GitHub), WebKit, Behat mit JavaScript, Playwright und LTI auf 5.0/5.2, Playwright gegen MariaDB 11.4
  (5.3), LTI gegen MariaDB.
- **PHPStan**: in keinem Workflow und keinem `makefile` eingerichtet; in dieser Umgebung nicht installierbar.
- Lasttests (k6/JMeter): für diesen Auftrag ausgenommen, nicht bestanden.
- Echter paralleler Zugriff zweier Webanfragen auf denselben Batch: im Test durch eine gehaltene Sperre nachgestellt
  (PostgreSQL- und MySQL-Sperren sind innerhalb einer Verbindung wiedereintrittsfähig, deshalb mit der Datensatz-Sperre).
- Firefox im Zwei-Site-LTI-Lauf: nur in der Gate-Konfiguration (lokal Chromium).
