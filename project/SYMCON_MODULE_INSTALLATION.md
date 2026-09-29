# Eigenständige Symcon-Module installieren

Diese Anleitung verbindet die bestehenden SAEF-Schritte für Dateisatz, Veröffentlichung
und Installation. Sie ersetzt weder den Publikationsvertrag noch die Freigabe eines
konkreten Live-Ziels.

## 1. Bibliothek reproduzierbar vorbereiten

Kanonische Quellen liegen in der jeweiligen Case Study. Ein explizites Manifest unter
`deployments/symcon/*-module.fileset.json` ordnet Quellen und unveränderte benötigte
SAEF-Helfer zu. `tools/build-symcon-module-fileset.php <manifest>` erzeugt daraus
die Distribution einschließlich Quellen- und Hashnachweisen. Mit `--check` wird
die vorhandene Distribution geprüft. Tests müssen gegen das tatsächliche Paket laufen.
Private IDs, Bindings, Messhistorien und trainierte installationsbezogene Modelle
gehören nicht in die Bibliothek.

## 2. Eigenständige Git-Bibliothek veröffentlichen

Der [Publikationsworkflow](../deployments/symcon/publication/README.md) ist maßgeblich.
`tools/publish-symcon-module.php --contract=<vertrag> --check` prüft lokal;
`--prepare=<neues-verzeichnis>` schreibt den bytegeprüften Kandidaten lokal.
Ein ZIP darf diesen Kandidaten verpacken und braucht keine zweite Quellenliste.

Neue Bibliotheken benötigen ein separat angelegtes Zielrepository mit Basis-Commit;
der Publisher erzeugt dieses Repository nicht. Repositoryname und Sichtbarkeit
müssen vor einer Veröffentlichung feststehen. Neue Verträge verwenden das PR-Verfahren:
`--apply` veröffentlicht den konkret freigegebenen Kandidaten mit erwarteten Hashes
und Basis-Commit; `--integrate` integriert den geprüften PR. Keine dieser Aktionen
installiert automatisch etwas auf einem Symcon-Server.

## 3. Über Symcons Modulverwaltung installieren

Nach Veröffentlichung die Git-URL in **Modules → +** eintragen. Danach die Instanz
des gewünschten Moduls erstellen, zunächst deaktiviert konfigurieren und Quellen,
Eigentümer gemeinsam geladener Helfer sowie Voraussetzungen am Live-Ziel prüfen.
Private Konfiguration separat übertragen und zurücklesen. Erst dann die konkret
autorisierte Funktion aktivieren. Rücknahme, Sicherung und Nachprüfung vorher festlegen.

Für Agenten bleibt [Symcon MCP](SYMCON_MCP_SCRIPT_READBACK.md) der verbindliche
Live-Kanal. Fehlt dort eine für den benötigten Installationsschritt geeignete
dokumentierte Operation, darf ein Agent nicht eigenmächtig auf UI-Automation,
Serverdateizugriff oder undokumentierte interne Befehle ausweichen. Eine manuelle
Installation durch den Nutzer ist möglich; anschließend kann MCP die Instanz prüfen.
Historische `MC_*`-Aufrufe in Case Studies sind allein kein aktueller öffentlicher
API-Vertrag.

## Abgrenzung zum Windows-Deployment

Der [Standalone-Deploymentkanal](STANDALONE_MODULE_DEPLOYMENT_CHANNEL.md) beschreibt
zusätzlich konkrete zielgebundene Windows-Adapter und deren Freigaben. Er ist nicht
Voraussetzung für jede neue, regulär per Git-Modulverwaltung installierte Bibliothek.
Ein neuer Windows-Adapter darf nicht nur für das Verpacken einer Git-Bibliothek entstehen.

Offizielle Quellen:
[Modulverwaltung](https://www.symcon.de/de/llms/modules/module-control.md),
[Funktionsindex](https://www.symcon.de/de/llms/function-index.md).
