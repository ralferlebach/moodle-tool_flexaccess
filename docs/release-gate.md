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
| `.github/flexaccess-family.lock.json` (nur tool_flexaccess, vom Release-Verantwortlichen committet) | Release-Lock: auth/enrol/mod per Commit-SHA, tool_flexaccess als `SELF`, tool_mergeusers per SHA, optional SHA-256 der Release-ZIPs |
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

## Freigabe (Release)

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

## Lokal reproduzieren

```bash
python3 .github/scripts/flexaccess_family.py lock --lock-file .github/flexaccess-family.lock.json \
  --self tool_flexaccess --self-dir . --self-sha "$(git rev-parse HEAD)" --out family-lock.json
python3 .github/scripts/flexaccess_family.py fetch --lock-file family-lock.json \
  --self tool_flexaccess --dest moodle-plugin-ci-plugins --with-mergeusers
```

`FLEXACCESS_FAMILY_URL` (z. B. `file:///pfad/{repo}`) leitet die Repositories auf lokale Spiegel um.
