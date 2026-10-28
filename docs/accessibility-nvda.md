# Barrierefreiheit: Screenreader-Abnahme mit NVDA (tool_flexaccess #11)

Die automatisierten Prüfungen (axe-core, WCAG 2.1 A/AA, Tastaturbedienung) laufen in der CI. Sie ersetzen keinen
Screenreader. Dieses Protokoll ist die manuelle Abnahme; ein Durchgang dauert etwa 30 Minuten.

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

- Windows mit aktueller NVDA-Version, Firefox oder Chrome; Moodle 5.3 mit FlexAccess 2026102700.
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
5. Temporären Zugang starten. Erwartet: Die Bestätigung bzw. der Kurs wird angesagt; die Restzeit-Anzeige ist
   als Text lesbar.

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
13. Dunkles Farbschema (in den Einstellungen des Benutzerkontos bzw. über das Betriebssystem): Schritte 10–12 kurz wiederholen; keine Inhalte
    verschwinden, Fokus bleibt sichtbar.

## Ergebnis

| Schritt | erfüllt | Bemerkung |
|---|---|---|
| 1–13 | | |

Abnahme durch: ____________  Datum: ____________  NVDA-Version / Browser: ____________

Ein „nicht erfüllt“ wird als eigenes Issue mit Seite, Schritt und angesagtem Text angelegt.
