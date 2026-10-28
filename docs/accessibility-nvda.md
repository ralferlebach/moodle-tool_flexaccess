# Barrierefreiheit: Screenreader-Prüfung mit NVDA (tool_flexaccess #11)

Die 13 Schritte unten laufen **automatisiert mit einem echten NVDA** in der GitHub-CI (Workflow „NVDA screen reader“,
`.github/workflows/nvda-screenreader.yml`, Job „NVDA (Windows, Moodle 5.3)“): Windows-Runner, NVDA über Guidepup,
Chromium, eine echte Moodle-5.3-Site mit den vier Plugins in den Commits des Laufs. Jeder Schritt wird über die
Tastatur durch NVDA ausgeführt und prüft, was NVDA tatsächlich gesagt hat. Das Protokoll bleibt als ergänzende
manuelle Prüfung (andere Browser, Firefox, eigene NVDA-Einstellungen) erhalten; ein Durchgang dauert etwa 30 Minuten.

## Automatisierte NVDA-Abdeckung

Testorakel (`tests/playwright/nvda/support.js`): Verglichen wird die Sprachausgabe von NVDA mit dem, was der Browser
NVDA über das fokussierte Element mitteilt (zugänglicher Name, Rolle, Zustände aus dem Accessibility-Baum von
Chromium). Ein Schritt besteht nur, wenn NVDA Name und Rolle und die verlangten Zustände (erforderlich, ungültig,
aktiviert, aktuelle Seite, Fehlermeldung) gesagt hat. Vor jeder Aktion wird das Sprachprotokoll geleert, sodass eine
ältere Ansage nie einen späteren Schritt erfüllen kann; das Protokoll jedes Tests hängt als `nvda-speech.txt` am
Bericht (Artefakt des Laufs).

| Schritt | Test (`tests/playwright/nvda/nvda-journeys.spec.js`) | Was NVDA sagen muss |
|---|---|---|
| 1 | N01 „entry page: window title and level 1 heading are announced“ | Fenstertitel; H1 als „Überschrift Ebene 1“, H2 als Ebene 2 |
| 2 | N02 „entry page: every action is announced with name and role, in the visible order“ | jede Schaltfläche, jeder Link, jedes Feld mit Name und Rolle; Umschalt+Tab zurück zum selben Element |
| 3 | N03 „quick registration: every field is announced with its label and as required“ | Beschriftung und „erforderlich“ für jedes Pflichtfeld |
| 4 | N04 „quick registration: errors are announced at the field they belong to“ | „ungültig“ und der Text der Fehlermeldung am Feld (nach Absenden mit der Tastatur) |
| 5 | N05 „temporary access: the result is announced as text“ | die Bestätigung „Temporary access granted“ als Text (eine Restzeit wird auf der Kursseite nicht angezeigt) |
| 6 | N06 „course management is reached with the keyboard: Course > More > FlexAccess“ | „More“ als Menü, „FlexAccess“ als Menüeintrag; Enter öffnet die Kursverwaltung |
| 7 | N07 „management tabs: the current tab is announced as current, Enter switches“ | jede Registerkarte als Link, die aktive als „aktuelle Seite“, nach dem Wechsel die neue |
| 8 | N08 „credential package: the plan and the confirmation are announced, …“ | Anzahl der Konten, Bestätigungsschaltfläche; nach dem Download antwortet NVDA weiter (das Paket wird ungelesen gelöscht) |
| 9 | N09 „restrictions table: column headers are announced with the cells“ | Spaltenüberschrift mit Zelle („Reference … Student“, „Effect … Deny“) |
| 10 | N10 „system check: every status is announced as text (light)“ | Spalte „Status“ mit einem Statuswort (OK/Warnung/Fehler) |
| 11 | N11 „merge review: fields are announced with label and state (light)“ | Nachweisart, Begründung (erforderlich), Bestätigung (nicht aktiviert), kein vorzeitiges „ungültig“; nach dem Absenden die Meldung |
| 12 | N12 „recovery: the result or the blocker is announced (light)“ | Kontrollkästchen „aktiviert“ nach Leertaste; „Access restored“ |
| 13 | N10/N13, N11/N13, N12/N13 (dark) | dieselben Prüfungen im dunklen Farbschema; bei der Wiederherstellung der Blocker („Access not restored“, Sperre durch die Administration) |

Negativkontrolle (`nvda-control.spec.js`, isolierte Testseite, kein Produktcode verändert): Mit echter NVDA-Ausgabe muss
das Orakel eine Schaltfläche ohne Namen, eine sichtbare, aber nicht verbundene Fehlermeldung und eine nicht per
Tastatur erreichbare Registerkarte ablehnen und die korrekten Gegenstücke annehmen.

Nachweis je Lauf: Die Job-Zusammenfassung nennt Windows-, NVDA-, Chromium-, Playwright-/Guidepup-, PHP-, PostgreSQL-
und Node-Version, den Moodle-Commit und die Commits der vier Plugins (Familien-Resolver); das Artefakt
`nvda-screenreader-<Lauf>` enthält Bericht, Sprachprotokolle, Server-Log und `family-lock.json`. Der Job ist nur grün,
wenn alle 16 Tests bestanden haben und keiner übersprungen wurde.

Grenzen: NVDA in der von Guidepup bereitgestellten Version mit englischer Sprachausgabe, nur Chromium, nur Moodle
5.3 (PostgreSQL 17), PHP-Webserver mit einem Prozess. Firefox, deutsche Sprachausgabe und andere NVDA-Einstellungen
deckt die manuelle Prüfung ab.

Bei der Umsetzung gefunden und behoben: Die aktive Registerkarte der FlexAccess-Verwaltung war kein Link und nicht als
aktuell gekennzeichnet (Moodles Tab-Vorlage); sie ist jetzt erreichbar und trägt `aria-current`. Das Auswahlfeld
„Nachweisart“ wurde vor jeder Eingabe als „ungültig“ angesagt; jetzt nicht mehr.

## Was automatisiert belegt ist

| Prüfung | Test | Umfang |
|---|---|---|
| WCAG 2.1 A/AA (axe-core, nur „serious“ und „critical“ zählen) | `tool/tests/playwright/accessibility.spec.js` | alle 18 Verwaltungsseiten von tool und die Einschränkungsseite von enrol, einschließlich Formulare, Bestätigungen und Merge-Prüfung; Moodle 4.5 hell, Moodle 5.3 hell und dunkel |
| Öffentliche Seiten | `auth/tests/playwright/accessibility.spec.js` | Zugangsseite, Schnellregistrierung |
| Kontrast im dunklen Farbschema | `tool/tests/playwright/navigation.spec.js` | Verwaltungsseiten, Moodle 5.3 |
| Tastatur | `navigation.spec.js` | „Kurs > Mehr > FlexAccess“ und die Registerkarten mit der Tastatur erreichbar und auslösbar |

Die axe-Prüfung erkennt nachweislich Fehler: Ein absichtlich eingefügtes Bild ohne Alternativtext und ein Text mit
zu geringem Kontrast werden im dunklen Schema als `image-alt` und `color-contrast` gemeldet (Positivkontrolle,
27.10.2026).

## Umgebung

- Windows mit aktueller NVDA-Version, Firefox oder Chrome; Moodle 5.3 mit FlexAccess 2026102800.
- Ein Kurs mit FlexAccess-Einschreibung, ein Manager-Konto, ein Konto ohne Anmeldung (private Sitzung).
- NVDA-Sprachausgabe an, Bildschirmvorhang optional (NVDA+Strg+Esc).

## Ablauf

Für jeden Schritt notieren: erfüllt / nicht erfüllt / Bemerkung (was NVDA vorgelesen hat).

### A. Besucherinnen und Besucher (ohne Anmeldung)

1. Kursseite eines FlexAccess-Kurses öffnen → Weiterleitung zur Zugangsseite. Erwartet: Seitentitel und
   Überschrift Ebene 1 werden angesagt; mit `H` erreichbar.
2. Mit `Tab` durch die Seite: Jede Schaltfläche und jeder Link (temporärer Zugang, Anmeldung, ggf. Registrierung)
   wird mit dem sichtbaren Namen angesagt, die Reihenfolge entspricht der sichtbaren.
3. Schnellregistrierung öffnen, Formular mit `F` ansteuern. Erwartet: Jedes Feld hat eine Beschriftung,
   Pflichtfelder werden als „erforderlich“ angesagt.
4. Formular leer absenden. Erwartet: Fehlermeldungen werden angesagt oder sind mit dem Feld verbunden
   (beim Fokussieren des Feldes vorgelesen); kein Fehler nur über Farbe.
5. Temporären Zugang starten. Erwartet: Die Bestätigung („Temporary access granted“) und der Kurs werden angesagt.

### B. Kursverwaltung (Manager)

6. „Kurs > Mehr > FlexAccess“ nur mit der Tastatur öffnen. Erwartet: Menü und Eintrag werden angesagt.
7. Registerkarten der Kursverwaltung mit `Tab` und `Enter` wechseln. Erwartet: Die aktive Registerkarte wird als
   „aktuell“ bzw. ausgewählt erkennbar angesagt.
8. Zugangsliste anlegen (Formular), Ausgabe der Zugangsdaten bestätigen. Erwartet: Die Bestätigungsseite nennt die
   Anzahl der Konten; die Bestätigungsschaltfläche ist erreichbar; der Download startet.
9. Einschränkungen: Tabelle mit `T` ansteuern, mit `Strg+Alt+Pfeiltasten` lesen. Erwartet: Spaltenüberschriften
   werden zu den Zellen angesagt.

### C. Systemverwaltung (Manager)

10. Systemstatus: Statusmeldungen (OK/Warnung) werden mit Text angesagt, nicht nur über Farbe oder Symbol.
11. LTI-Identitäten → „Prüfen“ eines Paars: Vorschau als Tabelle lesbar; Pflichtangaben zur Freigabe
    (Nachweisart, Begründung, Bestätigung) haben Beschriftungen; nach dem Absenden wird die Meldung angesagt.
12. Wiederherstellung eines Kontos: Vorschau, Bestätigung und Ergebnis (wiederhergestellt bzw. der genannte
    Blocker) werden angesagt.
13. Dunkles Farbschema (in den Einstellungen des Benutzerkontos bzw. über das Betriebssystem): Schritte 10–12 kurz
    wiederholen; keine Inhalte verschwinden, Fokus bleibt sichtbar.

## Ergebnis

| Schritt | erfüllt | Bemerkung |
|---|---|---|
| 1–13 | | |

Abnahme durch: ____________  Datum: ____________  NVDA-Version / Browser: ____________

Ein „nicht erfüllt“ wird als eigenes Issue mit Seite, Schritt und angesagtem Text angelegt.
