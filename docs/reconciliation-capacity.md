# Abgleich: Mengengerüst, Budgets und Verhalten oberhalb der Grenze (tool_flexaccess #17, Entscheidung D4)

Stand 2026102601. Grundlage ist die Entscheidung D4 vom 26.10.2026: Ein konstanter Speicherbedarf für beliebig große
Installationen ist keine Abnahmevoraussetzung. Verlangt sind ein dokumentiertes Mengengerüst, nachgewiesene Peak-RAM-,
Abfrage- und Laufzeitbudgets und ein geregeltes Verhalten oberhalb der Grenze. Dieses Dokument legt sie fest. Die
Messwerte stammen aus `tests/reconciliation_scale_test.php`; die Läufe stehen im Testbericht 2026102601.

## Was wo verarbeitet wird

| Teil | Verarbeitung | PHP-Speicher |
|---|---|---|
| Kontenprüfung (`reconciliation::run`, Aufgabe und Upgrade) | Abschnitte von `BATCH` = 200 Konten; die Fälle eines Abschnitts mit einer Abfrage; Zeitbudget je Lauf, Fortsetzung als Ad-hoc-Aufgabe | durch die Abschnittsgröße begrenzt |
| Verwaiste Einschränkungsrollen (`record_orphan_restrictions`) | neue Fälle in Schlüsselblöcken von `ORPHAN_CHUNK` = 1000 Waisen (nächster Block, Prüfung vorhandener Fälle, Masseneinfügung); Wiederöffnen und Auflösen je eine mengenbasierte `UPDATE`-Anweisung mit einem korrelierten Prädikat aus auth_flexaccess (nur Indexzugriffe je Fall), je mit vorgeschalteter Zählung | höchstens ein Block (gemessen ≈ 0,7 MB ab 1000 Waisen), unabhängig von der Gesamtzahl |
| Prüfbericht ohne Änderung (Systemstatus, Vorschau) | Zählungen exakt, Nutzerlisten je Regel höchstens `REPORT_IDS` = 200; Zeitbudget 60 s | durch die Listengröße begrenzt |
| Reparatur verwaister Rollen (Systemstatus) | Abschnitte von 200 Nutzern, bis nichts mehr geändert wird; das Ereignis nennt die genaue Zahl und höchstens 200 Nutzer | durch die Abschnittsgröße begrenzt |
| Zähler „automatisch reparierbare Inkonsistenzen“ (Systemstatus) | höchstens `REPAIRABLE_LIMIT` = 5000 Inkonsistenzen je Seitenaufruf | durch die Grenze begrenzt |

## Mengengerüst und Budgets

| Größe | Festlegung |
|---|---|
| Geprüftes Mengengerüst | bis **100.000** verwaiste Einschränkungsrollen bzw. Fälle in einem Durchlauf (Kapazitätsprüfung `FLEXACCESS_SCALE_LARGE=1`); in jeder CI bis 10.000 |
| Abfragebudget je Waisen-Durchlauf | Lesen: höchstens 3 je Block von 1000 Waisen plus 8; Schreiben: die Masseneinfügungen (der Treiber teilt sie in Anweisungen zu 100 Zeilen bei PostgreSQL, 50 bei MariaDB) plus höchstens 2 `UPDATE`; ein zweiter Durchlauf schreibt nichts |
| PHP-Speicherbudget je Waisen-Durchlauf | 2 MB (Test); gemessen ≈ 0,7 MB ab 1000 Waisen, gleichbleibend bis 100.000 |
| Laufzeitbudget je Waisen-Durchlauf | 30 s bis 10.000 Waisen (CI, großzügig für geteilte Runner); bis 100.000 gemessen, siehe unten |

Messwerte (Moodle 4.5 und 5.3, PostgreSQL und MariaDB; erster Durchlauf / zweiter Durchlauf):

| Umgebung | Waisen | Lesen 1./2. | Schreiben 1./2. | PHP-Peak 1./2. | Laufzeit 1./2. |
|---|---:|---:|---:|---:|---:|
| PostgreSQL 16, Moodle 4.5 | 0 | 5 / 5 | 0 / 0 | 8 KB / 8 KB | 0,00 s / 0,00 s |
| PostgreSQL 16, Moodle 4.5 | 100 | 7 / 6 | 1 / 0 | 101 KB / 58 KB | 0,01 s / 0,00 s |
| PostgreSQL 16, Moodle 4.5 | 1.000 | 9 / 8 | 2 / 0 | 708 KB / 530 KB | 0,04 s / 0,02 s |
| PostgreSQL 16, Moodle 4.5 | 10.000 | 36 / 35 | 20 / 0 | 716 KB / 662 KB | 0,34 s / 0,16 s |
| PostgreSQL 16, Moodle 4.5 | 100.000 | 306 / 305 | 200 / 0 | 716 KB / 662 KB | 7,96 s / 5,47 s |
| PostgreSQL 17, Moodle 5.3 | 0 | 5 / 5 | 0 / 0 | 8 KB / 8 KB | 0,00 s / 0,00 s |
| PostgreSQL 17, Moodle 5.3 | 100 | 7 / 6 | 1 / 0 | 101 KB / 58 KB | 0,01 s / 0,00 s |
| PostgreSQL 17, Moodle 5.3 | 1.000 | 9 / 8 | 2 / 0 | 708 KB / 530 KB | 0,03 s / 0,01 s |
| PostgreSQL 17, Moodle 5.3 | 10.000 | 36 / 35 | 20 / 0 | 716 KB / 662 KB | 0,40 s / 0,18 s |
| PostgreSQL 17, Moodle 5.3 | 100.000 | 306 / 305 | 200 / 0 | 716 KB / 662 KB | 10,91 s / 7,12 s |
| MariaDB 10.11, Moodle 4.5 | 0 | 5 / 5 | 0 / 0 | 23 KB / 23 KB | 0,00 s / 0,00 s |
| MariaDB 10.11, Moodle 4.5 | 100 | 7 / 6 | 2 / 0 | 89 KB / 72 KB | 0,01 s / 0,00 s |
| MariaDB 10.11, Moodle 4.5 | 1.000 | 9 / 8 | 20 / 0 | 526 KB / 512 KB | 0,06 s / 0,02 s |
| MariaDB 10.11, Moodle 4.5 | 10.000 | 36 / 35 | 200 / 0 | 651 KB / 644 KB | 0,64 s / 0,36 s |
| MariaDB 10.11, Moodle 4.5 | 100.000 | 306 / 305 | 2000 / 0 | 651 KB / 644 KB | 19,88 s / 16,02 s |
| MariaDB 11.4, Moodle 5.3 | 0 | 5 / 5 | 0 / 0 | 23 KB / 23 KB | 0,00 s / 0,00 s |
| MariaDB 11.4, Moodle 5.3 | 100 | 7 / 6 | 2 / 0 | 89 KB / 72 KB | 0,01 s / 0,00 s |
| MariaDB 11.4, Moodle 5.3 | 1.000 | 9 / 8 | 20 / 0 | 526 KB / 512 KB | 0,05 s / 0,01 s |
| MariaDB 11.4, Moodle 5.3 | 10.000 | 36 / 35 | 200 / 0 | 651 KB / 644 KB | 0,59 s / 0,27 s |
| MariaDB 11.4, Moodle 5.3 | 100.000 | 306 / 305 | 2000 / 0 | 651 KB / 644 KB | 16,37 s / 13,06 s |

„Lesen“/„Schreiben“ sind die von Moodle gezählten Datenbankanweisungen (`perf_get_reads`/`perf_get_writes`), „PHP-Peak“ die
Spitze von `memory_get_peak_usage()` nach Zurücksetzen vor dem Durchlauf, „Laufzeit“ die Wanduhrzeit des Durchlaufs. Der
zweite Durchlauf findet alle Fälle schon vor und schreibt nichts. Lauf: `tests/reconciliation_scale_test.php` mit
`FLEXACCESS_SCALE_LARGE=1`, PHP 8.3.6, ein Kern der Testumgebung, Datenbank lokal; Protokolle `scale-pg45`, `scale-pg53`,
`scale-m45`, `scale-m53` (Testbericht 2026102601). Bei 100.000 Waisen liegt die Laufzeit damit bei 8–11 s (PostgreSQL) bzw.
16–20 s (MariaDB) für den ersten Durchlauf.

Zum Vergleich der Stand vor 2026102601 (alle Waisen und Fälle als Arrays in PHP, PostgreSQL, Moodle 4.5): bei
100 / 1.000 / 10.000 Waisen 100 KB / 0,5 MB / 1,6 MB PHP-Speicher – linear wachsend.

Verworfene Varianten (gemessen, PostgreSQL): ein einziges `INSERT … SELECT … WHERE NOT EXISTS` gegen die Falltabelle
wird quadratisch, weil PostgreSQL je Zeile den Index der Tabelle durchläuft, die die Anweisung gerade füllt (10.000:
4 s, 100.000: 466 s); `NOT IN`/`EXISTS` gegen die `DISTINCT`-Unterabfrage ließ PostgreSQL die Unterabfrage je Fall neu
auswerten (100.000: 745 s).

**Voraussetzung PostgreSQL:** aktuelle Planer-Statistiken (Autovacuum, wie im Betrieb üblich). Ohne Statistik – etwa
unmittelbar nach einem Massenimport von Nutzern – plant PostgreSQL für leere Tabellen und kann quadratische Pläne
wählen; nach einem Massenimport daher `ANALYZE` ausführen oder Autovacuum abwarten. Der Test führt nach seinen
Massen-Fixtures ebenfalls `ANALYZE` aus (MariaDB/MySQL halten die InnoDB-Statistik selbst aktuell).

## Verhalten oberhalb der Grenze

- **PHP-Speicher:** Es gibt keine Obergrenze, an der der Speicher überläuft – der Waisen-Durchlauf hält höchstens einen
  Block, Kontenprüfung, Bericht und Reparatur arbeiten in festen Abschnitten bzw. mit festen Listengrößen.
- **Laufzeit:** wächst mit der Datenmenge (gemessen etwa linear, siehe oben). Oberhalb des geprüften Mengengerüsts ist sie nicht gemessen und nicht
  zugesagt.
- **Abbruch:** Jeder Block und jede Anweisung ist atomar. Bricht ein Lauf ab (Zeitlimit des Servers, Fehler), bleibt ein konsistenter
  Stand; der Lauf steht weiter auf `running`, und der nächste Lauf (Aufgabe) vollendet ihn und schreibt nur, was noch
  fehlt. Fälle werden nur nach einer vollständigen Kontenprüfung aufgelöst.
- **Anzeige:** Oberhalb von 5000 Inkonsistenzen meldet der Systemstatus „mindestens …“ statt einer genauen Zahl;
  die Reparatur selbst ist davon nicht begrenzt.
