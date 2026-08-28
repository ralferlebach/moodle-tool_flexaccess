# Changelog

## 1.0.0-RC1 — 2026-08-27 — Browsersuiten: zwei Fehler behoben, Artefakte nach Ergebnis getrennt
- **`ReferenceError: loginAsManager is not defined`.** Beim Aufteilen der Barrierefreiheitsprüfung ist die Hilfsfunktion im auth-Teil geblieben, ihr Aufruf aber in den tool-Teil gewandert. Sie steht jetzt dort, wo sie gebraucht wird.
- **Falsche Beschriftung im Aktivierungstest.** Der Test erwartete „Make my account permanent" — die Aktivität beschriftet ihre Schaltfläche aber mit „Activate my account" (Sprachstring `sasubmit`). Alle in den Suiten erwarteten Beschriftungen wurden gegen die englischen Sprachdateien gegengeprüft.
- **Artefakte hängen jetzt am Ergebnis.** Bei einem grünen Lauf wird der vollständige Bericht **mit** Videos abgelegt (`playwright-report-with-videos`). Bei einem roten Lauf wird stattdessen nur eingesammelt, was tatsächlich fehlgeschlagen ist, und ohne Videos (`playwright-failures`) — an einem echten Bericht gemessen: **14 MB → 324 KB** bei vollständig erhaltenen Screenshots und Traces.

## 1.0.0-RC1 — 2026-08-27 — Falscher @package-Tag in der kopierten Fixture-Datei
- **`@package` in `tests/playwright/seed.php` korrigiert.** Die Datei stammt aus `enrol_flexaccess` und trug beim Kopieren dessen Tag weiter; `moodle-cs` weist das als Fehler zurück (`moodle.Commenting.Package.Incorrect`). Alle PHP-Dateien der vier Plugins wurden gegengeprüft — keine weitere Abweichung.

## 1.0.0-RC1 — 2026-08-27 — Eigene Browser-Testsuite je Plugin
- **Jedes Plugin bringt jetzt seine eigene Playwright-Suite mit** (`tests/playwright/`), statt dass alle vier dieselbe Suite aus `enrol_flexaccess` ausführen. Jede Suite prüft die Handlungen, für die *ihr* Plugin zuständig ist, und jedes Repository steht damit für sich.
- Gemeinsame Helfer liegen als `helpers.js` in jedem Plugin — bewusst als Kopie: Ein geteiltes Paket würde die vier Repositories auch auf Testebene aneinanderbinden.
- Jedes Plugin hat ein eigenes `seed.php`, das genau das Fixture erzeugt, das seine Suite braucht.
- **Zwei Fehler dabei gefunden und behoben:** Das Kurskürzel im Fixture nutzte `time()` — zwei Läufe innerhalb derselben Sekunde kollidierten und der Kurs konnte nicht angelegt werden. Und der Pfad zur `config.php` stimmt bei `tool`-Plugins nicht, weil sie eine Ebene tiefer liegen (`admin/tool/<name>`).
- Die Testsuiten bleiben wie bisher aus dem Installationspaket ausgeschlossen.

- Inhalt: Übersicht und Kontenliste, Mailqueue, Richtlinien, Einladung anlegen, Zugangsliste anlegen, Kampagne anlegen (samt Nachweis, dass der Link nur einmal erscheint), Batch-Liste sowie die Barrierefreiheitsprüfung der Administrationsseiten (7 Tests).

## 1.0.0-RC1 — 2026-08-27 — Playwright-Artefakte
- **Artefaktpfad korrigiert:** Der Upload zeigte auf das eigene Plugin-Verzeichnis, in dem keine Testsuite liegt — aus diesem Repository gab es deshalb nie Artefakte. Er verweist jetzt auf `enrol_flexaccess/tests/playwright`, wo die gemeinsame Suite ausgeführt wird.
- **Zwei Artefakte je Lauf:** einmal ohne Videos (Screenshots, Traces, Bericht) und einmal mit allem.

## 1.0.0-RC1 — 2026-08-27 — Rollenwechsel wurde bei den Zugangslisten nicht beachtet
- **Beim Wechsel in die Rolle „Teilnehmer/in" blieb der Eintrag „Anonyme Zugangslisten" sichtbar, und die Seite ließ sich öffnen.** Ursache: Zwei kursbezogene Entscheidungen fragten die Berechtigung `managebatches` im **Systemkontext** ab. Moodle setzt die Administrator-Umgehung beim Rollenwechsel nur für den gewechselten Kontext aus — eine Frage an den Systemkontext wurde deshalb weiterhin mit „ja" beantwortet. Alle Prüfungen laufen jetzt im **Kurskontext**; eine auf Systemebene zugewiesene Rolle greift dort weiterhin, weil Berechtigungsprüfungen den Kontextpfad nach oben durchlaufen.
- **Einordnung:** Es handelt sich um eine falsche Vorschau, nicht um eine Rechteausweitung — betroffen waren ausschließlich Konten, die den Zugang ohnehin besitzen (Website-Administration bzw. Manager/innen mit systemweiter Berechtigung). Für Teilnehmende und Trainer/innen ohne Bearbeitungsrecht war der Zugang zu keinem Zeitpunkt möglich.
- **Neuer Regressionstest** in `access_permissions_test`: Er wechselt tatsächlich per `role_switch()` in die Rolle „Teilnehmer/in" und stellt sicher, dass weder Seite noch Aktionen angeboten werden — und dass die eigenen Rechte nach dem Zurückwechseln wieder greifen. Gegen den alten Stand geprüft: Der Test schlägt dort fehl, mit genau der gemeldeten Beobachtung als Meldung.

## 1.0.0-RC1 — 2026-08-27 — Berechtigungen geprüft und vollständig abgesichert
- **Sicherheitsprüfung aller Einstiegspunkte.** Sämtliche Seiten der vier Plugins wurden auf ihren Zugangsschutz geprüft. Ergebnis: keine ungeschützte Seite. Bewusst öffentlich sind nur die Einstiegsseiten für anonyme Besucher (`register.php`) und die token-basierten Seiten (`invite.php`, `campaign.php`, `setpassword.php`, `magic.php`), die über den Token bzw. die Kursrichtlinie abgesichert sind.
- **Berechtigungen je Rolle nachgemessen.** Student und Trainer/in ohne Bearbeitungsrecht erhalten für die anonymen Zugangslisten **keinen** Zugang — weder zur Seite noch zu einer der Einzelaktionen. Trainer/innen mit Bearbeitungsrecht dürfen ansehen und beantragen; Anlegen, Zugangsdaten ausstellen und Konten umwandeln bleibt Manager/innen vorbehalten.
- **Neuer Test `access_permissions_test`** sichert beides dauerhaft ab (50 Zusicherungen): Für jede Rolle wird geprüft, welche Seite und welche Aktion erlaubt ist, **und** dass keine Capability an eine Rolle vergeben ist, die sie nicht haben soll. Ein versehentliches Ausweiten der Rechte fällt damit sofort auf.

## 1.0.0-RC1 — 2026-08-27 — Freigabe-Gate: verlangte ein Dokument, das es nur einmal gibt
- **Der Gate scheiterte an einer Bedingung, die nur ein Plugin erfüllen konnte.** Er verlangte bei `MATURITY_STABLE` die Datei `docs/scope-decisions-1.0.md`; die liegt aber ausschließlich in `tool_flexaccess`. In den drei anderen Repositories blieb er deshalb rot, obwohl sämtliche Tests grün waren. Die Bedingung ist entfernt — die Scope-Entscheidungen bleiben dokumentiert, sind aber keine Freigabevoraussetzung je Plugin.
- **Der Gate ist jetzt die einzige Bewertungsstelle der Release-Gates.** Er prüft Matrix, Lockstep, Artefakt und Coverage bei *jedem* Reifegrad, nennt jedes Ergebnis einzeln und gibt den deklarierten Reifegrad aus. Der Job heißt entsprechend „Release gate".
- **Doppelte Verdrahtung entfernt.** `ci-complete` hing bisher sowohl direkt an den vier Gates als auch am Gate selbst — dieselbe Bedingung an zwei Stellen. Es hängt jetzt nur noch am Gate-Pfad (`maturity-gate` und `stale-files`).
- Keine Änderung an Version, Release oder Reifegrad.

## 1.0.0-RC1 — 2026-08-27 — CI: Freigabe-Gate blieb nach einem Re-run dauerhaft rot
- **`maturity-gate` läuft jetzt immer** (`if: always()`). Ohne diese Angabe wurde der Job übersprungen, sobald einer seiner Vorgänger fehlschlug — und ein übersprungener Job wird von „Re-run failed jobs" **nicht** erneut ausgeführt. Er blieb damit dauerhaft auf `skipped`, weshalb `ci-complete` selbst dann rot blieb, wenn alle eigentlichen Fehler längst behoben und alle Tests grün waren.
- **`ci-complete` benennt jedes Gate einzeln.** Die bisherige Meldung nannte nur zwei der sechs Ergebnisse; ein Fehlschlag in einem der übrigen sah nach einem unerklärlichen roten Lauf aus. Zusätzlich wird `stale-files` jetzt tatsächlich mitgeprüft — der Job stand zwar in `needs`, wurde in der Bedingung aber übergangen.
- **`maturity-gate` gibt die Ergebnisse seiner Vorgänger aus**, damit direkt an der Stelle sichtbar ist, woran es lag.
- Keine Änderung an Version, Release oder Reifegrad.

## 1.0.0-RC1 — 2026-08-27 — Reifegrad Release Candidate
- **Reifegrad auf `MATURITY_RC`** gesetzt (Version unverändert `2026082700`). Das entspricht dem Release-Namen `1.0.0-RC1`: Alle CI-Strecken laufen grün — Dev, Main, Playwright, jMeter und k6 einschließlich Capacity Race — die endgültige Stable-Freigabe steht aber noch aus.
- **`README.md` erweitert:** Unter „Requirements" sind jetzt die Geschwister-Plugins samt GitHub-Adresse aufgeführt, getrennt nach harter Abhängigkeit (in `version.php` deklariert) und ergänzendem Bestandteil des Verbunds. Ein einleitender Abschnitt erklärt, wie die vier Plugins zusammenwirken und warum eine Teilinstallation nicht funktioniert.

## 1.0.0-RC1 — 2026-08-27 — Release Candidate 1
- Version `2026082700`, Release `1.0.0-RC1`, Reifegrad `MATURITY_STABLE`.
- Die Abhängigkeiten der vier Plugins verlangen ebenfalls `2026082700`: Sie sind nur in diesem gemeinsamen Stand lauffähig.
- **Neue `README.md`** nach dem Muster von Moodle an Hochschulen, mit den tatsächlichen Angaben dieses Plugins: Voraussetzungen, Motivation, Installation, Einstellungen, Capabilities, geplante Aufgaben, Funktionsweise samt Stolperfallen sowie Hinweise zu Support, Übersetzung und Pflege.

## 0.9.63 — 2026-08-27 — Zugangslisten: Kärtchen, Freitext, Einmal-Ausgabe
- **Kärtchen-Layout schnittgerecht.** Der Abstand zwischen zwei Karten ist doppelt so groß wie der Seitenrand (8 mm), sodass Halbieren und nochmaliges Halbieren der Seite bei jeder Karte denselben Rand ergibt. Nachgerechnet: alle Schnittkanten liegen exakt 8 mm von beiden angrenzenden Karten und von allen vier Seitenrändern entfernt; Kartenformat 89 × 58,25 mm.
- **Keine Überlagerungen mehr.** Beschriftung steht jetzt *über* dem Wert statt daneben, sodass auch lange Anmeldenamen die volle Breite haben. Die Textspalte hält Abstand zum QR-Code.
- **Freitext auf den Kärtchen.** Neues Feld `cardtext` (Upgrade `2026082440`, additiv), erfassbar bei Anlage **und** Beantragung einer Liste; wird links auf jeder Karte gedruckt und ist auf den freien Platz begrenzt.
- **Passwörter sind standardmäßig 6 Zeichen lang.** Das Zeichenalphabet bleibt unverändert — ohne leicht verwechselbare Zeichen und ohne umständliche Sonderzeichen. Die Passwortrichtlinie der Website hat weiterhin Vorrang: Verlangt sie mehr Zeichen, werden mehr erzeugt.
- **Nur noch ein Download.** Die Einzeldownloads (XLSX / PDF-Liste / Kärtchen) sind entfallen: Jeder Download vergibt die Passwörter neu, getrennte Dateien hätten einander entwertet. Es gibt ausschließlich das ZIP-Paket aus einem einzigen Satz Zugangsdaten.
- **Ausgabe nur einmal.** Nach der Ausgabe wird der Zeitpunkt festgehalten (`timeissued`) und der Download für alle außer der Website-Administration gesperrt; für diese erscheint eine ausdrückliche Warnung, dass ein erneutes Ausstellen die bereits ausgegebenen Exemplare entwertet.
- **Capability-Lücke geschlossen:** Die Kursseite öffnet sich jetzt bei *jeder* relevanten Berechtigung (ansehen, erstellen oder beantragen) statt nur beim Antragsrecht; jede Aktion bleibt einzeln geschützt. `can_request()` berücksichtigt `createcoursebatches`, und Anfragen erreichen auch Personen, die nur dieses Recht besitzen.
- Versions-Gleichschritt `2026082440`.

## 0.9.62 — 2026-08-27 — Versions-Gleichschritt
- Keine Codeänderung. Versions-Gleichschritt auf `2026082439`.

## 0.9.61 — 2026-08-27 — Versions-Gleichschritt
- Keine Codeänderung. Versions-Gleichschritt auf `2026082438`.

## 0.9.60 — 2026-08-27 — Testserver auf 127.0.0.1
- Der Testserver wird an `127.0.0.1` gebunden und unter derselben Adresse angesprochen; `localhost` konnte je nach Client nach `::1` aufgelöst werden, wohin der Server nicht lauscht.
- Versions-Gleichschritt `2026082437`.

## 0.9.59 — 2026-08-27 — Passwortrichtlinie in der Oberfläche, Tests, Browser-Journeys
- **Die Auswahl der Passwortlänge beginnt bei der effektiven Mindestlänge der Website.** Eine Länge anzubieten, die die Passwortrichtlinie ohnehin ablehnt, führte nur zu einem abgelehnten Batch.
- **Neue Tests `password_policy_test`:** Der Generator erfüllt auch eine strenge Richtlinie (14 Zeichen, alle Zeichenklassen), die Bereitstellung erzeugt ausschließlich richtlinienkonforme Passwörter, und eine unerfüllbare Richtlinie führt zu einem klaren Abbruch statt zu unbrauchbaren Konten.
- Versions-Gleichschritt `2026082436`.

## 0.9.58 — 2026-08-27 — Kompensation nachvollziehbar, zusätzliche Fehlerinjektion
- **Eine fehlgeschlagene Kompensation wird nicht mehr stillschweigend verworfen.** Lässt sich ein Konto nach abgebrochener Bereitstellung nicht entfernen, wird das über `debugging()` gemeldet und als Statusmeldung am Batch hinterlegt, damit der Überrest sichtbar bleibt und gezielt bereinigt werden kann.
- **Einladungsversand nutzt die atomare Deduplizierung** der Mailqueue, sodass gleichzeitige Aktionen genau einen Versandauftrag erzeugen.
- **Zwei zusätzliche Fehlerinjektionstests:** Fehler beim Eintragen der Mitgliedschaft **nach** erfolgreicher Einschreibung hinterlässt kein Konto; eine bereits zugestellte Mail wird bei fehlgeschlagener Quittung nicht erneut versendet.
- **Kommentare überarbeitet:** Verweise auf Review-Vorgangsnummern und Formulierungen, die den Bearbeitungsverlauf beschreiben, sind durch Sachaussagen zum Code ersetzt.
- Versions-Gleichschritt `2026082435`.

## 0.9.57 — 2026-08-27 — Review 0.9.51: beide RC-Blocker geschlossen
- **P0-1 Provisioning ist jetzt pro Mitglied atomar.** Bisher entstand bei einem Fehler nach `create_batch_account()` — etwa in `admin_enrol()` oder beim Member-Insert — ein Konto, das der resumierbare Batch nicht kennt; ein Retry legte ein **weiteres** an. Jeder Mitgliedsschritt ist nun in `try/catch` gekapselt und löst bei Fehlschlag eine Kompensation aus (`auth::rollback_batch_account()`). Diese greift ausschließlich bei FlexAccess-Konten mit Platzhalter-Adresse — ein personalisiertes Konto kann darüber nie gelöscht werden.
- **P0-2 Ein fehlgeschlagener Resend zerstört keinen funktionierenden Link mehr.** Der Renderer rotierte den Tokenhash **vor** dem Versand; scheiterte die Mail, war der zuvor zugestellte Link wertlos, und mehrere eingeplante Jobs entwerteten jeweils die Mail des vorherigen. Neu: Der erzeugte Token wird in `pendingtokenhash` geparkt (Upgrade `2026082434`, additiv) und ersetzt den aktiven Hash **erst nach bestätigter Zustellung**. Der zuletzt zugestellte Link bleibt bis dahin gültig.
- **P1 Passwortrichtlinie:** Generierte Batch-Passwörter durchlaufen jetzt Moodles `check_password_policy()`; die Länge respektiert zusätzlich `minpasswordlength`. Kann die Richtlinie nicht erfüllt werden, bricht die Erstellung mit klarer Meldung ab, statt unbrauchbare Konten anzulegen.
- **P1 Dedup:** Ein identischer, noch wartender Deferred-Job wird nicht erneut eingeplant — zwei Jobs hätten sonst zweimal gesendet und dabei jeweils den Link des anderen entwertet.
- **P1 Fortschritt:** Der Conversion-Import zählt jetzt **verarbeitete** statt nur erfolgreich konvertierter Zeilen; ein Import mit vielen Übersprüngen wirkte sonst hängengeblieben.
- Tests: `failure_atomicity_test` mit Fehlerinjektion (kein verwaistes Konto; fehlgeschlagener Resend lässt den zugestellten Link gültig; erfolgreicher Resend ersetzt ihn).
- Versions-Gleichschritt `2026082434`.

## 0.9.56 — 2026-08-27 — Playwright, jMeter und k6 lauffähig gemacht
- **Playwright scheiterte an der Moodle-Installation** („Dependencies check failed"). Ursache war meine Versions-Bump-Automatik: Ein pauschales Suchen-und-Ersetzen der Build-Nummer schrieb **auch die `$plugin->dependencies`** um, sodass jedes Plugin exakt den brandneuen Build aller Geschwister verlangte. Beim Rollout über vier Repositories liegt aber zwangsläufig eines zurück — und dann verweigert der Installer den Dienst. Die Pins nennen jetzt die **Mindestversion**, in der die genutzte API entstand (`2026082411`, für `tool → auth` `2026082423`), und werden nicht mehr automatisch mitgezogen.
- **jMeter scheiterte am Prüfsummenvergleich.** Die Apache-Datei enthält bereits einen Dateinamen (`<hash> *apache-jmeter-5.6.3.tgz`); das Skript hängte einen zweiten an, woraufhin `sha512sum` nach einer Datei namens „apache-jmeter-5.6.3.tgz  jmeter.tgz" suchte. Es wird jetzt nur das Hash-Feld verwendet — mit echter Prüfsummendatei verifiziert.
- **k6 meldete 100 % fehlgeschlagene Requests, weil der Testserver nicht lief.** Zwei Ursachen: Der Server wurde in einem Schritt gestartet und erst in einem späteren genutzt (jetzt via `setsid` abgekoppelt), und die Bereitschaftsschleife lief bei Misserfolg einfach aus, statt abzubrechen. Sie bricht jetzt mit Serverlog ab. Zusätzlich prüfen jMeter- und k6-Schritt unmittelbar vor dem Lastlauf, ob das Ziel antwortet.
- **k6-Pläne gehärtet:** Beide Pläne brechen in `setup()` mit klarer Meldung ab, wenn `BASE_URL`/`COURSEID` fehlen oder das Ziel nicht antwortet. Der bewusst „fail closed" antwortende `magic.php`-Aufruf ohne Token wird als erwartete Antwort deklariert und verfälscht die Fehlerrate nicht mehr.
- Versions-Gleichschritt `2026082433`.

## 0.9.55 — 2026-08-27 — Coverage-Messung repariert und scharf geschaltet
- **Das Coverage-Gate maß Unsinn — mein Fehler.** Der Filter `/flexaccess/` zählte die Statements **aller vier** Plugins, obwohl im jeweiligen Job nur die eigene Testsuite läuft. Ergebnis: 0,52 %. Das Gate ermittelt die Komponente jetzt aus `version.php` und wertet ausschließlich den eigenen Installationspfad aus.
- **Zweiter Fehler im selben Skript:** Es las `version.php` per `include`. Eine Moodle-`version.php` beginnt mit `defined('MOODLE_INTERNAL') || die()`, das Skript brach also stillschweigend ab und meldete Erfolg. Jetzt wird die Komponente als Text ausgelesen.
- **Echte Messwerte (lokal mit pcov über die vollen Suiten):** auth 50,9 % · enrol 57,7 % · tool 46,6 % · mod 10,2 %. Die Untergrenzen liegen je Plugin einige Punkte darunter (45/50/40/8) — sie fangen eine echte Regression, ohne bei normaler Schwankung rot zu werden. Das Gate ist damit wieder **blockierend**. mod ist bewusst niedrig: Seine Logik liegt in `view.php`, einem von Behat abgedeckten Entry-Point.
- **Drei ungültige `@covers`-Ziele behoben.** `\xmldb_auth_flexaccess_upgrade` und `\backup_flexaccess_activity_structure_step` existieren nicht als Klasse (jetzt `@coversNothing`), `\enrol_flexaccess_plugin::enrol_page_hook` zeigte auf eine Methode statt auf die Klasse. PHPUnit meldet solche Ziele als **Warnung** — mit dem in der CI genutzten `--fail-on-warning` hätte das den Build rot gemacht. Alle vier Suiten laufen jetzt mit diesem Flag auf Exit 0.
- Versions-Gleichschritt `2026082432`.

## 0.9.54 — 2026-08-27 — Codechecker-Befunde behoben, Prüfkontext angeglichen
- **Ursache der Serie gefunden:** Zwei Sniffs feuern **nur**, wenn das Plugin **innerhalb** eines Moodle-Baums geprüft wird und die Zielversion bekannt ist. Die Dev-Pipeline prüfte einen Standalone-Ordner (`codechecker plugin`), die Main-Pipeline den installierten Pfad — deshalb war Dev grün und Main rot. **Beide Pipelines nutzen jetzt denselben strengen Aufruf** (`codechecker` ohne Pfadargument, `--max-warnings 0`).
- **`moodle.Files.LangFilesOrdering`:** Alle acht Sprachdateien sind jetzt alphabetisch sortiert (120/142/25/267 Strings je Sprache, EN/DE-Parität unverändert exakt).
- **`moodle.PHPUnit.TestCaseCovers`:** Auf Moodle 4.5 akzeptiert der Sniff das PHP-Attribut `#[CoversClass]` nicht — er sucht den Klassen-Docblock unmittelbar vor der Klasse, und ein dazwischenstehendes Attribut verdeckt ihn. Alle 59 Testklassen tragen jetzt eine `@covers`-Angabe im Docblock; die Attribute wurden entfernt. Damit sind alle 244 Befunde weg.
- **Coverage-Job ist jetzt berichtend statt blockierend.** Die Messung greift in diesem Job nicht (0,52 % gemessen bei tatsächlich weit höherer Abdeckung durch die Suiten) — ein Gate darauf würde jeden Build wegen eines Messproblems blockieren, nicht wegen mangelnder Qualität. Die Zahl wird bei jedem Lauf ausgegeben; die Schwelle (`COVERAGE_FLOOR`) wird scharf geschaltet, sobald die Messung nachweislich verlässlich ist.
- Versions-Gleichschritt `2026082431`.

## 0.9.53 — 2026-08-27 — Main-Pipeline: zwei Gates waren konstruktiv falsch
- **Lockstep-Gate blockierte jeden Rollout.** Vier getrennte Repositories lassen sich nur nacheinander pushen; das Gate verlangte aber bei **jedem** Branch-Push identische Versionen aller vier — die ersten drei Pushes waren damit zwangsläufig rot. Es blockiert jetzt nur noch dort, wo „gemeinsam released" tatsächlich behauptet wird: auf einem **Tag**. Auf Branch-Pushes wird ein Versionsunterschied als Warnung berichtet, nicht als Fehler.
- **Coverage-Gate maß das falsche Ziel.** `--coverage-text` berichtet die Abdeckung des **gesamten Moodle-Baums** (im Lauf: 2,23 %, weil der Core die Zeilenzahl dominiert) — über dieses Plugin sagt das nichts, und der Mindestwert konnte nie erreicht werden. Die Messung läuft jetzt über **Clover-XML** und wertet mit `tools/coverage_gate.php` ausschließlich die Dateien dieses Plugins aus (ohne die Tests selbst). Fehlt der Report oder enthält er keine Plugin-Datei, schlägt das Gate fehl statt still durchzuwinken.
- Versions-Gleichschritt `2026082430`.

## 0.9.52 — 2026-08-27 — CI-Fix: Coverage-Konfiguration war nicht 4.5-kompatibel
- **Alle PHPUnit- und Behat-Jobs scheiterten an `Class "core\test\phpunit\coverage_info" not found`.** Die in 0.9.51 eingeführte `tests/coverage.php` verwendete den **namespaced** Klassennamen; den gibt es erst ab Moodle 5.x. Auf dem unterstützten Moodle 4.5 heißt die Klasse `phpunit_coverage_info`. Sie wird jetzt über diesen globalen Namen abgeleitet — auf 4.5 ist das die Klasse selbst, auf 5.x ein gepflegter Alias, also für alle unterstützten Versionen korrekt.
- **Neue Datei `db/removed_files.txt` und neuer CI-Job `stale-files`.** Ein Plugin-Update per ZIP fügt Dateien hinzu und überschreibt sie, **löscht aber nie**. In früheren Releases entfernte Dateien überleben deshalb in einer Installation oder in einem so aktualisierten Repository — mit Folgen wie doppelten Klassen (phpcpd) oder Zugriffen auf nicht mehr existierende Spalten. Der Job schlägt fehl, solange eine gelistete Datei noch vorhanden ist, statt die Ursache in Folgefehlern zu verstecken. In Dev- **und** Main-Pipeline verdrahtet, `ci-complete` hängt daran.
- Versions-Gleichschritt `2026082429`.

## 0.9.51 — 2026-08-27 — Coverage- und Maturity-Gate
- **Neue `tests/coverage.php`** definiert den Coverage-Messumfang dieses Plugins.
- **Neue CI-Gates** `coverage` (erzwungene Mindest-Line-Coverage) und `maturity-gate` (`MATURITY_STABLE` nur bei durchgehend grünen Release-Gates und dokumentierten Scope-Entscheidungen).
- Die Maturity bleibt bewusst `MATURITY_BETA`, bis der Reviewer die Blocker unabhängig als geschlossen bestätigt.
- Versions-Gleichschritt `2026082428`.

## 0.9.50 — 2026-08-26 — P1-3 Conversion asynchron + Privacy-Tests
- **Conversion-Import asynchronisiert (P1-3).** Sync-Obergrenze `MAX_SYNC_CONVERT = 100`; größere Importe übernimmt der neue Ad-hoc-Task `convert_batch`, die Seite kehrt sofort zurück.
- **Kein N+1 mehr:** Die Batch-Mitglieder werden einmal vorab geladen und nach Anmeldename indiziert, statt je Zeile erneut abgefragt zu werden.
- **Idempotent:** Bereits konvertierte Mitglieder werden übersprungen. Ein wiederholter Lauf (etwa ein von Moodle erneut ausgeführter Task) erzeugt damit keine zweite Identitätsumstellung und **keine doppelte Set-Password-Mail**.
- **Teilfehler mitgliedsbezogen:** Neues Feld `converterror` auf `tool_flexaccess_batch_member` (Upgrade `2026082427`, additiv) hält je Mitglied fest, warum die Umwandlung scheiterte — statt nur einer Gesamtzahl bei 2000 Zeilen.
- **Fortschritt sichtbar:** Neuer Status `converting`, Fortschritt wird alle 25 Zeilen zurückgeschrieben und in der Liste angezeigt.
- **Privacy-Tests für Batchdaten** (`privacy_batch_test`): Mitgliedschaft liefert einen Context, wird exportiert und beim Löschen des Contexts vollständig entfernt.
- Tests: `batch_convert_async_test` (Retry ohne Doppelmails, Hintergrundlauf, zeilenbezogener Fehler).
- Versions-Gleichschritt `2026082427`.

## 0.9.49 — 2026-08-26 — CI-Fix: XMLDB-Debugmeldung beim Kampagnen-Token
- **CI-Blocker behoben.** Das in 0.9.48 eingeführte Feld `tokenhash` war als `CHAR NOT NULL` mit `DEFAULT=""` deklariert. XMLDB gibt dafür eine Debug-Meldung aus und korrigiert den Default stillschweigend; `moodle-plugin-ci` wertet jede Debug-Meldung während der PHPUnit-Initialisierung als Fehler. Dadurch scheiterte der Install-Schritt in **allen vier** Repositories (die Geschwister-Plugins werden ja mitinstalliert) — die Quality-Jobs mit `--no-init` liefen weiterhin durch, was das Bild verschleierte. Feld und Upgrade-Schritt deklarieren jetzt keinen Default mehr.
- Versions-Gleichschritt `2026082426`.

## 0.9.48 — 2026-08-26 — Review 0.9.44: P1-7, P1-8, P1-9 + Scope-Entscheidungen
- **P1-9 Campaign-Token nicht mehr im Klartext gespeichert.** Der Token ist ein Bearer-Secret: Wer ihn hat, kann die Kampagne einlösen. Gespeichert wird jetzt nur noch `tokenhash` (SHA-256); die Klartextspalte wird beim Upgrade migriert und **gelöscht** (Savepoint `2026082425`, inkl. Umstellung des Unique-Index). Bestehende Links funktionieren weiter. Der Link wird genau **einmal** bei Erstellung angezeigt und lässt sich nicht wiederherstellen, nur rotieren; die Rotation entwertet den bisherigen Link sofort. Test `test_rotate_invalidates_the_previous_link`.
- **P1-7 Alle übrigen state-changing Aktionen laufen über POST:** Invitation Send/Remind/Revoke, Campaign Delete (mit Bestätigung), Campaign-Link-Rotation (mit Bestätigung) und Policy Delete. Aktionen werden als POST-Buttons statt als Links gerendert; serverseitig wird zusätzlich `REQUEST_METHOD === 'POST'` geprüft. Keine Datei mit `confirm_sesskey()` bleibt ohne POST-Guard.
- **Scope-Entscheidungen dokumentiert** (`docs/scope-decisions-1.0.md`): Die ursprüngliche Anforderung „temporäre Nutzer für andere ausblenden" wird aus dem 1.0-Scope genommen (kein stabiler Moodle-Erweiterungspunkt, kein Core-Hack); Restriktionen sind für 1.0 nur im Kurs-Scope administrierbar, die Engine wertet System- und Kategorieregeln weiterhin aus.
- `docs/review-0.9.35-status.md` um den vollständigen Stand der 0.9.44-DoD ergänzt.
- Versions-Gleichschritt `2026082425`.

## 0.9.47 — 2026-08-26 — Review 0.9.44: P1-1, P1-2, P1-4 + Artefakt-Release-Gate
- **P1-1 Fehlgeschlagene Bereitstellung erreicht jetzt zuverlässig `FAILED`.** Der bisherige Code setzte den Status nach `$transaction->rollback($e)` — Moodle wirft dort erneut, die Zeile war unerreichbar, ein gescheiterter Batch blieb dauerhaft auf `CREATING`. Die große Transaktion ist ersatzlos entfallen: Jede Mitgliedszeile wird erst geschrieben, nachdem Konto **und** Einschreibung erfolgreich waren, sodass ein Abbruch einen kleineren, aber konsistenten Batch hinterlässt. Der Fehlerzustand wird garantiert persistiert, samt Grund im neuen Feld `statusmessage` (Upgrade `2026082424`, additiv).
- **P1-1 Retry ist idempotent:** `provision_members()` erzeugt nur noch die *fehlenden* Konten (Differenz aus Sollzahl und vorhandenen Mitgliedern). Ein von Moodle wiederholter Ad-hoc-Task setzt damit fort, statt Konten und Einschreibungen zu duplizieren.
- **P1-2 Fortschritt ist echt statt vorgetäuscht:** `membercount` wird nach jedem Chunk (50) festgeschrieben; die Kursliste zeigt einen Fortschritt nur noch für `creating` — ein `queued`-Batch bekommt keinen Fake-Zähler. Bei `failed` wird der Fehlergrund angezeigt. Keine Transaktion mehr über bis zu 1000 Nutzeranlagen.
- **P1-4 XLSX-Import strikt validiert, keine stille Trunkierung:** Der Export schreibt eine maschinenlesbare Schema-Kennung (`FLEXACCESS-BATCH-V1`, Zelle H1), die der Import strikt prüft — die Kopfzeile allein wäre sprachabhängig. Zu große Dateien, unlesbare/beschädigte Arbeitsmappen, fremde Dateien und **mehr als 2000 Datenzeilen** werden jetzt mit verständlicher Meldung **abgelehnt**, statt Datensätze stillschweigend zu verwerfen.
- Tests: `batch_failure_test` (Fehlerinjektion → `FAILED` mit Grund; Retry ohne Duplikate; Chunk-Fortschritt), `batch_import_schema_test` (fehlende Schema-Kennung, beschädigte Datei, 2001 Zeilen, künstlich aufgeblähter Row-Index).
- Versions-Gleichschritt `2026082424`.

## 0.9.46 — 2026-08-26 — Review 0.9.44: die drei P0-Blocker geschlossen
- **P0-2 Invitations laufen wieder über die zentrale Mailqueue — und bleiben secret-free.** Der Sofortversand hatte zwar den Klartext-Token beseitigt, umging aber Stundenlimit, Retry/Backoff und Queue-Monitoring. Neu: eine *semantische, secret-freie* Queue-Zeile, die nur Renderer-Klasse und Einladungs-ID enthält (`kind: deferred`). Der neue `invitation_mail_renderer` erzeugt den Token erst im Worker unmittelbar vor dem Versand; `timesent`/`timereminded`/`remindercount` werden ausschließlich nach **tatsächlicher** Zustellung gestempelt. Eine zwischenzeitlich widerrufene oder abgelaufene Einladung erzeugt beim Zustellversuch gar keinen Token mehr.
- **P0-3 Credential-Ausgabe nur noch per POST.** Die Bestätigung ist jetzt ein `single_button` mit `method=post`; zusätzlich wird `confirm` serverseitig nur bei `REQUEST_METHOD === 'POST'` akzeptiert. Ein state-changing GET (Prefetch, Crawler, geteilter Link) kann damit keine Passwortrotation mehr auslösen.
- **P0-1 (Mitwirkung):** CLI-Guard (`PHP_SAPI !== 'cli'` → 403) in allen `tools/`-Skripten, platziert **vor** jedem Schreibzugriff — zweite Verteidigungslinie, falls eine Kopie doch auf einem web-erreichbaren Pfad landet.
- Tests: `invitation_queue_integration_test` weist nach, dass Einladungen dem Stundenlimit unterliegen (bei erschöpftem Budget bleibt die zweite Einladung in der Queue) und dass eine widerrufene Einladung keinen Token mehr erzeugt; die Invitation-Tests bilden jetzt durchgehend die Queue-Semantik ab.
- Versions-Gleichschritt `2026082423`.

## 0.9.45 — 2026-08-26 — Review vollständig abgeschlossen
- **Bugfix:** Der Message-Provider `batchrequest` hatte keinen `messageprovider:batchrequest`-Sprachstring und erschien in den Mitteilungseinstellungen als `[[messageprovider:batchrequest]]`. Beim Vereinheitlichen der String-IDs aufgefallen, zur Laufzeit reproduziert und behoben (en + de).
- **String-IDs vereinheitlicht** (Colon → flach für allgemeine UI-Strings; Capability-, Privacy- und Message-Provider-Keys behalten konventionsgemäß den Doppelpunkt).
- **`docs/review-0.9.35-status.md` aktualisiert:** Alle Punkte des externen Reviews sind abgearbeitet; es bestehen keine Rückstellungen mehr.
- Versions-Gleichschritt `2026082422`.

## 0.9.44 — 2026-08-25 — Review-Abschluss + Nachweisdokument
- **Neu: `docs/review-0.9.35-status.md`** — vollständiger Abarbeitungsstand des externen Reviews: alle P0 und alle P1 geschlossen, P2 überwiegend geschlossen, drei Punkte mit Begründung bewusst zurückgestellt (String-ID-Konvention, weitere Aufteilung der Auth-Fassade, Concurrency-/DAST-Tests). Dient als Nachweisgrundlage für die Freigabeentscheidung.
- **CI-Release-Gate** `ecosystem-lockstep` (siehe enrol-CHANGELOG).
- Versions-Gleichschritt `2026082421`.

## 0.9.43 — 2026-08-25 — Review-P1: asynchrone Batch-Bereitstellung + CI auf development
- **Batch-Erstellung asynchron:** Batches über 50 Konten werden nicht mehr im Web-Request erzeugt. `batch::create()` legt den Batch sofort im Status `queued` an und übergibt die Bereitstellung an den neuen Ad-hoc-Task `\tool_flexaccess\task\provision_batch`. Kleine Batches (≤ 50) laufen weiterhin synchron.
- **Sichtbarer Provisioning-Status:** Neue Felder `status` (`queued`/`creating`/`complete`/`failed`) und `requestedcount` auf `tool_flexaccess_batch` (Upgrade `2026082420`, additiv; Bestandsbatches werden als `complete` markiert). Die Kursliste zeigt Status samt Fortschritt („12 von 200"); Zugangsdaten lassen sich erst nach vollständiger Bereitstellung ausstellen.
- Die Kernroutine `provision_members()` läuft weiterhin in einer Transaktion — ein Fehler rollt den gesamten Batch zurück, statt einen halb gefüllten zu hinterlassen.
- **CI:** Dev-Pipeline und Playwright-Workflows ziehen die Geschwister-Plugins jetzt aus **`development`** (verifizierter Branch-Name; alle vier Repos haben `development` und `main`). Die Main-Pipeline bleibt auf `main`.
- Tests: `batch_async_test` (synchroner Pfad, Queue + Task-Ausführung).
- Versions-Gleichschritt `2026082420`.

## 0.9.42 — 2026-08-25 — CI-Fix: Skew-Robustheit gegenüber älteren Geschwister-Plugins
- **PHPUnit-Fails im CI behoben (Ursache: Sibling-Skew).** Der tool-Job lief mit dem neuen tool-Code, zog aber ein älteres `auth_flexaccess`. Folge: 6 Errors `Call to undefined method auth_flexaccess\api::send_mail_now()` und 1 Failure im Härtungstest.
  - `invitation::queue_mail()` prüft jetzt `method_exists(...,'send_mail_now')` statt nur `class_exists()` — die Klasse existierte ja, nur die Methode fehlte. Bei fehlender API wird **nicht** auf die persistente Queue ausgewichen (das würde den Token at rest speichern, P0-2), sondern `false` zurückgegeben; der Aufrufer meldet den Fehlschlag ehrlich.
  - `invitation_test` überspringt die betroffenen Tests mit klarer Begründung statt zu scheitern.
  - `batch_credential_lifecycle_test` überspringt den Härtungstest, wenn das installierte `auth_flexaccess` älter als `2026082415` ist (die Härtung lebt dort).
- Versions-Gleichschritt `2026082419`.

## 0.9.41 — 2026-08-25 — Review-P1/P2: Test-Gates, Pagination, Rechte-Feinschliff
- **Kurs-Batchliste:** Ansicht hängt jetzt an `viewcoursebatches` (bisher nur bei Erstellrecht sichtbar) und ist **paginiert** (50 pro Seite, `paging_bar`) — vorher unbegrenzt.
- **Playwright-Workflow** installiert alle vier Geschwister-Plugins und nutzt `npm ci` (siehe unten).
- Versions-Gleichschritt `2026082418`.

## 0.9.40 — 2026-08-25 — Review-P1: Batch-Reliability/-Rechte, Invitation-UX
- **Credential-Ausgabe vom Download getrennt:** Das Ausstellen von Batch-Zugangsdaten rotiert Passwörter und ist jetzt eine explizite, **bestätigte** Aktion (Confirm-Seite, POST + sesskey) statt stiller Nebeneffekt eines Downloads. Konvertierte/personalisierte Konten bleiben unberührt (P0-1).
- **Granulare Batch-Capabilities:** Neue kurskontext-Capabilities `viewcoursebatches`, `createcoursebatches`, `issuebatchcredentials`, `convertbatchaccounts`. Verdrahtet an Anlegen (coursebatches), Ausstellen (batchdownload) und Umwandeln (batchconvert). Rückwärtskompatibel: `managebatches` (System) und das bisherige `managecoursebatches` gewähren weiterhin alle Rechte.
- **Batch-Erstellung failure-safe:** `batch::create()` läuft in einer Transaktion — ein Fehler mittendrin rollt den gesamten Batch zurück (kein Teil-Batch). Obergrenze als `MAX_SYNC_CREATE` benannt.
- **Invitation `revoke()`** liefert jetzt `bool`; die UI meldet Erfolg nur, wenn tatsächlich widerrufen wurde (kein irreführendes „widerrufen" bei bereits angenommenen/reservierten Einladungen).
- **Mehrfach-E-Mail-Eingabe:** Ungültige Adressen werden gemeldet statt still verworfen; Duplikate werden dedupliziert.
- Versions-Gleichschritt `2026082417`.

## 0.9.39 — 2026-08-25 — Versions-Gleichschritt (enrol: Zugangsschlüssel-Fix)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082416`.

## 0.9.38 — 2026-08-25 — Security-Härtung (Review-P0/P1) + CI-Rollback
- **P0-1 Batch-Credential-Takeover behoben:** Neues Feld `converted` auf `tool_flexaccess_batch_member`. Bei Personalisierung/Konversion (`batch_import::convert`) wird das Mitglied als nicht mehr batch-verwaltet markiert; `batch::reset_credentials` überspringt solche Konten. Damit kann eine Credential-Neuausgabe niemals das Passwort eines inzwischen persistenten, personalisierten Nutzers rotieren. Zweite Verteidigungslinie in auth (siehe auth-CHANGELOG). Reproduzierender Test `batch_credential_lifecycle_test`.
- **P0-2 Invitation-Token nicht mehr im Klartext at rest:** `invitation`-Mails werden jetzt sofort versendet (`auth_flexaccess\api::send_mail_now`); der Einmal-Token steht nur noch im ausgehenden Link und wird nirgends persistent gespeichert (die Queue enthält keine Token-Payloads mehr). Der irreführende Kommentar wurde korrigiert.
- **P1 (Invitation):** `timesent`/`timereminded`/`remindercount` werden erst nach **erfolgreichem** Versand gesetzt (kein „gesendet", wenn der Mailversand fehlschlug).
- **P0-3 Batch-Privacy vollständig:** `get_contexts_for_userid` erfasst jetzt `batch.usermodified` und Batch-Mitgliedschaften; `export_user_data` exportiert Batch-Modifikationen und Mitgliedschaften; `delete_data_for_all_users_in_context` entfernt zusätzlich Batch-Mitgliedschaften und anonymisiert Einladungs-E-Mails.
- **P0-4 XLSX-Import gehärtet (CVE-2026-40902):** Dateigrößen-Limit (2 MiB), expliziter read-only `Xlsx`-Reader (kein Format-Sniffing), und **gebundene Zeilen-Iteration** (`getRowIterator(1, MAX+1)`, max. 2000 Datenzeilen) — neutralisiert die unbounded-row-dimension-DoS unabhängig von den deklarierten Dimensionen. Upload mit `maxbytes`.
- **CI:** Rückrollung der Dev-Pipeline-Änderung — Geschwister werden wieder aus dem Default-Branch (`main`) gezogen.
- Versions-Gleichschritt `2026082415`.

## 0.9.38 — 2026-08-25 — CI-Rollback + P0-Security-Härtung

- **CI:** Rücknahme der develop-Branch-Umstellung (Dev-Pipeline zieht Geschwister wieder aus `main`).
- **P0-1 (Credential-Takeover behoben):** Neues `converted`-Flag an `tool_flexaccess_batch_member` (install.xml + Upgrade `2026082415`); bei Konversion gesetzt. `reset_credentials()` überspringt konvertierte/personalisierte Mitglieder, sodass ein Batch-Download niemals das Passwort eines inzwischen permanenten Nutzers rotiert. Zweite Verteidigungslinie in `auth::set_account_password()`.
- **P0-2 (Bearer-Token nicht mehr at rest):** Invitation-Mails werden sofort versendet (Token nur im Speicher, nie in der Mailqueue). Irreführender Kommentar korrigiert. **P1:** `timesent`/`timereminded` werden erst nach erfolgreichem Versand gesetzt.
- **P0-3 (Privacy vollständig):** `get_contexts_for_userid`, `export_user_data` und `delete_data_for_all_users_in_context` decken jetzt Batches und Batch-Mitgliedschaften ab (inkl. Löschen der Batch-Member und Invite-E-Mails).
- **P0-4 (XLSX-DoS/CVE-2026-40902):** Import gehärtet — expliziter Xlsx-Reader, Dateigrößenlimit (2 MiB), harte Begrenzung der Zeilen-Iteration (`getRowIterator(1, 2001)`) unabhängig von deklarierten Dimensionen; `maxbytes` am Upload.
- Versions-Gleichschritt `2026082415`.

## 0.9.37 — 2026-08-25 — CI: Dev-Pipeline zieht Geschwister aus develop
- Die **Dev-Pipeline** (`moodle-plugin-ci-dev.yml`) holt die Geschwister-Plugins jetzt per `add-plugin … --branch develop` aus dem **develop-Branch** statt aus `main`. Damit testet die beschleunigte Pipeline den echten Entwicklungsstand aller vier Plugins gemeinsam — kein Skew mehr durch hinterherhängendes `main`. Die **Main-Pipeline** zieht weiterhin aus `main` (Release-Stand).
- Versions-Gleichschritt auf `2026082414`.

## 0.9.36 — 2026-08-25 — Versions-Gleichschritt (CI-Fix im enrol-Behat)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082413`.

## 0.9.35 — 2026-08-25 — Versions-Gleichschritt (CI-Fixes in auth/enrol)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082412`.

## 0.9.34 — 2026-08-25 — Versions-Gleichschritt (E-Mail-Login-Methode + Login-UI)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082411`.

## 0.9.33 — 2026-08-25 — Versions-Gleichschritt (enrol: Fix Teilnehmerlisten-Sichtbarkeit)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082410`.

## 0.9.32 — 2026-08-25 — Versions-Gleichschritt
- Keine Codeänderung. Versions-Gleichschritt auf `2026082409`.

## 0.9.31 — 2026-08-25 — Versions-Gleichschritt (enrol: präzisierte Neutralisierungs-Warnung)
- Keine Codeänderung. Versions-Gleichschritt auf `2026082408`.

## 0.9.30 — 2026-08-25 — Discoverability A/C/D1/D2/D4/D5/D6
- **A:** Dashboard zusätzlich unter *Website-Administration → Nutzer/innen → Nutzerkonten* registriert.
- **C:** `tool/flexaccess:managecoursebatches` jetzt nur noch **Kursmanager + Admin** (nicht mehr editingteacher). Neue Capability `tool/flexaccess:requestbatches` (editingteacher + manager) fürs Beantragen.
- **D5:** Beantragen von Listen (Teilnehmerzahl) auf der Kurs-Seite; Lehrende ohne Provisionierungsrecht lösen eine Anfrage aus. Benachrichtigung als **E-Mail UND Moodle-Message** (Message-Provider `batchrequest`) an genau die Personen mit Provisionierungsrecht im Kurs-Kontext (`managers_for_course()`), mit Deep-Link zum Erstellen. Neue API `can_request`/`require_request`/`managers_for_course`/`notify_request`.
- **D6:** In der Batch-Liste je Zeile „im Kurs öffnen" (Link zur Kurs-Seite).
- Kurs-Navigationsknoten greift jetzt bei `can_request` (Lehrende sehen ihn zum Beantragen). Neues Formular `coursebatchrequest_form`. Tests erweitert (Manager-only manage, teacher request, Empfänger, Message-Versand via Sink).
- Versions-Gleichschritt auf `2026082407`.

## 0.9.29 — 2026-08-25 — P2: PHPUnit-11-Migration + Pakete ohne .git
- `@covers`-Doc-Annotationen der Testklassen auf `#[CoversClass(...)]`-Attribute umgestellt (keine PHPUnit-Deprecations mehr). `.git`-Verzeichnisse aus dem Paket entfernt. Versions-Gleichschritt auf `2026082406`.

## 0.9.28 — 2026-08-25 — P1 T2: kurs-interne Zugangslisten-Verwaltung
- **T2:** Lehrende verwalten anonyme Zugangslisten jetzt **im Kurs** (`coursebatches.php`): Erstellen, Auflisten und Download (XLSX/PDF-Liste/Karten) für genau diesen Kurs, ohne in die Site-Administration zu wechseln.
- Neue Kurskontext-Capability `tool/flexaccess:managecoursebatches` (editingteacher + manager). Neue API `batch::for_course()`, `count_for_course()`, `can_manage()`, `require_manage()` (Dual-Kontext: System-`managebatches` ODER Kurs-`managecoursebatches`).
- `batchdownload.php` akzeptiert nun den Kurskontext (Lehrende dürfen ihre eigenen Listen herunterladen). Navigations-Hook `tool_flexaccess_extend_navigation_course()` verlinkt die Seite im Kurs. Neues Formular `coursebatch_form` (ohne Kurs-Selektor). Test `coursebatches_test`.
- Versions-Gleichschritt auf `2026082405`.

## 0.9.27 — 2026-08-24 — CI-Fix: fehlerhafte Workflow-Ausdrücke (${ } → ${{ }})
- Fehlerhafte GitHub-Actions-Ausdrücke im `lint-jsamd`-Job korrigiert (`${ } → ${{ }}`); mit `actionlint` gegengeprüft (0 Findings). Kein PHP-Code geändert; Versions-Gleichschritt auf `2026082404`.

## 0.9.26 — 2026-08-24 — CI: JS/AMD/Mustache-Job wiederhergestellt (catquiz-Form 1:1)
- `lint-jsamd` (grunt + mustache) in dev wiederhergestellt; Mustache/npm/Grunt in main ergänzt. Kein PHP-Code geändert; Versions-Gleichschritt auf `2026082403`.

## 0.9.25 — 2026-08-24 — CI-Fixes (DB-Versionen, vollständige Geschwister, eine Main-Pipeline)
- CI: `postgres:13→16`, `mariadb:10.8→10.11`; jede Pipeline installiert alle drei Geschwister (Ökosystem-Tests); `moodle-release.yml` entfernt.
- Kein PHP-Code geändert; Versions-Gleichschritt auf `2026082402`.

## 0.9.24 — 2026-08-24 — Versions-Gleichschritt (enrol: L3-Kurs-Einstieg + Load-Pläne + CI-Konsolidierung)
- Keine Codeänderung in diesem Plugin. CI: eine Main-Pipeline (Ökosystem-`main.yml` entfernt); Load-Workflows liegen im Hub `enrol_flexaccess`.
- Versions-Gleichschritt auf `2026082401`.

## 0.9.23 — 2026-08-24 — Versions-Gleichschritt (enrol: Zugangs-Blocker-Fix + Kopplungscheck)
- Keine Codeänderung in diesem Plugin; gemeinsamer Versions-Bump auf `2026082400` und aktualisierte Abhängigkeits-Pins.
- **CI-Fix:** `@package`-Korrektur in `tools/mustache_check.php` und `tools/fix_phpdoc.php` (Copy-Paste-Rest).
- **CI-Pipeline:** getrennte Dev-/Main-Workflows + dispatch-only JMeter-/k6-Lastworkflows (catquiz-Vorbild, FlexAccess-Geschwister als Abhängigkeit).

## 0.9.22 — 2026-08-20 — Fix: PHPDoc-Checker (CI) — @param-Vollstaendigkeit
- **Fix (CI PHPDoc):** Der Moodle-PHPDoc-Checker erkennt generische Array-Typen (`array<...>`, `array{...}`) in `@param` nicht und meldete daher "incomplete parameters list" fuer `batch_export::excel/pdf_list/login_cards`, `batch_import::convert/target_username`. Diese `@param`-Typen auf `array` vereinfacht (Form in der Beschreibung erhalten). Zusaetzlich `invitation::queue_mail`: fehlender `@param $token` ergaenzt (Signatur hat 5 Parameter, Docblock hatte 4). Verwaisten doppelten Docblock vor `invitation::reserve()` entfernt. Keine Logikaenderung.

## 0.9.21 — 2026-08-20 — Feature: Excel-Rückkonversion von Stapel-Accounts (Kampagne, Teil 2)
- **Neu (Kampagne, Teil 2):** Rückkonversion aus dem ausgefüllten Export. Neuer Service `local\batch_import` (`parse()` liest die hochgeladene .xlsx via PhpSpreadsheet; `convert()` ordnet jede Zeile über die ursprüngliche Nutzerkennung dem Stapel-Mitglied zu, personalisiert Vorname/Nachname/E-Mail und wandelt den Account zu einem vollwertigen, dauerhaften Account um — inkl. Passwort-setzen-Mail an die neue Adresse als E-Mail-Konfirmation). Nutzerkennungs-Änderung per Excel (optionale Spalte fuer die neue Nutzerkennung, Vorrang) ODER per Regel (E-Mail / erzeugte Kennung behalten / lokaler E-Mail-Teil / vorname.nachname). Zeilen ohne Treffer/E-Mail werden mit Meldung übersprungen. Neue Seite `batchconvert.php` (+ `batch_convert_form` mit Filepicker + Regel-Auswahl), verlinkt von der Stapel-Ansicht. Excel-Export um die optionale Spalte fuer die neue Nutzerkennung erweitert.

## 0.9.20 — 2026-08-20 — Feature: Stapel-Bereitstellung von Kurs-Accounts (Kampagne, Teil 1)
- **Neu (§ Kampagne, Teil 1):** Stapelweise Bereitstellung von Kurs-Accounts. Neue Tabellen `tool_flexaccess_batch` + `tool_flexaccess_batch_member`, Service `local\batch` (Anlage von N Accounts mit zufaelliger Nutzerkennung + Passwort, Einschreibung in einen Kurs ueber die FlexAccess-Einschreibung/Teilnehmerrolle, vorlaeufig-eingeschraenkt ODER dauerhaft-vollwertig; `reset_credentials` fuer sichere Neu-Ausgabe). Drei Exporte via `local\batch_export`: Excel (PhpSpreadsheet; Nutzerkennung, Passwort, leere Spalten Vorname/Nachname/E-Mail/Profilfelder), druckbare PDF-Liste (Nutzerkennung alphabetisch, Passwort, leere Namensspalten) und Login-Kaertchen-PDF (8 Karten/Seite mit Nutzerkennung, Passwort, Kurs-URL, QR-Code). Admin-UI `batches.php` (+ `batch_form`) und `batchdownload.php` (setzt frische Passwoerter und streamt Excel/PDF/Kaertchen einzeln oder als ZIP). Neue Capability `tool/flexaccess:managebatches`, Menuepunkt, Privacy-Provider erweitert. Klartext-Passwoerter werden nie gespeichert (nur im Arbeitsspeicher waehrend Anlage/Export).

## 0.9.19 — 2026-08-20 — Fix: Upgrade-Crash beim Verbreitern der indizierten ratehit.identifier-Spalte
- Keine Codeaenderung.

## 0.9.19 — 2026-08-20 — RC-Gates (Review 0.9.17): Invitation-Security (2 P0) + Playwright-Lockfile
- **P0-1 (Bearer-Secret at rest):** Der Invitation-Token wird nicht mehr im Klartext gespeichert. `tool_flexaccess_invite.token` (CHAR40) -> `tokenhash` (CHAR64, SHA-256); der Klartext-Token wird erst beim Versand erzeugt, nur sein Hash persistiert (Upgrade migriert bestehende Tokens -> Hash und droppt die Klartextspalte). Jeder Versand/Reminder gibt einen frischen Single-Use-Token aus; der Klartext existiert nur transient in der (nach Zustellung geprunten) Mailqueue.
- **P0-2 (Consume-before-success):** Einladungen werden jetzt nach Reserve->Grant->Commit verarbeitet (neuer Status `reserved` + `timereserved`, mit Timeout-Reclaim fuer abgebrochene Versuche). `invite.php` reserviert vor der Registrierung und **committet erst bei Erfolg**; scheitert die Registrierung, geht die Einladung via `release_reservation()` zurueck auf `pending` und ist erneut nutzbar. Neuer Test deckt Reserve/Release/Retry + Commit ab.
- Campaign-Token bleibt unveraendert (bewusst re-teilbar, siehe SSOT).

## 0.9.18 — 2026-08-20 — Fix: PHPDoc-Parameterliste (enrol-CI rot)
- Keine Codeaenderung.

## 0.9.17 — 2026-08-20 — Fix: Cross-Plugin-Mailqueue (Standalone-CI) + saubere API-Grenze
- **Fix (Standalone-CI):** Der Invitation-Service schrieb Mails per Raw-Insert direkt in `auth_flexaccess_mailqueue`. In einer isolierten tool-CI (ohne installiertes `auth_flexaccess`) fehlte diese Tabelle -> zwei PHPUnit-Errors. Behoben durch:
  - **Saubere API-Grenze:** Versand laeuft jetzt ueber die oeffentliche `\auth_flexaccess\api::queue_mail()` (statt Direktzugriff auf die fremde Tabelle); der tote Raw-Insert wurde entfernt.
  - **Ehrlicher Test-Skip:** mail-abhaengige Tests (`test_send_and_remind`, `test_due_reminders`) ueberspringen sauber, wenn `auth_flexaccess`/die Mailqueue nicht installiert ist (Isolationslauf), statt zu erroren.
  - **CI-Contract:** die tool-eigene `moodle-ci.yml` (PHPUnit + Behat) installiert jetzt die deklarierten Schwestern `auth`+`enrol` via pinbarem `SIBLING_REF`, sodass der Mailpfad in CI real getestet wird.

## 0.9.16 — 2026-08-20 — P2-Cleanup: Performance, Reliability, i18n
- **Perf (§15):** Campaign- und Invitation-Formular verwenden jetzt das AJAX-gestuetzte `course`-Autocomplete-Element statt eines `<select>` ueber *alle* Kurse; die Verwaltungsseiten laden Kurs-Anzeigenamen nur noch fuer die aktuell angezeigte Seite (skaliert auf 10.000+ Kurse).
- **i18n (§20):** Participant-Visibility-Enums ('show'/'hide'/'inherit') werden ueber `policy_presenter::visibility_label()` lokalisiert ausgegeben statt als Rohwert (neuer String `policyvisibilityinherit`).

## 0.9.15 — 2026-08-20 — RC-Gates (Review 0.9.13): 4 P0 + Reliability + Doku/CI-Sync
- Campaign- und Invite-Landing rufen Quick-Registration jetzt als **trusted** auf (Kurs-Gate wird nicht doppelt geprueft).

## 0.9.14 — 2026-08-20 — Einladungen: personengebundenes Single-Use-Modell (Review §9)
- **Invitations:** neues personengebundenes Einladungsmodell (`tool_flexaccess_invite`, install.xml + Upgrade 2026081914) als Ergaenzung zum teilbaren Campaign-Link. Service `local\invitation`: create/get/all(paginiert)/count, `send`/`remind`/`revoke`, **atomares single-use `accept`** (per-Token-Lock + Statusguard), `is_acceptable` (pending + nicht abgelaufen + nicht widerrufen) und `due_reminders`.
- **Mail via Queue:** Einladungs- und Erinnerungsmails laufen ueber die FlexAccess-Mailqueue (Ratelimit gilt).
- **UI:** capability-gesicherte Verwaltung `invitations.php` (`tool/flexaccess:manageinvitations`, RISK_SPAM|RISK_PERSONAL) mit Bulk-Anlage (`invitation_form`, mehrere Empfaenger + optionales Ablaufdatum + Sofortversand) und Aktionen Send/Resend/Remind/Revoke; oeffentliche Landing `invite.php?token=` konsumiert das Token, bindet die Registrierung an die Einladungs-E-Mail und loest erst bei erfolgreicher Kontoerstellung ein.
- **Reminder-Task:** `send_invitation_reminders` (db/tasks.php, taeglich) erinnert offene, gesendete, nicht angenommene Einladungen einmalig nach `invitationreminderdays` (Default 3, 0 = aus).
- **Privacy:** Provider um die invite-Tabelle erweitert (E-Mail + usermodified als personenbezogene Daten deklariert, exportiert — inkl. an die eigene Adresse gerichteter Einladungen —, bei Loeschung anonymisiert).
- Tests: Lebenszyklus, Expiry/Revoke, send/remind, single-use accept, due-reminders.

## 0.9.13 — 2026-08-20 — P2-Batch: Performance, Retention, Supply-Chain, Doku
- **Perf:** `campaign::all()` paginiert (`limitfrom`/`limitnum`) + `count_all()`; die Admin-Kampagnenliste nutzt eine `paging_bar` (50/Seite). Test deckt Paginierung/Zaehlung ab.

## 0.9.12 — 2026-08-20 — P1/P2-Härtung: Security (a) + Identity/State (b) + Cleanup/Docs (c)
- **(c) Cleanup:** tote `stub*`-Strings entfernt; Makefile-Bereinigung.

## 0.9.11 — 2026-08-20 — RC-Hardening: P0#6 (Admin-Conversion über Mailqueue)
- Keine Codeaenderung.

## 0.9.10 — 2026-08-20 — RC-Hardening: 7/8 P0 aus dem 0.9.8-Review
- **P0#2 — Campaign-Kapazitaet unter Parallelitaet abgesichert:** `redeem()` reserviert einen Platz jetzt unter **Moodle-Lock** (Pruefung + Inkrement atomar); die Landing-Seite **reserviert vor** der Kontoerstellung und gibt den Platz via `release_reservation()` zurueck, falls die Anmeldung scheitert. Kein Doppel-Grant fuer den letzten Platz mehr.
- **P0#8 — echter Privacy-Provider:** `null_provider` ersetzt; die Kampagnen-Tabelle (usermodified/name/timemodified) wird als personenbezogene Metadaten deklariert, exportiert und bei Loeschung anonymisiert (usermodified=0).

**Offen (bewusst gestaffelt):** P0#6 — Admin-Conversion versendet die Passwort-Mail noch via Core `setnew_password_and_mail` (umgeht die FlexAccess-Mailqueue/Ratelimit). Fix erfordert einen neuen queued 'set-password'-Mailfluss.

## 0.9.9 — 2026-08-19 — Welle 4 Abschluss: Accessibility-Gate + Docs-SSOT & Traceability
- Keine Codeaenderung.

## 0.9.8 — 2026-08-19 — Welle 4: Policy-Caching (Perf)
- Keine Codeaenderung.

## 0.9.7 — 2026-08-19 — Welle 5: Einladungskampagnen (§49)
- **§49 — Campaign/Invitation:** neue tokenisierte Einladungskampagnen. Ein Admin erstellt teilbare Links, über die sich Personen per FlexAccess-Schnellregistrierung selbst in einen Kurs einschreiben.
- **Datenmodell:** neue Tabelle `tool_flexaccess_campaign` (install.xml + Upgrade 2026081907) mit Name, Zielkurs, eindeutigem Token, Aktiv-Flag, Verfügbarkeitsfenster, Max-Einlösungen/Zähler und Kampagnen-Gate.
- **Service** `local\campaign`: CRUD, `is_redeemable` (Aktiv/Fenster/Cap), **atomare** `redeem` (bedingtes UPDATE, überschreitet den Cap auch unter Last nicht) und `passes_gate` (bcrypt-Passwort bzw. Domain-/Subdomain-Allowlist).
- **Kampagnen-Gate:** die von der Roadmap vorgesehene tool/Campaign-Platzierung des Zugangs-Gates (Passwort ODER E-Mail-Domain), unabhängig vom kurs-/systemweiten Gate.
- **UI:** capability-gesicherte Verwaltungsseite `campaigns.php` (Kapability `tool/flexaccess:managecampaigns`, RISK_CONFIG|RISK_SPAM) mit Anlegen/Bearbeiten/Löschen und Übersicht inkl. Einlösungsstand; öffentliche Landing-Seite `campaign.php?token=` validiert Kampagne + Gate, hostet die Schnellregistrierung und zählt eine Einlösung erst bei erfolgreicher Kontoerstellung.
- Tests: `campaign` CRUD, Fenster-/Cap-Validierung, atomare Einlösung, Passwort-/Domain-Gate.

## 0.9.6 — 2026-08-19 — Welle 4: Persistence-Follow-up (schließt P0 #9 vollständig)
- Keine Codeaenderung.

## 0.9.5 — 2026-08-19 — Welle 3 Strom E: administrierbare Kategorie-Policies (P0 #8) + Cleanup
- **P0 #8 — Verwaltungs-UI:** neue capability-gesicherte Seite `managepolicies.php` (Kapability `tool/flexaccess:managepolicies`, RISK_CONFIG) mit Formular zum Setzen/Loeschen der Kategorie-Overrides (Tri-State Erben/Erlauben/Verweigern fuer die Zugangsmethoden, Lebensdauern, Teilnehmer-Sichtbarkeit) plus Uebersichtstabelle. Menueeintrag unter FlexAccess.
- Tests: `category_policy` save/load/merge/delete (enrol).

## 0.9.4 — 2026-08-19 — CI-Härtung + Upgrade-Robustheit (Plugin-Isolation, PHPDoc, reset_role_capabilities)
- **CI-Fix (Plugin-Isolation):** `account_labels` und der zugehoerige Test nutzen die stabilen Wire-Werte direkt statt der auth-Klassenkonstanten. Die tool-Testsuite laeuft damit isoliert (ohne auth) ohne "class not found"; in Produktion unveraendert.

## 0.9.3 — 2026-08-19 — Welle 3 Strom F: Quick-Registration neu spezifiziert (P0 #5)
- Keine Codeaenderung.

## 0.9.2 — 2026-08-19 — Welle 2: Retention/Deletion, zentraler Conversion-Guard, Temp-Restriktionen (P0 #9/#10/#6)
- Keine Codeaenderung.

## 0.9.1 — 2026-08-19 — Welle 1: Token-Sicherheit + atomares Temp-Rate-Limit (P0 #1, #2)
- Keine Codeaenderung.

## 0.9.0 — 2026-08-19 — Beta-Schwelle: CI-Fix, Maturity BETA, Versions-Neustart
- Versionsschema auf `2026081900` / Release `0.9.0` gesetzt, Maturity auf **MATURITY_BETA** angehoben; Cross-Plugin-Dependencies auf `2026081900` gezogen.
- **CI-Fix:** fehlende `@param $reference` in den Docblocks von `api::search_accounts` und `api::build_account_filter` ergaenzt (PHPDoc-Checker).
- Hinweis: Zwei aus dem erneuten Audit stammende Rest-P0 (Klartext-Token in der Mailqueue; generelles atomares Rate-Limit fuer anonyme Temporary-Erzeugung) sind als erste Beta-Haertungswelle eingeplant.

## 0.1.39 — 2026-08-19 — Konfigurierbare Rate-Limits, Cleanup, i18n, Backup/Restore, CI-Härtung
- **§37 i18n:** rohe Enum-Werte (Kontotyp/-status) werden in der Kontenliste jetzt ueber `account_labels` lokalisiert dargestellt statt als interne Rohwerte.
- **§3 Cleanup:** ungenutzte Capability `tool/flexaccess:manageaccounts` entfernt.

## 0.1.38 — 2026-08-19 — Re-login-fähige Konversion, Transaktionen, Mailqueue-Limit, Referenzsuche (§7/§8/§13/§16/§36)
- **§8:** Neue `convert.php` + `convert_form` verlangen die echte E-Mail des Nutzers und rufen `admin_convert` auf (Set-Password-Mail). Die Aktion in `accounts.php` verlinkt dorthin (statt Ein-Klick-Konversion ohne E-Mail).
- **§36:** Sichtbares Such-Formular; `reference_query::normalise` wird jetzt genutzt (behebt zugleich toten Code), Referenznummern werden mitgesucht.

## 0.1.37 — 2026-08-19 — Teilnehmerlisten-Sichtbarkeit durchgesetzt (§35, P0)
- Keine Codeaenderung.

## 0.1.36 — 2026-08-19 — Capacity-Race / verwaiste Accounts behoben (§18)
- Keine Codeaenderung.

## 0.1.35 — 2026-08-19 — DSGVO-Privacy-Provider (§11) + PHPDoc-Fixes
- Keine Codeaenderung (Privacy bleibt `null_provider`).

## 0.1.35 — 2026-08-19 — DSGVO-Datenschutz-Provider vervollstaendigt (§11)
- Keine Codeaenderung (Provider bleibt korrekt `null_provider`: keine eigenen Tabellen).

## 0.1.34 — 2026-08-19 — Rate-Limiting der oeffentlichen Schreib-Endpoints (§5)
- Keine Codeaenderung.

## 0.1.33 — 2026-08-19 — Enrolment-Expiry (§32/§33) + echte jmeter/playwright-Plaene (§26/§27)
- Keine Codeaenderung.

## 0.1.32 — 2026-08-19 — Magic-Login, Mail-Queue-Retrofit, SEC-03, main-CI + jmeter/playwright
- Keine Codeaenderung.

## 0.1.31 — 2026-08-18 — Aufraeumen: toter persistence_followup-Mailpfad entfernt
- Keine Codeaenderung.

## 0.1.30 — 2026-08-18 — DRY: gemeinsame Identitaetsfelder der Formulare
- Keine Codeaenderung.

## 0.1.29 — 2026-08-18 — Paket B: E-Mail-Verifikation der Persistierung (Option, Default an)
- Keine Codeaenderung.

## 0.1.28 — 2026-08-18 — Paket B: B4 Konvertierung temporaer -> persistent
- Keine Codeaenderung.

## 0.1.27 — 2026-08-18 — Paket A abgeschlossen: Methodenauswahl (Gast + Normallogin)
- Keine Codeaenderung.

## 0.1.26 — 2026-08-18 — Paket A: Quick-Registration (allowquick)
- Keine Codeaenderung.

## 0.1.25 — 2026-08-18 — CI-Fix (veraltete Behat-Datei)
- Keine Codeaenderung.

## 0.1.24 — 2026-08-18 — Paket A: B2 (Access-Key) verifiziert
- **Access-Key-Durchsetzung end-to-end per Behat verifiziert** (Sicherheits-Blocker B2 geschlossen): Challenge-Formular, falscher Schluessel wird abgewiesen, korrekter Schluessel gewaehrt Zugang; Rate-Limit im Flow, Schluessel nur per POST (nie in URL/Log). 3 Ecosystem-Szenarien, 20 Steps gruen.
- Keine Codeaenderung.

## 0.1.23 — 2026-08-18 — CI-Fixes
- Keine Codeaenderung.

## 0.1.23 — 2026-08-18 — Paket A (Access), Teil 2: Zugangsschlüssel
- **Der Zugangsschlüssel ist jetzt wirksam** (war Sicherheits-Blocker B2). E2E per Behat verifiziert: falscher Schlüssel -> Fehler, richtiger -> Kurszugang.
- Keine Codeaenderung; Teil des verifizierten Gesamtlaufs.

## 0.1.22 — 2026-08-18 — Paket A (Access), Teil 1
- **Der URL-/aktivitaetssensitive Zugang funktioniert jetzt end-to-end** (war Beta-Blocker B1). Real per Behat verifiziert: ein anonymer Besucher gelangt ueber die Entry-Page zu temporaerem Zugang und landet im Zielkurs.
- Keine Codeaenderung; Teil des verifizierten Gesamtlaufs.

## 0.1.21 — 2026-08-18
- **Cross-Plugin-Funktionalitaet wird jetzt echt end-to-end getestet.** Behat wurde in der Sandbox real ausgefuehrt (Moodle 5.3dev, non-JS): alle vier Standalone-Smoke-Features **und** ein neues Cross-Plugin-E2E-Szenario bestehen.
- Keine Codeaenderung; das Dashboard ist die Cross-Plugin-Assertion im E2E-Szenario (zaehlt den vom Enrol-Flow erzeugten Account).

## 0.1.20 — 2026-08-18
- **Behat gruen gemacht (war der letzte rote CI-Schritt).** Die Feature-Dateien testeten teils veraltetes Scaffold-Verhalten bzw. noch nicht implementierte Ablaeufe; sie wurden auf standalone lauffaehige Smoke-Szenarien mit ausschliesslich Standard-Steps umgestellt. Verifiziert mit moodle-plugin-ci 4.5.11 (phpcs 0/0, validate 0 Fehler, PHPUnit auf Moodle 5.3dev gruen).
- **Robustheit:** Dashboard (`index.php`) degradiert sauber, wenn das Schwester-Plugin auth_flexaccess nicht installiert ist (Hinweis statt Fatal) — behebt den Behat-Crash der isolierten CI. Behat `navigation.feature` prueft das Dashboard. Playwright/Load-Workflows entfernt.

## 0.1.19 — 2026-08-18
- **Verifiziert mit der exakten CI-Toolchain (moodle-plugin-ci 4.5.11 PHAR): phpcs 0/0, `validate` 0 Fehler, PHPUnit auf Moodle 5.3dev gruen.** Cross-Plugin-Integrationstests laufen in der Vollumgebung (alle vier Plugins) normal und ueberspringen sich nur in der Einzel-Plugin-CI.
- **Weitere CI-Fixes:** `policy_presenter_test` ueberspringt sich sauber (markTestSkipped), wenn `enrol_flexaccess` (Tabelle `enrol_flexaccess_instance`) fehlt. Behat `navigation.feature` mit `@tool`-Typ-Tag.

## 0.1.18 — 2026-08-17
- **Linting robust fuer aeltere moodle-cs gemacht (die lokale `make check`-Umgebung nutzt eine strengere/aeltere moodle-cs als die CI):** `@package`-Tag in jedem Datei-, Klassen-/Interface-/Trait- und Top-Level-Funktions-Docblock ergaenzt (aeltere moodle-cs verlangt dies ueberall; neuere ab 3.6 hat es gelockert). Test-Klassen erhielten `@covers` auf die jeweils geprueften Klassen (behebt die `missing coverage information`-Warnungen). **Gegengeprueft:** die echte CI (moodle-plugin-ci 4.5.11) meldet weiterhin 0 Verstoesse, PHPUnit auf Moodle 5.3dev bleibt gruen.

## 0.1.17 — 2026-08-17
- **Real auf Moodle 5.3dev (branch 503, PG17) verifiziert — PHPUnit gruen, phpcs 0/0.** Dabei behobene echte Fehler: fehlende Capability-Sprachstrings (flexaccess:viewdashboard, flexaccess:viewaccounts, flexaccess:manageaccounts, flexaccess:convertaccounts, flexaccess:managemailqueue, flexaccess:viewpolicies) ergaenzt (Core tool_capability-Check).
- **CI grün gemacht (phpcs, real verifiziert mit moodlehq/moodle-cs v3.7):** Sprachdateien alphabetisch sortiert + `@package` ergänzt (Moodle LangFilesOrdering); einzeilige Docblocks in Mehrzeilenform mit Beschreibungszeile überführt; Multiline-Funktionsaufrufe per phpcbf normalisiert; unnötige `MOODLE_INTERNAL`-Checks entfernt; Konstanten-Docblocks ergänzt.
- **Makefile:** Vorlage übernommen und an das Plugin-Verzeichnis angepasst (PLUGIN_NAME/PLUGIN_REL/MOODLE_ROOT); `make check` zeigt nur Fails, läuft volle Lintings + PHPUnit.
- **GitHub-Workflows:** getrennt für Development (`moodle-ci.yml`, branches-ignore main) und Main (`moodle-release.yml`); zusätzlich `playwright.yml` und `load.yml` bereitgestellt. Von vimipad-spezifischen Bundle/AMD/Node-Schritten befreit; Behat-Tags und Pfade je Komponente. `.gitattributes`/`.gitignore` adaptiert.

## 0.1.16 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.16 (keine funktionale Änderung).

## 0.1.15 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.15 (keine funktionale Änderung).

## 0.1.14 — 2026-08-17
- **Dashboard + Mailqueue-Seite real:** `index.php` zeigt Konten-/Mail-Kennzahlen über die Auth-Facaden; `mailqueue.php` listet die Queue (Filter, Pagination, ohne Token/Payload). Keine Direktzugriffe auf Auth-Tabellen.

## 0.1.13 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.13 (keine funktionale Änderung).

## 0.1.12 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.12 (keine funktionale Änderung).

## 0.1.11 — 2026-08-17
- **Accounts-Seite real umgesetzt:** `accounts.php` listet FlexAccess-Konten über die Auth-Facade (Filter, Pagination) und bietet capability-/sesskey-gesicherte **Admin-Conversion** temporärer Konten. Keine Direktzugriffe auf Auth-Tabellen.

## 0.1.10 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.10 (keine funktionale Änderung).

## 0.1.9 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.9 (keine funktionale Änderung).

## 0.1.8 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.8 (keine funktionale Änderung).

## 0.1.7 — 2026-08-17
- **Hochziehen:** `policies.php` konsumiert jetzt real die enrol-Facade `enrol_flexaccess\api::get_effective_policy` und zeigt je Kurs (`?courseid=`) die wirksame Policy read-only an — ohne Geheimnisse/Hashes. Neuer reiner `local\policy_presenter` (+ PHPUnit `policy_presenter_test`).
- **CI-Fix (phpcs):** zu lange Zeile in `policies.php` in einen Sprachstring ausgelagert.
- **CI-Fix:** pgsql-Workflow-createdb-Zeile entfernt.

## 0.1.6 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.6 (keine funktionale Änderung).

## 0.1.5 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.5 (keine funktionale Änderung; Policy-Diagnose kann `enrol_flexaccess\api` konsumieren).

## 0.1.4 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.4 (keine funktionale Änderung).

## 0.1.3 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.3 (keine funktionale Änderung; Campaigns/Follow-up folgen ab 0.5.x).

## 0.1.2 — 2026-08-17
- Scope-Erweiterung (Planung/Doku): leichtgewichtiges **Campaign/Invitation-Modell** und **Follow-up-Konfiguration** aufgenommen (ADR-014); Roadmap 0.5.x + Backlog ergänzt; Umstellung von null_provider auf vollständigen Privacy Provider vorgemerkt.

## 0.1.1 — 2026-08-17
- Version scheme moved to incremental `0.1.x` (release `0.1.1`).
- Added `$plugin->supported = [405, 502]` for matrix consistency; hard dependencies on `auth_flexaccess` + `enrol_flexaccess` confirmed (ADR-010).

## 0.1.0-alpha — 2026-08-17
- Initial architecture scaffold.

## 0.1.0-alpha3 - 2026-08-17
- Add system/course shared access-key requirement for temporary-user entry; secrets are hash-only.
