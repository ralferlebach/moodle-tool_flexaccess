# Abarbeitungsstand des externen Code-Reviews (Basis 0.9.35)

Stand: Release 0.9.44 / Build `2026082421`.

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
| String-ID-Konvention vereinheitlichen (Colon → flach) | **zurückgestellt (bewusst)** | Betrifft ~500 Strings in vier Plugins samt aller Verwendungsstellen. Das Review stuft den Punkt selbst als i18n-Hygiene ohne Laufzeitfehler ein. Ein Massen-Rename kurz vor 1.0 bringt hohes Regressionsrisiko bei null funktionalem Gewinn; sinnvoll ist er im Zuge einer AMOS-/Marketplace-Einreichung. Empfehlung: eigener Sprint nach 1.0. |
| `auth_flexaccess\api` intern in Services aufteilen | **zurückgestellt (bewusst)** | Die Fassade delegiert bereits an `account_service`, `token_service`, `mail_worker`, `policy_*`. Eine weitere Zerlegung ist reines Refactoring ohne Verhaltensänderung und damit ein schlechter Kandidat unmittelbar vor der Freigabe. Empfehlung: nach 1.0. |
| Concurrency-/Fault-Injection-/DAST-Tests | **offen** | Die fachlichen Wettlaufpfade (Kapazität, Kampagnen-Slot, Einladungs-Accept, Identitätskonversion, Rate Limit) sind durch Moodle-Locks abgesichert und in PHPUnit funktional getestet; echte Parallelität lässt sich damit aber nicht nachweisen. Erfordert eine dedizierte Lastumgebung (k6-Write-Szenarien). Empfehlung: vor dem produktiven Vollbetrieb. |

## Nicht zutreffend

* **Jest** — es existiert kein produktiver JS-Code; ein Testframework ohne Testgegenstand wäre Checklisten-Theater (Einschätzung des Reviews wird geteilt).

## Bekannte Abweichung zur ursprünglichen Anforderung

* **Teilnehmersichtbarkeit:** Umgesetzt ist „temporäre Nutzer sehen die Teilnehmerliste nicht". Die ursprüngliche Anforderung („temporäre Nutzer sind für andere nicht sichtbar") ist ohne Core-Eingriff nicht stabil umsetzbar und bleibt bewusst außerhalb des 1.0-Scopes.
