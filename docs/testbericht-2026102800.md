# Testbericht FlexAccess 2026102800 (Re-Audit tool #11 / NVDA vom 28.10.2026)

Lokale Nachweisläufe (L-800) am 28.10.2026 (Uhr der Testumgebung, UTC, 08:59–13:50) auf den Commits unten. Alle
Zahlen stammen aus den Protokollen; nichts ist geschätzt. Zuordnung zu den Kriterien:
[abnahme-2026102800.md](abnahme-2026102800.md).

## Gegenstand

| Plugin | Version | Commit (lokal, nicht gepusht) |
|---|---|---|
| auth_flexaccess | 2026102800 | `9dbc10b2aeefce4dbf37a05fb73738e386299ce8` |
| enrol_flexaccess | 2026102800 | `9b79657a0f7873fc4da99dc38e80911507bb6951` |
| mod_flexaccess | 2026102800 | `86904db34a7731b77a08903f92e7763f61c2a103` |
| tool_flexaccess | 2026102800 | `b4701b79119d07078ed267796bf3ffe71eaeec3d` |
| tool_mergeusers | 2026081302 | `61c13f0484986ba78898b1760464adfe2eb97aae` (gepinnt) |

Danach folgt in tool nur ein Commit mit diesem Bericht, der Abnahmematrix, einer Ergänzung von
`docs/accessibility-nvda.md` und der erweiterten Versionsangabe im NVDA-Workflow (Chromium, Playwright, Guidepup,
Moodle-Commit); kein Plugin-Code. Umgebungen: PHP 8.3.6; Moodle 4.5.15 und 5.3+ (Build 20261013, `public/`);
PostgreSQL 16 (4.5) und 17 (5.3); Playwright 1.62.1 mit Chromium.

## Ergebnisse

| Prüfung | Umgebung | Ergebnis |
|---|---|---|
| Familien-Resolver-Selbsttest | Python 3 | 8 Tests OK |
| Statische Prüfungen (phplint, validate, savepoints, mustache, Codechecker ohne Warnung, phpdoc ohne Warnung) | 4.5 und 5.3, alle vier Plugins | grün |
| PHPUnit | 4.5 PostgreSQL | auth 231 Tests / 1369 Assertions, enrol 247 / 1386, mod 9 / 28, tool 178 / 906; kein Failure, Error, Skip oder Incomplete |
| PHPUnit | 5.3 PostgreSQL 17 | auth 230 / 1361, enrol 247 / 1386, mod 9 / 28, tool 178 / 906; grün, nur PHPUnit-Deprecation-Hinweise des Frameworks (35 / 47 / 4 / 38) |
| Playwright enrol, Chromium | 4.5 / 5.3 | 10/10 / 10/10 bestanden, darunter „role and cohort restrictions can be administered per course“ |
| Playwright tool, Chromium | 4.5 | 35 bestanden, 2 übersprungen (Dunkelmodus erst ab 5.3: 18-Seiten-Scan dunkel, Kontrast dunkel) |
| Playwright tool, Chromium | 5.3, `public/` | 37/37 bestanden (u. a. 18-Seiten-axe-Scan hell und dunkel) |
| Zwei-Site-LTI, Chromium | 4.5 | 15/15 bestanden: Provider P1–P11 (11/11), Consumer C1–C3, C5 (4/4); Firefox-Variante C4 nur im Workflow |
| Zwei-Site-LTI, Chromium | 5.3 | 15/15 bestanden (wie 4.5) |
| NVDA-Journeys, Ersatz-Sprachkanal aus dem Chromium-Accessibility-Baum (nicht im Repository) | 5.3, `public/`, PostgreSQL 17 | 16/16 bestanden (Negativkontrolle, N01–N09, N10–N12 hell und dunkel) – belegt die Moodle-seitigen Abläufe, **nicht** das echte NVDA |
| NVDA-Workflow „NVDA screen reader“ auf Windows | GitHub | **nicht ausgeführt** – erster Lauf nach dem Upload |
| Rot/Grün Rollen-Einschränkungen | lokal, vor dem Commit | ohne Korrektur in `restrictions.php` rot (Rollenoptionen `[""]`), mit Korrektur grün |

## Offen bis zum Upload

- Erster Lauf von „NVDA (Windows, Moodle 5.3)“ auf den hochgeladenen SHAs; danach als Required Status Check eintragen.
- „LTI interop“ (enrol) auf den hochgeladenen SHAs, jetzt bei jedem Push; Firefox-Consumer-Journey C4 nur dort.
- Plugin-CI aller vier Repositories auf den neuen HEADs.
