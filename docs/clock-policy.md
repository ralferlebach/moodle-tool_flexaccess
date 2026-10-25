# Zeit in der FlexAccess-Familie (Clock-API, Stand 2026102500)

Gilt für auth, enrol, mod und tool_flexaccess: Produktionscode, CLI-Skripte, PHPUnit-Tests, Behat-Kontexte,
Playwright-Fixtures (`seed.php`, `merge_cli.php`, `probe.php`, LTI-`cli.php`) und Last-Skripte.

## Regel

- **Zeitpunkte** (jetzt, Ablauf, Fenstergrenzen, Zeitstempel) kommen ausschließlich aus
  `\core\di::get(\core\clock::class)->time()` oder aus einem übergebenen `$now`, das Vorrang hat.
- **PHPUnit:** Jede Testklasse, die Zeitpunkte braucht, friert die Moodle-Uhr in `setUp()` ein
  (`$this->clock = $this->mock_clock_with_frozen()`) und liest alle Bezugszeitpunkte aus `$this->clock`.
  Helfer und Geschwisteraufrufe eines Szenarios verwenden damit denselben Zeitpunkt; Zeitsprünge sind
  ausdrücklich (`$this->clock->bump()`, `set_to()`), nie Warten.
- **Fixtures und CLI:** Ein Skript liest die Uhr einmal (`$now`) und verwendet diesen Wert für die ganze
  Vorbereitung.
- **Dauern** (Zeitbudgets, Laufzeitmessung) sind keine Zeitpunkte: Sie messen monoton mit
  `\tool_flexaccess\local\stopwatch` (über `\core\di`, austauschbar; intern `hrtime()`). Eine eingefrorene
  Kalenderuhr darf ein Budget nicht anhalten, eine springende Wanduhr es nicht vorzeitig beenden.
- **Formatierung** eines gegebenen Zeitstempels (`userdate()`, `date($format, $timestamp)`) liest keine Uhr.

## Gate

`tests/clock_usage_test.php` in jedem der vier Plugins durchsucht alle PHP-Dateien des Plugins mit dem
PHP-Tokenizer (Kommentare und Zeichenketten sind keine Aufrufe) und alle JavaScript-Dateien. Es schlägt fehl
bei `time()`, `microtime()`, `hrtime()`, `gettimeofday()`, `localtime()`, `date_create()`,
`new DateTime()`/`new DateTimeImmutable()` ohne Argument oder mit `'now'`, `date()`/`gmdate()`/`idate()`/
`strtotime()`/`mktime()`/`gmmktime()`/`getdate()`/`usergetdate()` ohne Zeitstempel sowie `Date.now()` und
`new Date()` ohne Argument. Ein zweiter Test prüft, dass der Scanner diese Fälle findet und Methodenaufrufe
wie `$clock->time()` nicht.

## Dokumentierte Ausnahmen

Eine Ausnahme braucht einen Eintrag in `EXCEPTIONS` des Gates **und** die Markierung `clock-exception` in der
Zeile; ungenutzte Einträge lassen das Gate ebenfalls fehlschlagen.

| Plugin | Datei | Aufruf | Begründung |
|---|---|---|---|
| tool | `classes/local/stopwatch.php` | `hrtime()` (2×) | monotone Dauer, kein Zeitpunkt |
| enrol | `tests/core_time_semantics_test.php` | `time()` (1 Helfer) | Moodle-Core liest für seine Einschreibefenster selbst `time()` (`round(time(), -2)`, `is_enrolled()`); der Abgleich mit Core braucht dieselbe Quelle. Die eingefrorene Uhr wird vor jedem Vergleich auf diesen Wert gesetzt. |

Sonst gibt es keine direkten Abfragen der Wanduhr in der Familie (Stand 2026102500: 0 in Produktion, Tests,
CLI und Fixtures außerhalb dieser Ausnahmen).

## Stabilität

Die Klassen mit Zeitgrenzen (enrol `clock_test`, `core_time_semantics_test`, `enrolment_lifecycle_test`,
`enrol_expiry_test`, `instance_form_roundtrip_test`; tool `clock_test`, `recovery_postcondition_test`,
`invitation_test`; auth `clock_test`, `lifecycle_test`, `merge_evidence_test`) werden im Nachweislauf zehnmal
hintereinander ausgeführt, ebenso die Playwright-Suiten mit Zeitfenstern (`--repeat-each=10`). Ergebnis im
Testbericht der Version.
