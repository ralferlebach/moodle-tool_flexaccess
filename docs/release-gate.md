# Release-Gate der FlexAccess-Familie (ab 2026101800, tool_flexaccess #12)

Die vier Plugins werden gemeinsam freigegeben und hängen voneinander ab. Jede CI-Prüfung muss deshalb
genau **eine** bekannte Kombination testen – nicht vier bewegliche Branch-Stände.

## Bausteine

| Datei | Zweck |
|---|---|
| `.github/flexaccess-family.json` (in allen vier Repositories gleich) | Repositories und Moodle-Pfade der vier Plugins; tool_mergeusers fest auf einen Commit gepinnt |
| `.github/scripts/flexaccess_family.py` (gleich) | Auflösen, Prüfen, Laden der gepinnten Commits; keine Fallbacks |
| `.github/scripts/test_flexaccess_family.py` (gleich) | Selbsttests mit lokalen Test-Repositories (Negativfälle) |
| `.github/scripts/record_environment.php` | Schreibt die getestete Kombination (SHAs, Moodle/PHP/DB) in die Job-Zusammenfassung; bricht ab, wenn installierte Versionen vom Lock abweichen |
| `.github/flexaccess-family.lock.json` (nur tool_flexaccess, versioniert, ab 2026102400 im Release enthalten) | Release-Lock: auth/enrol/mod per Commit-SHA **oder per Inhalt** (`content`, siehe unten), tool_flexaccess als `SELF` (Inhalt ohne die Lock-Datei), tool_mergeusers per SHA, optional SHA-256 der Release-ZIPs |
| `.github/workflows/family-release.yml` (tool_flexaccess) | Release-Gate: PHPUnit aller vier Plugins (4.5, 5.0, 5.2, 5.3 × PostgreSQL/MariaDB) und Zwei-Site-LTI (4.5, 5.3; Provider/Consumer) gegen genau den Lock |

## Was jede CI-Pipeline tut

1. Job **„Plugin family (exact commits)“** (Dev-, Main-, Playwright-, Last- und LTI-Workflows):
   löst die Geschwister einmal vom Branch (`development` bzw. `main`) zu Commit-SHAs auf und verlangt,
   dass alle vier dieselbe Version wie dieser Commit tragen. Beim Hochladen der vier Repositories
   nacheinander wartet der Job bis zu 20 Minuten auf die übrigen; danach schlägt er fehl.
   Fehlender Branch, abweichende Version, fehlende oder verkürzte SHA, ungepinntes tool_mergeusers:
   harter Fehler, kein Rückfall auf einen anderen Branch.
2. Alle Test-Jobs laden **genau diese Commits** (`fetch --lock-json`), prüfen jede geladene `version.php`
   gegen den Lock und protokollieren Moodle-, PHP- und Datenbankversion samt SHA-Matrix.
3. Der Lock wird als Artefakt `flexaccess-family-lock` abgelegt.

## Inhalts-Lock (Auslieferung als Archiv, ab 2026102400)

Die vier Plugins werden als ZIP ausgeliefert und von Hand hochgeladen; die Commit-SHAs eines Release
entstehen also erst beim Hochladen. Der mitgelieferte Lock pinnt auth, enrol und mod deshalb über ihren
**Inhalt**: SHA-256 über die sortierten Paare (Pfad, Git-Blob-SHA) aller versionierten Dateien. Der Wert
hängt nicht von Commit-Metadaten oder Dateirechten ab (Web-Uploads verlieren das Ausführungsrecht), ändert
sich aber mit jeder hinzugefügten, fehlenden oder geänderten Datei. tool_flexaccess pinnt seinen eigenen
Inhalt ohne die Lock-Datei.

Das Gate (`lock`) akzeptiert für jedes Geschwister nur den **Kopf** seines Branches (`ref`, Standard
`development`), wenn er genau diesen Inhalt trägt, und hält dessen Commit-SHA im Evidenz-Artefakt fest.
Trägt der Kopf anderen Inhalt (Datei vergessen, alte Datei nicht gelöscht, später weiterentwickelt),
schlägt es fehl. Ein noch nicht hochgeladenes Geschwister wartet es bis zu 30 Minuten ab.

Erzeugt wird der Lock aus den vier fertig committeten Release-Bäumen:

```bash
python3 .github/scripts/flexaccess_family.py pin-local --parent /pfad/mit/den/vier/repositories > lock.json
python3 .github/scripts/flexaccess_family.py digest --dir ../moodle-auth_flexaccess        # Inhalt eines Repos
python3 .github/scripts/flexaccess_family.py digest --dir . --without-lock                  # tool ohne Lock
```

Hochladen: auth, enrol und mod **vollständig** (alle Dateien des ZIPs; im Repository vorhandene, im ZIP
fehlende Dateien löschen), danach tool_flexaccess. Der Push der Lock-Datei startet das Gate.

**Änderungen direkt auf GitHub** (etwa ein ausgetauschtes Plugin-Logo beim Hochladen) ändern den Inhalt; das Gate
schlägt dann fail-closed fehl („the commit under test carries content …, the lock pins …“). So geschehen mit
2026102601: alle vier `pix/pluginlogo.png` ersetzt, sonst identisch. Abhilfe ist nie ein Umgehen des Locks,
sondern: die Änderung in den Release-Baum übernehmen, neu pinnen, neu ausliefern. Vor jeder neuen Auslieferung
werden die GitHub-Köpfe deshalb mit dem zuletzt ausgelieferten Stand verglichen (nur Inhalt, Dateirechte zählen
nicht), zum Beispiel:

```bash
git fetch origin development
git diff --raw --no-abbrev <letzter ausgelieferter Stand> origin/development | awk '$3 != $4 {print $5, $6}'
```

Eine Datei, die dort erscheint, wurde nach der Auslieferung auf GitHub geändert und gehört in den nächsten
Release-Baum (oder wird auf GitHub zurückgenommen).

## Freigabe (Release)

Mit Inhalts-Lock (Archivauslieferung, Regelfall): siehe oben; danach weiter mit Schritt 4.

Mit Commit-Lock (Stände liegen schon auf GitHub):

1. auth, enrol und mod in der neuen Familienversion hochladen.
2. `FlexAccess family release gate` mit Modus **propose** starten (oder lokal
   `python3 .github/scripts/flexaccess_family.py pin --ref development`). Der Job gibt den Lock für die
   aktuellen Köpfe aus.
3. Den Lock als `.github/flexaccess-family.lock.json` zusammen mit tool_flexaccess committen; der Push
   startet das Gate (Modus **verify**).
4. Erst wenn **Release gate** grün ist: Tag setzen. `$plugin->supported` gilt erst damit als belegt; ist
   das Gate für eine Moodle-Version rot, wird `supported` für diese Version zurückgenommen.

Das Evidenz-Artefakt `flexaccess-family-evidence-*` enthält den aufgelösten Lock, die SHA-256 der
Release-ZIPs (`git archive`, beachtet `export-ignore`) und die Berichte der LTI-Läufe.

### Testgrenzen, die in jede Freigabe gehören

Eine Freigabe für produktive externe Identitätsanbieter oder LTI-Plattformen nennt ausdrücklich, was die
Pipeline **nicht** belegt (Stand 2026102700):

- OAuth 2: Ende-zu-Ende nur gegen einen lokalen Test-Anbieter (Node, http, Endpunkte direkt in der Datenbank);
  kein produktiver Identitätsanbieter. LDAP, CAS, Shibboleth, SAML2, DB und MNet: nur die Bindung über den
  Benutzernamen, kein echter Dienst.
- LTI 1.3: zwei Moodle-Instanzen mit eigener Test-CA, nur PostgreSQL, keine Fremdplattform.
- Browser: die Plugin-Suiten nur mit Chromium; im Gate läuft die LTI-Consumer-Seite zusätzlich mit Firefox,
  WebKit nie. Playwright nur auf 4.5 und 5.3, nicht auf 5.0/5.2.
- Gleichzeitige Abgleiche desselben Kontenpaars: über eine Sperre serialisiert. Getestet mit Dateisperren, die
  auch innerhalb eines PHP-Prozesses kollidieren (Zusammenführung während einer gehaltenen Sperre abgewiesen,
  Abgleichsaufgabe schlägt fehl und wird wiederholt); zwei echte parallele Webanfragen nicht. Ein LTI-Launch, der
  während der Zusammenführung unterwegs ist, ist im Zwei-Site-Test belegt; eine Datenbanktransaktion, die mitten
  in der Zusammenführung angehalten wird, nicht.
- Zustellung des Zugangsdatenpakets: HTTP kann den Empfang einer Datei nicht beweisen. Zugesagt ist die atomare
  Bereitstellung auf dem Server und eine auditierte Neuausgabe nach einer unterbrochenen Übertragung (Playwright
  „interrupted delivery after the server committed …“).

## Lokal reproduzieren

```bash
python3 .github/scripts/flexaccess_family.py lock --lock-file .github/flexaccess-family.lock.json \
  --self tool_flexaccess --self-dir . --self-sha "$(git rev-parse HEAD)" --out family-lock.json
python3 .github/scripts/flexaccess_family.py fetch --lock-file family-lock.json \
  --self tool_flexaccess --dest moodle-plugin-ci-plugins --with-mergeusers
```

`FLEXACCESS_FAMILY_URL` (z. B. `file:///pfad/{repo}`) leitet die Repositories auf lokale Spiegel um.
