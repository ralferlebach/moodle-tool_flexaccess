# Produktentscheidungen zum 1.0-Scope

Diese Entscheidungen sind Teil der Definition of Done aus dem Review von 0.9.44. Die DoD lässt für
beide Punkte ausdrücklich die Alternative „ausdrücklich aus 1.0-Scope entfernen und Lasten-/
Pflichtenheft anpassen" zu. Genau das wird hier getan — nachvollziehbar und mit Begründung, damit
kein Statusdokument später eine Fähigkeit behauptet, die es nicht gibt.

## P1-6 — Teilnehmersichtbarkeit

**Entscheidung: Das ursprüngliche Requirement wird aus dem 1.0-Scope genommen.**

Ursprünglich gefordert war: *ein temporärer Nutzer soll für andere Teilnehmende optional nicht in
der Teilnehmerliste erscheinen.* Umgesetzt und ausgeliefert ist etwas anderes: *ein temporärer
Nutzer darf die Teilnehmerliste nicht öffnen.*

Begründung: Moodle bietet keinen stabilen öffentlichen Erweiterungspunkt, um einzelne Nutzer aus der
Teilnehmerliste anderer auszublenden. Erreichbar wäre das nur über Eingriffe in Core-Abfragen. Ein
solcher Core-Hack ist für ein verteiltes Plugin nicht vertretbar: Er bricht bei jedem Core-Update,
ist nicht sicher testbar und würde die Plugin-Freigabe gefährden.

Konsequenzen, die bereits umgesetzt sind:

* Die Sprachstrings benennen ausschließlich die tatsächliche Funktion („Zugriff auf die
  Teilnehmerliste"), nicht die ursprüngliche.
* Der Hilfetext benennt die Einschränkung ausdrücklich: Temporäre Nutzer werden **nicht** vor
  anderen verborgen.
* `docs/traceability.md` führt das ursprüngliche Requirement als **nicht umgesetzt (Scope 1.0)**,
  nicht als erfüllt.

Offen und bewusst zurückgestellt: die Umbenennung des Konfigurationsschlüssels von
`participantlistaccessdefault` nach `participantlistaccessdefault`. Die Semantik ist an der
Oberfläche und in der Dokumentation bereits korrekt; die Schlüsselumbenennung erfordert eine
Datenmigration über den gesamten Policy-Stack (System-, Kategorie- und Instanzebene) und wird als
rein kosmetische Änderung nicht unmittelbar vor der Freigabe durchgeführt.

## P1-5 — Restriktionsebenen

**Entscheidung: Für 1.0 ist ausschließlich der Kurs-Scope administrierbar.**

Die Auswertungs-Engine unterstützt weiterhin `system`, `category` und `course`; eine auf
System- oder Kategorieebene per API oder Upgrade angelegte Regel wirkt also korrekt und wird auf der
Kursseite als vererbte Einschränkung berücksichtigt. Eine Administrationsoberfläche existiert für
1.0 aber nur für den Kurs-Scope.

Begründung: Die Kursebene deckt den Anwendungsfall der Zielinstallation vollständig ab. Eine
System- und Kategorieverwaltung erfordert zusätzlich kontextrichtige Capability-Prüfungen
(System- bzw. Kategoriekontext) und skalierbare Auswahl-Widgets für Rollen und Cohorts
(Autocomplete statt vollständigem Laden aller Cohorts, was auf großen Sites nicht tragfähig ist).
Das ist ein eigenständiges Feature und kein Nachziehen einer bestehenden Oberfläche.

Konsequenzen, die bereits umgesetzt sind:

* Die Kursoberfläche prüft `enrol/flexaccess:config` im **Kurskontext**.
* `restriction_service::delete()` ist scope-geprüft: Eine Kursadministration kann eine System- oder
  Kategorieregel nicht über ihre ID löschen.
* Das Löschen erfolgt per POST mit Sesskey.
* Der Einleitungstext der Seite weist darauf hin, dass Regeln auf Site- und Kursbereichsebene
  ebenfalls wirken.

Für 1.1 vorgesehen: System- und Kategorie-CRUD mit kontextrichtigen Capabilities und
Autocomplete-Selektoren für Rollen und Cohorts.
