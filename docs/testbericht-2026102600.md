# Testbericht FlexAccess 2026102600 (Nachtrag zu 2026102502, 26.10.2026)

2026102600 ändert gegenüber 2026102502 nur Versionsnummern, eine Journey der Zwei-Site-LTI-Suite und Dokumentation; der
PHP-Code der Plugins ist identisch. Alle übrigen Ergebnisse stehen in [testbericht-2026102502.md](testbericht-2026102502.md),
die Zuordnung zu den Akzeptanzkriterien in [abnahme-2026102502.md](abnahme-2026102502.md) (unverändert gültig).

## Befund aus der CI (Release-Gate vom 26.10.2026, 06:41 UTC, Stand 2026102502)

| Job | Ergebnis |
|---|---|
| Lock (exact commits) | grün |
| PHPUnit (all four), 4.5/5.0/5.2/5.3 × PHP 8.2–8.4 × PostgreSQL/MariaDB | grün |
| LTI 1.3 provider, 4.5 und 5.3 | 10/10 und 10/10 |
| LTI 1.3 consumer, 4.5 und 5.3 | je 4 von 5 (1 rot) |

Rot war „@consumer recovering the platform access keeps the platform identity“: erwartet 77, erhalten 72. Ursache: Die
Journey verglich mit der Note 77, die nur die @provider-Journey „the same resource through a second deployment …“ setzt.
Das Gate führt @consumer- und @provider-Journeys in getrennten Jobs mit je eigenem Site-Paar aus; lokal liefen bis dahin
alle 14 Journeys nacheinander auf einem Site-Paar, deshalb blieb die Abhängigkeit unbemerkt. Kein Fehler im Plugin-Code:
Die Wiederherstellung hielt die Plattform-Identität und die Note (72) korrekt.

Korrektur: Die Journey liest die Note vor der Wiederherstellung und vergleicht danach mit ihr; sie hängt nur noch von den
vorangehenden @consumer-Journeys ab.

## Nachweise 2026102600

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| Zwei-Site-LTI wie im Gate getrennt: nur `@consumer`, frisches Site-Paar | 4.5 und 5.3 (PostgreSQL) | 4/4 und 4/4 (Chromium; der Firefox-Lauf der `@browser`-Journey nur im Gate) |
| Zwei-Site-LTI wie im Gate getrennt: nur `@provider`, frisches Site-Paar | 4.5 und 5.3 (PostgreSQL) | 10/10 und 10/10 |
| Zwei-Site-LTI, alle Journeys auf einem Site-Paar | 4.5 | 14/14 |
| Statische Prüfungen (phplint, validate, savepoints, mustache, codechecker und phpdoc mit `--max-warnings 0`) | 4.5 und 5.3, alle vier Plugins | grün |
| PHPUnit enrol_flexaccess nach dem Versionssprung | 4.5 PostgreSQL | 247 Tests, alle OK |
| Release-Lock: ZIP-Upload simuliert, Lock-Prüfung und Abruf wie im Gate | – | `verify_zips.sh 2026102600`: Lock-Prüfung und Abruf aller drei Geschwister aus den ZIP-Ständen OK |

## Nicht nachgewiesen

- GitHub-CI auf 2026102600 (noch nicht hochgeladen).
- Alles Weitere wie in [testbericht-2026102502.md](testbericht-2026102502.md), Abschnitt „Nicht nachgewiesen“.
