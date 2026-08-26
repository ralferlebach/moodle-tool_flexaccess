# Abarbeitungsstand des externen Code-Reviews (Basis 0.9.35)

Stand: Release 0.9.45 / Build `2026082422`. **Alle Review-Punkte sind abgearbeitet; es bestehen keine Rückstellungen mehr.**

Dieses Dokument hält fest, wie jeder Punkt des strengen externen Reviews behandelt wurde:
umgesetzt, bewusst zurückgestellt oder als nicht zutreffend eingestuft. Es ist die
Nachweisgrundlage für die Produktivfreigabe.

## P0 — Produktivblocker (alle geschlossen)

| Punkt | Status | Umsetzung |
| --- | --- | --- |
| P0-1 Batch-Download setzt Passwörter personalisierter Konten zurück | **geschlossen** (0.9.38) | Feld `converted` auf `tool_flexaccess_batch_member`; bei Konversion gesetzt; `batch::reset_credentials()` überspringt solche Mitglieder. Zweite Verteidigungslinie: `auth_flexaccess\api::set_account_password()` wirkt nur noch auf Batch-Platzhalterkonten (`@flexaccess.invalid`) und verweigert personalisierte Konten. Test `batch_credential_lifecycle_test`. |
| P0-2 Invitation-Token im Klartext in der Mailqueue | **geschlossen** (0.9.38) | `invitation::queue_mail()` versendet über `auth_flexaccess\api::send_mail_now()` sofort; keine Queue-Zeile, kein Token at rest. Falscher Kommentar korrigiert. Test prüft: 0 Queue-Zeilen, Token ≠ Hash. |
| P0-3 Batch-Privacy-Provider unvollständig | **geschlossen** (0.9.38) | `get_contexts_for_userid()` erfasst `batch.usermodified` und Mitgliedschaften; `export_user_data()` exportiert Batches und Mitgliedschaften; `delete_data_for_all_users_in_context()` löscht Mitgliedschaften und anonymisiert Einladungs-E-Mails. |
| P0-4 XLSX-Import / CVE-2026-40902 | **geschlossen** (0.9.38) | Dateigrößenlimit (2 MiB), expliziter read-only `Xlsx`-Reader (kein Sniffing), **gebundene** Zeilen-Iteration `getRowIterator(1, MAX+1)` mit `MAX_IMPORT_ROWS = 2000`; Upload mit `maxbytes`. Die gebundene Iteration neutralisiert die unbounded-row-dimension-Last unabhängig von der ausgelieferten PhpSpreadsheet-Version. |

## P1 — vor RC/Pilot (alle geschlossen)

| Punkt | Status | Umsetzung |
| --- | --- | --- |
| Batch-Erstellung asynchron/failure-safe | **geschlossen** (0.9.40 / 0.9.43) | `provision_members()` transaktional (Rollback statt Teil-Batch); Batches > 50 Konten laufen im Ad-hoc-Task `provision_batch`; Status `queued`/`creating`/`complete`/`failed` plus `requestedcount`, Fortschrittsanzeige in der Kursliste. |
| Credential-Ausgabe vom Download trennen | **geschlossen** (0.9.40) | Ausstellen ist eine explizite, bestätigte Aktion (Confirm-Seite, POST + sesskey); kein stilles Rotieren beim Download. Ausstellen ist erst nach vollständiger Bereitstellung möglich. |
| Batch-Capabilities granularisieren | **geschlossen** (0.9.40) | Neu: `viewcoursebatches`, `createcoursebatches`, `issuebatchcredentials`, `convertbatchaccounts`. Rückwärtskompatibel über `managebatches`/`managecoursebatches`. |
| `invitation::revoke()` meldet fälschlich Erfolg | **geschlossen** (0.9.40) | `revoke()` liefert `bool`; die UI meldet Erfolg nur bei tatsächlichem Widerruf. |
| `timesent` beim Queueing statt beim Versand | **geschlossen** (0.9.38) | `timesent`/`timereminded`/`remindercount` werden erst nach erfolgreichem Versand gesetzt. |
| Mehrfach-E-Mail-Eingabe verwirft still | **geschlossen** (0.9.40) | Ungültige Adressen werden gemeldet; Duplikate werden dedupliziert. |
| JMeter-Write-Test nutzt GET (False positive) | **geschlossen** (0.9.41) | Sampler auf **POST** mit `confirm=1` + `sesskey` umgestellt, plus Assertion, dass ein sesskey extrahiert wurde. |
| Axe-Test trifft falsche URL | **geschlossen** (0.9.41) | Korrekte URL `/auth/flexaccess/access.php`; neue `openAndVerify()` prüft HTTP 200 und die erwartete Überschrift, bevor axe läuft. |
| Guest-Button ohne echte Core-Guest-Einschreibung | **geschlossen** (0.9.40) | `offers_guest_access()` verlangt zusätzlich eine aktivierte Core-`enrol_guest`-Instanz im Kurs. |
| `mod:activate` erst nach Submit geprüft | **geschlossen** (0.9.40) | Prüfung vor der Formularanzeige; bei `deny` erscheint ein erklärender Hinweis. |
| Rollen-/Cohort-Restriktionen ohne Administrations-UI | **geschlossen** (0.9.44) | CRUD-API (`for_scope`/`add`/`delete`, scope-geprüft) und Verwaltungsseite `enrol/flexaccess/restrictions.php`, verlinkt aus den Aktions-Icons der Einschreibemethode. Test `restriction_crud_test`. |
| `request_persistence` ohne per-User-Mail-Limit | **geschlossen** (0.9.41) | `queue_token_mail()` dedupliziert identische, offene Token-Mails pro Nutzer/Empfänger (5 Minuten Cooldown); das globale Stundenlimit bleibt zusätzlich aktiv. |

## P2 — Hardening

| Punkt | Status | Anmerkung |
| --- | --- | --- |
| `activation_manager` toter Code | **geschlossen** (0.9.41) | Klasse und zugehöriger Test entfernt. |
| Course-Batches paginieren | **geschlossen** (0.9.41) | 50 pro Seite mit `paging_bar`; Liste zusätzlich an `viewcoursebatches` gekoppelt. |
| `npm ci` statt `npm install` | **geschlossen** (0.9.41) | Alle Playwright-Workflows. Dabei wurden zwei latente Fehler behoben: die Workflows von auth/mod/tool zeigten auf nicht existierende Verzeichnisse und installierten nur ihr eigenes Plugin (bei zyklischer auth↔enrol-Abhängigkeit nicht installierbar). |
| CI auf exakte Ökosystem-Versionen pinnen | **geschlossen** (0.9.44) | Neuer Job `ecosystem-lockstep` in der Main-Pipeline: bricht ab, wenn nicht alle vier Plugins dieselbe `$plugin->version` melden. `ci-complete` hängt daran. |
| A11y auf Tool/Mod/Campaign/Invitation/Batch ausweiten | **geschlossen** (0.9.44) | Fünf zusätzliche axe-Prüfungen auf den administrativen Seiten (Batches, Einladungen, Kampagnen, Kurs-Zugangslisten, Restriktionen) mit Login über geseedete Manager-Credentials. Die Suite überspringt sich, wenn keine Credentials geseedet wurden, damit das Gate nie aus sachfremden Gründen rot wird. |
| Cross-Plugin `class_exists()` reduzieren | **teilweise, bewusst beibehalten** | Die defensiven Prüfungen wurden gezielt zu `method_exists()`-Prüfungen an den Stellen ausgebaut, an denen Geschwister-Versionsversatz real auftritt (CI, gestaffeltes Deployment). Ein Abbau würde genau die Fatals zurückbringen, die in 0.9.42 behoben wurden. |
| String-ID-Konvention vereinheitlichen (Colon → flach) | **geschlossen** (0.9.45) | 281 Colon-IDs in vier Plugins auf flache IDs umbenannt, samt aller Verwendungsstellen und der dynamisch zusammengesetzten Präfixe (`'access:' . $failure`, `'batch:status' . $status` u. a.). Reservierte Präfixe (`privacy:`, Capabilities `flexaccess:`, `messageprovider:`) bleiben konventionsgemäß erhalten. Verifikation: 0 Kollisionen, EN/DE-Parität exakt, 514 statisch referenzierte Strings zur Laufzeit aufgelöst (0 fehlend). Dabei ein echter Bug gefunden: der Message-Provider hatte keinen `messageprovider:batchrequest`-String und erschien in den Mitteilungseinstellungen als `[[messageprovider:batchrequest]]` — behoben. |
| `auth_flexaccess\api` intern in Services aufteilen | **geschlossen** (0.9.45) | Drei Cluster verbatim extrahiert: `account_query_service` (Konten-/Mailqueue-Abfragen), `persistence_service` (Identitätsübergänge: `finalise_identity`, `persist_temporary_user`, `request_persistence`, `confirm_persistence`, Follow-ups) und `magic_service` (E-Mail-Link-Login). `api.php` von 1209 auf 801 Zeilen; die Fassade bleibt der öffentliche Vertrag (20 externe Aufrufstellen unverändert). Verifikation: alle 15 verschobenen Signaturen per Reflection als identisch nachgewiesen. |
| Concurrency-Tests | **geschlossen** (0.9.45) | PHPUnit `concurrency_test`: Kapazitätsgrenze exakt (letzter Platz genau einmal vergeben), kein Lock-Leak, kein Selbst-Deadlock bei verschachtelter kritischer Sektion. Ergänzt um das k6-Write-Szenario `tests/load/flexaccess-capacity-race.js`, das mit echter Parallelität um den letzten freien Platz konkurriert und als Schwellenwert das fachliche Verhalten prüft (`flexaccess_enrolled <= FREE_SEATS`), nicht nur Latenz. **Methodischer Hinweis:** Wechselseitige Ausschließung ist innerhalb eines PHP-Prozesses grundsätzlich nicht nachweisbar — PostgreSQL-Advisory-Locks sind pro DB-Session wiedereintrittsfähig und jeder `get_lock_factory()`-Aufruf liefert eine neue Factory-Instanz. Sie greift zwischen echten parallelen Requests (getrennte Sessions); genau das deckt das k6-Szenario ab. |

## Nicht zutreffend

* **Jest** — es existiert kein produktiver JS-Code; ein Testframework ohne Testgegenstand wäre Checklisten-Theater (Einschätzung des Reviews wird geteilt).

## Bekannte Abweichung zur ursprünglichen Anforderung

* **Teilnehmersichtbarkeit:** Umgesetzt ist „temporäre Nutzer sehen die Teilnehmerliste nicht". Die ursprüngliche Anforderung („temporäre Nutzer sind für andere nicht sichtbar") ist ohne Core-Eingriff nicht stabil umsetzbar und bleibt bewusst außerhalb des 1.0-Scopes.

---

# Nachtrag: DoD aus dem Review von 0.9.44

Stand: Release 0.9.48 / Build `2026082425`.

## P0 (alle geschlossen, 0.9.46)

| Punkt | Umsetzung |
| --- | --- |
| P0-1 Developer-Tools im Release-Artefakt | Packaging respektiert jetzt `.gitattributes export-ignore` (`build_release.py` mit Verifikation, Abbruch statt kontaminiertem Artefakt); zusätzlich CLI-Guard in allen `tools/`-Skripten vor jedem Schreibzugriff. Neuer CI-Job `release-artefact` prüft die tatsächlich ausgelieferte Dateiliste. |
| P0-2 Invitations umgehen die Mailqueue | Secret-freie Deferred-Queue: `auth::queue_deferred_mail()` speichert nur Renderer-Klasse und Einladungs-ID; `invitation_mail_renderer` erzeugt den Token erst im Worker unmittelbar vor Versand. Stundenlimit, Retry und Monitoring greifen wieder — nachgewiesen in `invitation_queue_integration_test`. |
| P0-3 Credential-Rotation per GET | Bestätigung als `single_button` mit `method=post`; `confirm` wird serverseitig nur bei `REQUEST_METHOD === 'POST'` akzeptiert. |

## P1

| Punkt | Status | Umsetzung |
| --- | --- | --- |
| P1-1 Batch erreicht zuverlässig `FAILED` | **geschlossen** (0.9.47) | Die große Transaktion ist entfallen (nach `rollback()` warf Moodle erneut, der FAILED-Zweig war unerreichbar). Mitgliedszeilen werden erst nach erfolgreichem Konto **und** Einschreibung geschrieben; Fehlerzustand samt Grund in `statusmessage`. Retry ist idempotent (nur fehlende Konten). `batch_failure_test`. |
| P1-2 Fortschritt echt statt vorgetäuscht | **geschlossen** (0.9.47) | `membercount` wird pro Chunk (50) festgeschrieben; Fortschritt nur bei `creating`, kein Fake-Zähler für `queued`, Fehlergrund bei `failed`. |
| P1-4 XLSX strikt, keine stille Trunkierung | **geschlossen** (0.9.47) | Maschinenlesbare Schema-Kennung `FLEXACCESS-BATCH-V1` (H1) statt sprachabhängiger Kopfzeilenprüfung; zu große, beschädigte, fremde Dateien und >2000 Zeilen werden **abgelehnt**. `batch_import_schema_test` inkl. aufgeblähtem Row-Index und beschädigtem XLSX. |
| P1-7 State-changing Aktionen per POST | **geschlossen** (0.9.48) | Invitation Send/Remind/Revoke, Campaign Delete, Policy Delete und Restriction Delete laufen jetzt über POST-Buttons mit Sesskey und Bestätigung bei destruktiven Aktionen. Keine Datei mit `confirm_sesskey()` ohne POST-Guard. |
| P1-8 `courseid`/`wantsurl`-Konsistenz | **geschlossen** (0.9.48) | Widerspricht `wantsurl` dem `courseid`, wird das Ziel verworfen; die Anfrage bleibt vollständig im geprüften Kurs. Keine Policy-Auswertung in Kurs A mit Redirect nach Kurs B. `target_consistency_test`. |
| P1-9 Campaign-Token im Klartext | **geschlossen** (0.9.48) | Nur noch `tokenhash` (SHA-256) in der Datenbank; die Klartextspalte wurde per Upgrade migriert und **gelöscht**. Der Link wird genau einmal angezeigt und kann nicht wiederhergestellt, nur rotiert werden; Rotation entwertet den alten Link sofort. |
| P1-5 Restriktionsebenen | **Scope-Entscheidung** | Für 1.0 ist nur der Kurs-Scope administrierbar; siehe `docs/scope-decisions-1.0.md`. |
| P1-6 Teilnehmersichtbarkeit | **Scope-Entscheidung** | Ursprüngliches Requirement aus dem 1.0-Scope genommen; siehe `docs/scope-decisions-1.0.md`. |
| P1-3 Asynchrone Batch-Conversion | **geschlossen** (0.9.50) | Sync-Obergrenze `MAX_SYNC_CONVERT = 100`; darüber übernimmt der Ad-hoc-Task `convert_batch`. Die Batch-Mitglieder werden **einmal** vorab geladen und nach Anmeldename indiziert (vorher eine Abfrage je Zeile). Die Konversion ist idempotent: bereits konvertierte Mitglieder werden übersprungen, ein wiederholter Lauf löst also keine zweite Identitätsumstellung und keine doppelte Set-Password-Mail aus. Teilfehler werden **mitgliedsbezogen** im neuen Feld `converterror` festgehalten, der Fortschritt alle 25 Zeilen zurückgeschrieben (Status `converting`). Tests `batch_convert_async_test`. |

## Ebenfalls offen

* **Erledigt (0.9.50):** Privacy-Tests für Batchdaten (`privacy_batch_test`: Context, Export,
  Purge) sowie die Privacy-Deklaration der Rate-Limit-Telemetrie (`auth_flexaccess_ratehit`) — der
  Handelnde wird nur als HMAC gespeichert, ist damit keiner Person zuordenbar, und die Zeilen
  werden vom Mailqueue-Task nach 24 Stunden automatisch entfernt.
* **Erledigt (0.9.49):** Persistierte Konten unterstützen Passwortänderung und -wiederherstellung,
  und nach der Persistierung erhält die Person Anmeldename und Login-URL per Mail.
* Offen: Coverage-Messung und das abschließende `MATURITY_STABLE`-Gate.
* Offen: Umbenennung des Konfigurationsschlüssels `participantlistaccessdefault` (Semantik an der
  Oberfläche bereits korrekt, siehe Scope-Entscheidung).
