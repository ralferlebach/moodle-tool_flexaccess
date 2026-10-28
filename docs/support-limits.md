# Testgrenzen und Supportmatrix der FlexAccess-Familie

Stand 2026102700. Was die automatisierten Tests belegen und was nicht; gehört in jede Freigabe für produktive externe
Identitätsanbieter oder LTI-Plattformen (Entscheidungen D2/D3 vom 26.10.2026).

## Wie die Familie getestet wird

- **Plugin-CI je Repository** (`moodle-plugin-ci-dev.yml`, `moodle-plugin-ci-main.yml`): Jeder Lauf löst die drei
  Geschwister einmal aus demselben Branch zu Commit-SHAs auf (alle in derselben Version, kein Ausweichen auf einen
  anderen Branch, tool_mergeusers auf einen Commit gepinnt) und installiert genau diese
  (`.github/scripts/flexaccess_family.py resolve` / `fetch`). PHPUnit auf Moodle 4.5, 5.0, 5.2, 5.3 mit PostgreSQL
  und MariaDB.
- **Playwright je Plugin** (`playwright.yml`): Moodle 4.5 und 5.3, Chromium.
- **Zwei-Site-LTI** (enrol_flexaccess, Workflow „LTI interop“, manuell und wöchentlich): Provider- und Consumer-Seite
  getrennt auf Moodle 4.5 und 5.3; die Consumer-Journey `@browser` zusätzlich mit Firefox.

Ein eigenes Release-Gate mit versioniertem Lock gab es bis 2026102700; es wurde auf Entscheidung des Maintainers
entfernt.

## Grenzen

- OAuth 2: Ende-zu-Ende nur gegen einen lokalen Test-Anbieter (Node, http, Endpunkte direkt in der Datenbank);
  kein produktiver Identitätsanbieter. LDAP, CAS, Shibboleth, SAML2, DB und MNet: nur die Bindung über den
  Benutzernamen, kein echter Dienst. Alle anderen Auth-Plugins: Zusammenführung fail-closed (`REVIEW_REQUIRED`).
- LTI 1.3: zwei Moodle-Instanzen mit eigener Test-CA, nur PostgreSQL, keine Fremdplattform.
- Browser: die Plugin-Suiten nur mit Chromium; die LTI-Consumer-Seite zusätzlich mit Firefox, WebKit nie.
  Playwright nur auf 4.5 und 5.3, nicht auf 5.0/5.2.
- Gleichzeitige Abgleiche desselben Kontenpaars: über eine Sperre serialisiert. Getestet mit Dateisperren, die
  auch innerhalb eines PHP-Prozesses kollidieren (Zusammenführung während einer gehaltenen Sperre abgewiesen,
  Abgleichsaufgabe schlägt fehl und wird wiederholt); zwei echte parallele Webanfragen nicht. Ein LTI-Launch, der
  während der Zusammenführung unterwegs ist, ist im Zwei-Site-Test belegt; eine Datenbanktransaktion, die mitten
  in der Zusammenführung angehalten wird, nicht.
- Zustellung des Zugangsdatenpakets: HTTP kann den Empfang einer Datei nicht beweisen. Zugesagt ist die atomare
  Bereitstellung auf dem Server und eine auditierte Neuausgabe nach einer unterbrochenen Übertragung (Playwright
  „interrupted delivery after the server committed …“).

## Freigabe nach `main`

1. Alle vier Plugins in derselben Version auf `development` hochladen; die Plugin-CI aller vier abwarten (grün).
2. Workflow „LTI interop“ in enrol_flexaccess manuell starten (Provider und Consumer, 4.5 und 5.3, Firefox) und
   abwarten (grün).
3. Dieselben Stände nach `main` übernehmen; dort laufen die Plugin-CIs erneut (`moodle-plugin-ci-main.yml`, mit dem
   Sammeljob „Release gate“ der Pipeline selbst). Erst wenn sie grün sind: Tag setzen.

Barrierefreiheit: automatisiert in der CI (axe, Tastatur, dunkles Farbschema); die Screenreader-Abnahme ist manuell,
siehe [accessibility-nvda.md](accessibility-nvda.md).
