# Testgrenzen und Supportmatrix der FlexAccess-Familie

Stand 2026102800. Was die automatisierten Tests belegen und was nicht; gehört in jede Freigabe für produktive externe
Identitätsanbieter oder LTI-Plattformen (Entscheidungen D2/D3 vom 26.10.2026).

## Wie die Familie getestet wird

- **Plugin-CI je Repository** (`moodle-plugin-ci-dev.yml`, `moodle-plugin-ci-main.yml`): Jeder Lauf löst die drei
  Geschwister einmal aus demselben Branch zu Commit-SHAs auf (alle in derselben Version, kein Ausweichen auf einen
  anderen Branch, tool_mergeusers auf einen Commit gepinnt) und installiert genau diese
  (`.github/scripts/flexaccess_family.py resolve` / `fetch`). PHPUnit auf Moodle 4.5, 5.0, 5.2, 5.3 mit PostgreSQL
  und MariaDB.
- **Playwright je Plugin** (`playwright.yml`): Moodle 4.5 und 5.3, Chromium.
- **Zwei-Site-LTI** (enrol_flexaccess, Workflow „LTI interop“, bei jedem Push auf `development`, manuell und
  wöchentlich): Provider- und Consumer-Seite getrennt auf Moodle 4.5 und 5.3; die Consumer-Journey `@browser`
  zusätzlich mit Firefox.
- **Screenreader** (tool_flexaccess, Workflow „NVDA screen reader“, bei jedem Push auf `development` und `main`):
  echtes NVDA auf einem Windows-Runner, Chromium, Moodle 5.3 mit PostgreSQL 17; 13 Schritte aus
  `docs/accessibility-nvda.md` und eine Negativkontrolle. Schlägt fehl, wenn NVDA, Browser, Site oder Sprachkanal
  fehlen oder ein Schritt übersprungen wird.

Ein eigenes Release-Gate mit versioniertem Lock gab es bis 2026102700; es wurde auf Entscheidung des Maintainers
entfernt.

## Grenzen

- OAuth 2: Ende-zu-Ende nur gegen einen lokalen Test-Anbieter (Node, http, Endpunkte direkt in der Datenbank);
  kein produktiver Identitätsanbieter. LDAP, CAS, Shibboleth, SAML2, DB und MNet: nur die Bindung über den
  Benutzernamen, kein echter Dienst. Alle anderen Auth-Plugins: Zusammenführung fail-closed (`REVIEW_REQUIRED`).
- LTI 1.3: zwei Moodle-Instanzen mit eigener Test-CA, nur PostgreSQL, keine Fremdplattform.
- Browser: die Plugin-Suiten nur mit Chromium; die LTI-Consumer-Seite zusätzlich mit Firefox, WebKit nie.
  Playwright nur auf 4.5 und 5.3, nicht auf 5.0/5.2.
- Screenreader: NVDA in der von Guidepup bereitgestellten Version, englische Sprachausgabe, nur Chromium und Moodle
  5.3; JAWS, VoiceOver, TalkBack, Firefox mit NVDA und andere Sprachen nur manuell (Protokoll in
  `docs/accessibility-nvda.md`).
- Gleichzeitige Abgleiche desselben Kontenpaars: über eine Sperre serialisiert. Getestet mit Dateisperren, die
  auch innerhalb eines PHP-Prozesses kollidieren (Zusammenführung während einer gehaltenen Sperre abgewiesen,
  Abgleichsaufgabe schlägt fehl und wird wiederholt); zwei echte parallele Webanfragen nicht. Ein LTI-Launch, der
  während der Zusammenführung unterwegs ist, ist im Zwei-Site-Test belegt; eine Datenbanktransaktion, die mitten
  in der Zusammenführung angehalten wird, nicht.
- Zustellung des Zugangsdatenpakets: HTTP kann den Empfang einer Datei nicht beweisen. Zugesagt ist die atomare
  Bereitstellung auf dem Server und eine auditierte Neuausgabe nach einer unterbrochenen Übertragung (Playwright
  „interrupted delivery after the server committed …“).

## Freigabe nach `main`

1. Alle vier Plugins in derselben Version auf `development` hochladen (tool_flexaccess zuletzt). Danach laufen
   automatisch: die Plugin-CI aller vier, „LTI 1.3 interoperability“ (enrol) und „NVDA screen reader“ (tool). Alle
   abwarten (grün).
2. Dieselben Stände nach `main` übernehmen; dort laufen die Plugin-CIs (`moodle-plugin-ci-main.yml`, mit dem
   Sammeljob „Release gate“ der Pipeline selbst) und „NVDA screen reader“ erneut. Erst wenn sie grün sind: Tag setzen.

Barrierefreiheit: automatisiert in der CI (axe, Tastatur, dunkles Farbschema, NVDA-Journeys); ergänzende manuelle
Prüfung siehe [accessibility-nvda.md](accessibility-nvda.md). Vor dem Tag müssen auch „NVDA screen reader“ (tool) und
„LTI 1.3 interoperability“ (enrol) auf den Ständen grün sein.
