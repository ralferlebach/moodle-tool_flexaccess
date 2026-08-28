# Session 002 — Von 0.9.13 zu 1.0.0-RC1

**Zeitraum:** 2026-08-25 bis 2026-08-27
**Stand am Ende:** Version `2026082700`, Release `1.0.0-RC1`, Reifegrad `MATURITY_RC`
**Ausgangspunkt:** `0.9.13`, mehrere offene P0-Blocker aus dem externen Review

---

## 1. Was erreicht wurde

Zwei externe Review-Runden wurden vollständig abgearbeitet. Am Ende der Sitzung erteilte der
Reviewer das **Code-Level-RC-Siegel**; alle CI-Strecken laufen grün, einschließlich der
produktionsnahen Gates Playwright, jMeter und k6 (Read Load und Capacity Race).

| Prüfstrecke | Stand |
| --- | --- |
| Dev-CI | grün |
| Main-CI (Matrix 4.5 / 5.0 / 5.2, PostgreSQL und MariaDB) | grün |
| Playwright | grün |
| jMeter | grün |
| k6 Read Load und Capacity Race | grün |
| PHPUnit | auth 63, enrol 100, mod 4, tool 65 |
| Behat | 16 Szenarien |

---

## 2. Geschlossene Blocker

### Aus dem Review zu 0.9.35

- **Batch-Zugangsdaten-Übernahme:** Eine personalisierte Kennung konnte durch eine erneute
  Ausstellung überschrieben werden. Konvertierte Mitglieder sind jetzt markiert und ausgenommen.
- **Einladungstoken im Klartext:** Die Warteschlange enthält nur noch Renderer-Klasse und
  Einladungs-ID; der Token entsteht erst im Worker unmittelbar vor dem Versand.
- **Batch-Privacy:** Kontexte, Export und Löschung decken Batches und Mitgliedschaften ab.
- **XLSX-DoS (CVE-2026-40902):** Größen-, Zeilen- und Schemagrenzen, gebundener Zeileniterator.
- **Entwicklerwerkzeuge im Release:** Packaging respektiert `.gitattributes export-ignore`,
  CLI-Guard in allen `tools/`-Skripten, CI-Gate auf die tatsächliche Dateiliste.
- **Zugangsdaten per GET:** Ausstellung ausschließlich per POST mit serverseitiger Methodenprüfung.

### Aus dem Review zu 0.9.51

- **Bereitstellung pro Mitglied atomar:** Scheitert ein Schritt nach der Kontoanlage, wird das
  Konto kompensierend entfernt (`rollback_batch_account()`), begrenzt auf FlexAccess-Konten mit
  Platzhalteradresse. Fehlgeschlagene Kompensation wird gemeldet und am Batch vermerkt.
- **Einladungs-Resend zerstört keinen gültigen Link:** Der neue Token wird als
  `pendingtokenhash` geparkt und erst nach bestätigter Zustellung aktiv.

### Aus dem Review zu 0.9.61

- **SMTP-Stundenlimit war unterlaufbar:** Die Zählung erfolgt jetzt über `timesent` statt über den
  Status. Eine zugestellte, aber unquittierte Mail zählt damit gegen das Limit.
- **Zustellung und Quittung getrennt:** Eine Quittung benötigt keine SMTP-Kapazität, verbraucht
  kein Sendebudget und läuft auch bei erschöpftem Kontingent. Eigener Wiederholungszähler.
- **Queue-umgehende API entfernt:** `api::send_mail_now()` und `mail_worker::send_now()` umgingen
  Warteschlange, Limit, Wiederholung und Überwachung.
- **Capability-Lücke:** Die Kursseite öffnet bei jeder relevanten Berechtigung; `can_request()`
  berücksichtigt `createcoursebatches`; Anfragen erreichen auch dessen Inhaber.

---

## 3. Selbst gefundener Sicherheitsbefund

**Rollenwechsel wurde bei den Zugangslisten nicht beachtet.** Zwei kursbezogene Entscheidungen
fragten `managebatches` im *Systemkontext* ab. Moodle setzt die Administrator-Umgehung beim
Rollenwechsel nur für den gewechselten Kontext aus, weshalb der Eintrag „Anonyme Zugangslisten"
auch in der Vorschau als Teilnehmer/in sichtbar blieb und die Seite sich öffnen ließ.

Alle Prüfungen laufen jetzt im Kurskontext; eine auf Systemebene zugewiesene Rolle greift dort
weiterhin, weil Berechtigungsprüfungen den Kontextpfad nach oben durchlaufen.

**Einordnung:** keine Rechteausweitung — betroffen waren ausschließlich Konten, die den Zugang
ohnehin besitzen. Es war eine falsche Vorschau, kein offener Zugang. Der Regressionstest wurde
gegen den alten Stand gegengeprüft und schlägt dort fehl.

---

## 4. Fachliche Erweiterungen

- **Asynchrone Bereitstellung und Konversion** großer Zugangslisten über Ad-hoc-Tasks, idempotent,
  mit zeilenbezogenen Teilfehlern (`converterror`) und sichtbarem Fortschritt.
- **Willkommensmail nach der Persistierung** mit Anmeldename und Login-URL. Der Anmeldename wird
  beim temporären Zugang erzeugt und war den Nutzenden vorher unbekannt.
- **Passwortänderung und -wiederherstellung** für persistierte Konten (`can_change_password()`,
  `can_reset_password()`).
- **Passwortrichtlinie:** Generierte Passwörter durchlaufen `check_password_policy()`; die
  Standardlänge beträgt 6 Zeichen, die Richtlinie der Website hat Vorrang.
- **Login-Kärtchen schnittgerecht:** Der Abstand zwischen zwei Karten ist doppelt so groß wie der
  Seitenrand, sodass Halbieren und nochmaliges Halbieren gleichmäßige Ränder ergibt. Freitext je
  Liste, keine Überlagerungen.
- **Ausgabe der Zugangsdaten nur einmal:** ein Paket statt Einzeldownloads; erneute Ausstellung
  ist der Administration vorbehalten und wird gewarnt.

---

## 5. Testinfrastruktur

- **Eigene Browser-Suite je Plugin** (`tests/playwright/`) statt einer gemeinsamen Suite.
- **Artefakte nach Ergebnis:** bei Grün der vollständige Bericht mit Videos, bei Rot nur die
  fehlgeschlagenen Tests ohne Videos plus komprimiertes Serverlog (14 MB → 324 KB gemessen).
- **k6 Capacity Race** als verpflichtendes Gate mit eigenem Fixture; Klassifikation über den
  HTTP-Status statt über übersetzte Texte.
- **Coverage-Gate** je Plugin mit gemessenen Untergrenzen (auth 45, enrol 50, tool 40, mod 8).

---

## 6. Was am Prüfwerkzeug gelernt wurde

Mehrere CI-Fehlschläge gingen darauf zurück, dass die lokale Prüfung schwächer war als die CI.
`check_ci.sh` enthält deshalb heute fünf Prüfungen, jede aus einem konkreten Fehlschlag entstanden:

1. **Codechecker mit CI-Werkzeugversion und CI-Kontext.** Mehrere Sniffs feuern nur, wenn das
   Plugin *innerhalb* eines Moodle-Baums geprüft wird und die Zielversion bekannt ist.
2. **phpdoc mit derselben Werkzeugversion.** Die ältere lokale Fassung akzeptierte einen Docblock,
   dessen Parameterliste nicht mehr zur Funktion passte.
3. **`@package`-Konsistenz.** Eine zwischen Plugins kopierte Datei behält sonst den falschen Tag.
4. **Entfernte Dateien in `db/removed_files.txt`.** Ein Paket löscht nie; eine entfernte Datei
   überlebt im Repository und läuft dort weiter mit.
5. **Pflichtdateien des Moodle-Plugin-Verzeichnisses.** Moodle installiert auch ohne
   `db/upgrade.php`; die Einreichung wird trotzdem abgewiesen.

Weitere Erkenntnisse: `XMLDB` verträgt kein `DEFAULT=""` auf `CHAR NOT NULL`; `localhost` kann je
Client nach `::1` aufgelöst werden, wohin `php -S` nicht lauscht; ein in einem Schritt gestarteter
Hintergrundserver überlebt den Schritt nicht zuverlässig; ein übersprungener Job wird von
„Re-run failed jobs" nie erneut ausgeführt.

---

## 7. Verwendete API-Verträge

Cross-Plugin-Zugriffe laufen ausschließlich über die öffentlichen Fassaden. Direkt geprüft: keine
Komponente schreibt in eine fremde Domänentabelle.

- `auth_flexaccess\api`: `create_temporary_user`, `create_batch_account`, `persist_temporary_user`,
  `classify_user`, `queue_mail`, `queue_deferred_mail`, `queue_deferred_mail_once`,
  `deferred_mail_queued`, `rollback_batch_account`, `rollback_temporary_user`, `set_account_password`
- `enrol_flexaccess\api` / `local\enrol_service`: `admin_enrol`, `reserve_and_enrol`,
  `offers_quick_registration`, `offers_magic_login`, `offers_guest_access`
- `tool_flexaccess\local\batch`: `create`, `provision_members`, `reset_credentials`,
  `can_open_course_page`, `require_course_page`

---

## 8. Offene Punkte und Risiken

- **`MATURITY_STABLE`** ist bewusst noch nicht gesetzt. Der Reviewer hat das Code-Level-RC erteilt;
  die abschließende Produktivfreigabe steht aus.
- **Mailqueue-Adminsicht** kennt die Zustände `ackpending` und `ackfailed` noch nicht; ein
  Reparaturweg und eine Aufbewahrungsregel für terminale `ackfailed`-Zeilen fehlen.
- **jMeter** trennt Durchsatzmessung und Rate-Limit-Prüfung noch nicht; ein Teil der Last trifft
  nach kurzer Zeit die Ablehnungsseite.
- **Browser-Abdeckung** ohne produktionsnahen E-Mail-Verifikations-Ablauf.
- **`participantvisibilitydefault`** wurde umbenannt; die Einschränkung selbst (temporäre Nutzende
  werden anderen weiterhin angezeigt) ist eine dokumentierte Scope-Entscheidung für 1.0.
- **Reconciliation-Task** für seltene Überreste nach fehlgeschlagener Kompensation: optional offen.

---

## 9. Nicht lokal prüfbar

Ehrlich vermerkt: Playwright, k6 und jMeter laufen in dieser Umgebung nicht (kein Browser, keine
Lastwerkzeuge). Geprüft wurden dort ausschließlich Syntax, Plan- und XML-Gültigkeit sowie die
Fixtures durch echte Ausführung. Die Bestätigung kam jeweils aus Ihren CI-Läufen.

Die Coverage-Messung erfolgte lokal mit pcov über die vollständigen Suiten; die in der CI
gemessenen Werte können abweichen.
