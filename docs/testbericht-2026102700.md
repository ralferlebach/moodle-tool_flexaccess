# Testbericht FlexAccess 2026102700 (Re-Audit vom 27.10.2026)

Lokale Nachweisläufe (L-700) am 27.10.2026 (Uhr der Testumgebung, UTC, 12:02–16:38) auf den Commits unten. Alle Zahlen
stammen aus den Protokollen; nichts ist geschätzt. Zuordnung zu den Kriterien: [abnahme-2026102700.md](abnahme-2026102700.md).

## Gegenstand

| Plugin | Version | Commit (lokal, nicht gepusht) |
|---|---|---|
| auth_flexaccess | 2026102700 | `602df7e123274fb1a8d61cfcdc5437c1883390cb` |
| enrol_flexaccess | 2026102700 | `0132205a4c0490eb9e36b250a98e9ad67672a31a` |
| mod_flexaccess | 2026102700 | `afe1273674bcfe10639697dab285509464492b43` |
| tool_flexaccess | 2026102700 | `3d00cb3e7f12a54c5dec654a4421e86e926c4176` |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Danach folgen nur dieser Bericht mit der Abnahmematrix (tool) und der Release-Lock (tool); sie ändern keinen
Plugin-Code. Umgebungen: PHP 8.3.6; Moodle 4.5.15, 5.0.11, 5.2.4+, 5.3+; PostgreSQL 16.15 (4.5/5.0/5.2) und 17.10
(5.3); MariaDB 10.11.14 (4.5/5.0/5.2) und 11.4.13 (5.3); Playwright 1.62.1 mit Chromium; pdftotext 24.02.0.

## Ergebnisse

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| PHPUnit, alle vier Plugins | 4.5 PostgreSQL 16, 4.5 MariaDB 10.11, 5.0 PostgreSQL, 5.0 MariaDB, 5.2 PostgreSQL, 5.2 MariaDB, 5.3 PostgreSQL 17, 5.3 MariaDB 11.4 | alle 8 grün, kein Failure, Error, Skip oder Incomplete: auth 231 Tests (4.5, mit „survey answer“) bzw. 230 (5.x), enrol 247, mod 9, tool 178; auf 5.x nur PHPUnit-11-Deprecation-Hinweise des Test-Frameworks |
| Statische Prüfungen (phplint, Codechecker ohne Warnung, phpdoc, validate, savepoints, mustache) | 4.5 und 5.3, alle vier Plugins | grün |
| Familien-Resolver-Selbsttest | Python 3 | 17 Tests OK |
| Playwright, Chromium | 4.5 PostgreSQL | auth 7, enrol 10, mod 4, tool 34 bestanden + 1 übersprungen (Dark-Mode-Kontrast erst ab 5.3) |
| Playwright, Chromium | 5.3 PostgreSQL 17, `public/`-Webroot | auth 7, enrol 10, mod 4, tool 35 bestanden |
| Playwright tool, Chromium | 4.5 MariaDB 10.11 | 34 bestanden + 1 übersprungen (wie oben) |
| Playwright tool, Chromium | 5.3 MariaDB 11.4, `public/`-Webroot | 35 bestanden |
| davon neu: „interrupted delivery after the server committed …“ (tool #15) | die vier tool-Läufe | 4/4 bestanden |
| davon neu: „LTI identities: without the right to change users …“ (auth #14) | die vier tool-Läufe | 4/4 bestanden |
| Zwei-Site-LTI, getrennt wie im Gate | 4.5 und 5.3, PostgreSQL | je Version Provider 11/11, Consumer 4/4 (Chromium; die fünfte Consumer-Journey ist die Firefox-Variante, nur im Gate) |
| davon neu: P11 „a launch in flight while the merge commits …“ | 4.5 und 5.3 | 2/2 bestanden |
| Zwei-Site-LTI, alle zusammen | 5.3 | 15/15 |
| `make check` | 4.5, alle vier Plugins | Exit 0 (phpcs, phpdoc, phpcpd „No clones found“, PHPUnit) |
| Upgrade auf 2026102700 (4.5, PostgreSQL) | aus 2026102601 und aus `origin/main` (2026092900) | beide „UPGRADE OK“, alle vier Versionen 2026102700; Teilnehmerrolle wie in 2026102601 beschrieben (aus 2026102601 `enrol_flexaccess#2`; aus `origin/main` beim Nutzer mit zusätzlicher manueller Einschreibung `(ohne Komponente)#0` und `enrol_flexaccess#2`) |
| Upgrade Moodle 5.2 → 5.3 mit FlexAccess-Daten (tool #11) | 5.2.4+ mit FlexAccess 2026102601 → 5.3+ (`public/`) mit 2026102700 | „UPGRADE OK“, 0 Debug-Zeilen, Datenbestand vorher = nachher (2 Konten, 1 Methode, 2 Einschreibungen), Anmeldung vor und nach dem Upgrade erfolgreich |
| Tabellenabdeckung tool_mergeusers | 4.5 / 5.0 / 5.2 / 5.3 | Lücke 0; `sessions` jetzt „excluded“ (10 statt 9), Ergebnis in `auth/docs/merge_policy.md` §11 |
| Release-ZIPs | alle vier | `verify_zips.sh 2026102700`: Lock-Prüfung und Abruf wie im Gate auf den gelieferten ZIPs; SHA-256 im Manifest |

Nicht wiederholt, weil der betroffene Code seit 2026102601 unverändert ist (Läufe L-601 gelten weiter):
Mengengerüst des Abgleichs (tool #17) und echte Deinstallation/Neuinstallation (enrol #15). Der Ausschluss von
`sessions` aus der generischen Zusammenführung berührt beides nicht.

## Rot- und Negativnachweise

| Gegenstand | Lauf | Ergebnis |
|---|---|---|
| Family-Gate 2026102601 | Inhalt des GitHub-Stands von tool nachgerechnet (`digest --without-lock`) | `83e8bf6947e8deea…` = Ist-Wert im Gate-Log; einzige inhaltliche Abweichung zu den gelieferten Bäumen: die vier `pix/pluginlogo.png` |
| Sitzung des entfernten Kontos (auth #13, enrol #10) | Zwei-Site-LTI 4.5, P11 vor der Korrektur | rot: die Sitzung des zusammengeführten auth_lti-Kontos wurde nach dem Merge weiter als dieses Konto bedient (Sitzungszeile erst auf das behaltene Konto verschoben, bei der nächsten Anfrage von Moodle zurückgeschrieben) |
| dasselbe in PHPUnit | `merge_matrix_test::test_the_removed_account_has_no_running_session_after_the_merge` ohne die Korrektur | 1 Failure (Sitzung des entfernten Kontos besteht, verschoben auf das behaltene) |
| Abgleichsaufgabe bei gesperrtem Paar | `merge_service_test::test_a_reconciliation_that_finds_the_pair_locked_is_retried` ohne die Korrektur | 1 Fehler „A locked pair must fail the task.“ (Aufgabe endete ohne Prüfung und Wiederholung) |
| Klartextsuche der neuen tool-#15-Journey | Positivkontrolle: absichtlich gesetzter Wert in der Plugin-Konfiguration | gefunden (`config_plugins`); die Journey findet in beiden Paketen nichts |

## Abweichungen und Befunde während der Läufe

- **Uhr-Regel:** Die drei neuen auth-Tests lasen die Zeit zunächst mit `time()`; `clock_usage_test` meldete das im
  ersten Lauf von Segment A. Korrigiert (Uhr über `\core\di`), Segment A und B danach vollständig neu ausgeführt; die
  Zahlen oben stammen aus dem zweiten Lauf.
- **Testhilfe:** Die neuen CLI-Konten erhielten zunächst Kennwörter ohne garantierte Ziffer und scheiterten zufällig
  an der Kennwortrichtlinie der Testseite; korrigiert vor den Nachweisläufen.
- **Neustart der Testumgebung:** Der erste Lauf von Segment D (make check, Upgrades, Abdeckung) lief gegen gestoppte
  Datenbanken und lieferte keine Ergebnisse; nach dem Neustart der Datenbanken vollständig wiederholt.

## Nicht nachgewiesen

- **GitHub-CI und Family-Gate auf 2026102700** (noch nicht hochgeladen), insbesondere pdftotext-Schritt, PHP 8.2/8.4
  und die Firefox-Consumer-Journey.
- PHPStan (nach E2 kein Pflicht-Gate, Bestätigung ausstehend); NVDA (manuell).
- Eine Datenbanktransaktion, die mitten in der Zusammenführung angehalten wird; zwei echte parallele Webanfragen auf
  dasselbe Kontenpaar (Sperre mit Dateisperren in einem Prozess belegt).
- Playwright/LTI auf 5.0/5.2 (nach D2 nicht verlangt); WebKit.
