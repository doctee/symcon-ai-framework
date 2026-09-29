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
Live-Kanal. Eine fehlende spezialisierte MCP-Installationsoperation bedeutet nicht,
dass Module Control keine passende aufrufbare Modulfunktion bereitstellt.
Vor einem Kanalwechsel die aktuelle Module-Control-Instanz eindeutig ermitteln
und mit den öffentlichen Funktionen `IPS_GetFunctionList()` und
`IPS_GetFunction()` die tatsächlich registrierten Funktionen und ihre Parameter
prüfen. Ihre Einordnung mit der aktuellen offiziellen Dokumentation abgleichen;
aus einer fehlenden Detailseite darf nicht auf einen internen Befehl geschlossen werden.

Der bestehende SAEF-Weg verwendet `MC_CreateModule(InstanceID, ModuleURL)` für
eine Erstinstallation und `MC_UpdateModule(InstanceID, Module)` für eine bereits
installierte Git-Bibliothek. Belege sind
[Open-Meteo, Schritt 05](../case-studies/open-meteo/05-inactive-live-preflight.md)
und [Navimow, Schritt 412](../case-studies/navimow/412-compact-map-controls-publication-and-live-rollout.md).
Historische Belege ersetzen nicht die Prüfung des aktuellen Funktionsvertrags.

Ein ausdrücklich freigegebener einmaliger Installationsaufruf über den
MCP-Codekanal ist eine administrative Mutation und muss als solche geprüft und
protokolliert werden. Er darf nicht als reine Leseprobe ausgegeben werden.
Positive Ziel-ID, Instanztyp und GUID, unveränderter Ausgangszustand, genaue
Repository-URL und Zielcommit vorher prüfen; Bibliotheksidentität, Commit,
Gültigkeit und ausschließlich erwartete Änderungen danach unabhängig zurücklesen.
Transportfehler, Ausführungsfehler und Truncation getrennt auswerten.
Keine Geräteaktionen, manuellen Serverdateitransporte oder Neustarts ableiten.

Erst wenn die erforderliche MCP-Bindung oder eine geeignete Modulfunktion
tatsächlich fehlt, den Fehler melden und einen anderen Live-Kanal ausdrücklich
freigeben lassen. Eine manuelle Installation über die Modulverwaltung bleibt möglich.

## Abgrenzung zum Windows-Deployment

Der [Standalone-Deploymentkanal](STANDALONE_MODULE_DEPLOYMENT_CHANNEL.md) beschreibt
zusätzlich konkrete zielgebundene Windows-Adapter und deren Freigaben. Er ist nicht
Voraussetzung für jede neue, regulär per Git-Modulverwaltung installierte Bibliothek.
Ein neuer Windows-Adapter darf nicht nur für das Verpacken einer Git-Bibliothek entstehen.

Offizielle Quellen:
[Modulverwaltung](https://www.symcon.de/de/llms/modules/module-control.md),
[Funktionsinformationen](https://www.symcon.de/de/llms/functions/program-information.md),
[Funktionsindex](https://www.symcon.de/de/llms/function-index.md).
