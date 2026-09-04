# Abschließende Sicherheitsprüfung der OwnTracks-Positionskarte

Datum: 2026-09-03
Status: Prüfung abgeschlossen; drei bestätigte Befunde mittlerer Priorität offen.
Keine uneingeschränkte Sicherheitsfreigabe.

## Ergebnis und Umfang

Im geprüften Umfang wurde kein kritischer Authentifizierungs-Bypass und kein
beliebig nutzbarer externer HTTP-Proxy nachgewiesen. Das ist keine Garantie gegen
unbekannte Schwachstellen und kein neuer Connect-Penetrationstest.

Die Freigabe umfasste Quellcodeprüfung, lokale synthetische Prüfungen und einen
ausschließlich lesenden Live-Abgleich über Symcon MCP. Es wurden keine
Produktionsdateien, Moduleinstellungen, Archive, Logging-Einstellungen, Hooks,
Caches oder Visualisierungen verändert. Kein Browseraufruf, Provider-Kontakt,
Paketbau, Commit oder Veröffentlichung fand in dieser Prüfung statt.

Die drei Befunde betreffen Verfügbarkeit und die Verlässlichkeit von
Ressourcengrenzen. Sie sollten vor dem formalen Sicherheitsabschluss korrigiert
und erneut geprüft werden. Sie belegen weder einen Datenabfluss noch die Ursache
früherer Kartenlücken auf dem Live-System.

## Prüfgegenstand und Reproduzierbarkeit

Die Prüfung liegt in einem eigenen Worktree auf dem frisch abgeglichenen
`origin/main`-Stand `f5d57a0cd80e51c55bc3e7706a9c85fa0dd67786`.
Die noch nicht eingecheckte OwnTracks-Implementierung liegt in einem getrennten
Workstream. Dieser wurde nur gelesen, nicht bereinigt oder als neuer Build
verwendet. Maßgeblich ist daher die exakte Paketidentität, nicht allein Git HEAD.

Geprüfte Paketidentität:
`82c148a5f1e7789db1850641f5ea45f44322f8f4e8b2c84ea354b53982d2de9c`.

Alle 34 Nutzdateien stimmen zwischen Quellzuordnung, lokalem Distributionsteil
und dem entsprechenden Live-Paket überein. Mit den zwei Paketmanifesten umfasst
das Paket 36 Dateien. Das unmittelbar vorherige Rollback-Paket wurde gefunden;
seine 34 Nutzdateien stimmen mit seinem eigenen Manifest überein. Der MCP-Abgleich
ersetzt keinen Nachweis über einen bereits geladenen PHP-Opcode-Cache.

Die Live-Instanz meldet Status 102. Ihr Konfigurationsfingerabdruck stimmt mit
dem freigegebenen Aktivierungsstand überein. Insbesondere bleiben kurzlebige
Header-Capabilities, feste OSM-Standard-Zieladresse, Zoomgrenzen sowie Provider-
und Gateway-Budgets konfiguriert. Die geprüften Cache-Zustandsdateien lagen unter
ihren vorgesehenen Größenlimits; ihre unmittelbaren Verzeichnisse waren keine
Symlinks. Das ist kein Windows-ACL-Nachweis.

Alle Live-Proben hatten getrennt geprüfte, leere `transportError`- und
`executionError`-Felder und `truncated=false`. Es wurden weder Quellpositionen
noch historische Bewegungsdaten angefordert. Private Adressen, Origins,
ObjectIDs, Trackerkennungen, Dateipfade und Tile-Indizes sind hier nicht enthalten.

## SEC-01 — Temporäre Dateien werden vor der gemeinsamen Sperre gelöscht (P2)

Betroffen:

- `candidate/OwnTracksTileMissStateStore.php:110`, insbesondere `ensureDirectory`
  vor `flock` sowie die Bereinigung ab Zeile 151;
- `candidate/OwnTracksProviderTileCache.php:415`, insbesondere der Aufruf von
  `removeTemporaryFiles` aus `ensureDirectory` ab Zeile 443.

Ein Schreiber hält die gemeinsame Sperre, schreibt seine temporäre Datei und
benennt sie anschließend atomar um. Eine zweite Anfrage bereinigt jedoch bereits
vor dem Warten auf dieselbe Sperre alle passenden temporären Dateien. Sie kann
dabei die Datei des ersten Schreibers löschen. Dessen anschließendes Umbenennen
kann fehlschlagen. Bei Systemen mit anderen Dateifreigaberegeln kann stattdessen
die Bereinigung fehlschlagen.

Lokaler Nachweis: Ein Prozess hielt die echte Sperrdatei und stellte den Zustand
zwischen erfolgreichem temporärem Schreiben und Umbenennen her. Ein zweiter
Prozess rief die unveränderte öffentliche Speicher-API auf. Bei beiden Speichern
wurde die temporäre Datei entfernt, während die erste Sperre noch gehalten wurde;
der zweite Prozess wartete danach auf die Sperre. Nach deren Freigabe schloss er
erfolgreich ab. Der Nachweis lief unter macOS, nicht als Fehlereinjektion live
unter Windows.

Auswirkung: sporadisch fehlgeschlagene Zustands- oder Cache-Schreibvorgänge und
damit Tile-Ausfälle. Eine externe Authentifizierungsumgehung ist dafür nicht
nachgewiesen; normales paralleles Arbeiten reicht als mögliche Voraussetzung.

Empfehlung: Verzeichnisprüfung von Bereinigung trennen und ausschließlich unter
derselben gehaltenen Sperre bereinigen. Der vorhandene `OwnTracksTileFileCache`
führt seine entsprechende Orphan-Bereinigung bereits innerhalb der Sperre aus.
Dieses lokale Muster wiederverwenden; keinen neuen öffentlichen SAEF-Helper
einführen. Den beschriebenen Interleaving-Test als Regressionstest aufnehmen.

## SEC-02 — Beschädigtes Auswahlbudget wird als unverbraucht behandelt (P2)

Betroffen: `candidate/OwnTracksTileMissStateStore.php:163–188`.

Ungültige oder zu große gespeicherte JSON-Daten führen zum Löschen der Datei und
zur Rückgabe eines leeren Stores. Dabei gehen nicht nur wiederherstellbare
Cache-Informationen, sondern auch bereits verbrauchte Request-/Bytebudgets und
negative Cache-Einträge verloren. Das widerspricht dem Fail-closed-Vertrag für
Zulassungszustände in EK-008.

Lokaler Nachweis: Ein gültiger synthetischer Zustand mit 48 verbrauchten Requests
und vollständig verbrauchtem Bytebudget wurde geschrieben und erneut gelesen.
Nach gezielter Beschädigung nur dieser Testdatei erhielt die nächste Operation
ohne Ablehnung einen leeren Zustand.

Auswirkung: Aufweichung der Auswahl-/Viewport-Grenzen nach Datenkorruption oder
lokaler Manipulation. Eine Möglichkeit, die Datei über einen nicht
authentifizierten HTTP-Aufruf zu beschädigen, wurde nicht gefunden. Das separate,
instanzweite Provider-Minutenlimit bleibt bestehen; der Befund bedeutet keinen
unbegrenzten Providerzugriff.

Empfehlung: Sicherheitsrelevante Zähler bei ungültigem Zustand nicht automatisch
zurücksetzen. Betroffene dynamische Anfragen geschlossen ablehnen und eine
explizite, nachvollziehbare Wiederherstellung vorsehen. Eine Cache-Reparatur darf
keine verbrauchten Zulassungsbudgets stillschweigend erneuern. Größenbeschränkung
auch vor beziehungsweise während des Lesens durchsetzen, nicht erst danach.

## SEC-03 — Timeout deckt DNS und Sperrwartezeit nicht ab (P2)

Betroffen:

- `candidate/OwnTracksPinnedHttpsTileTransport.php:69–95` und `resolveDns` ab
  Zeile 371;
- blockierende `flock(LOCK_EX)`-Aufrufe in den Speicher- und Budgetklassen;
- `candidate/OwnTracksProviderTileRuntime.php:76`, dessen `withSelection`-Callback
  auch den Providerabruf umfasst.

Die Zeitmessung beginnt erst nach der synchronen DNS-Auflösung. Die Dateisperren
haben ebenfalls keine eigene Deadline. Die Miss-State-Sperre wird während des
Providerabrufs gehalten. Damit ist der konfigurierte HTTP-Timeout keine belastbare
Obergrenze für die gesamte Anfrage; die Domänenarbeit kann länger als die
30-sekündige Zulassungs-Lease laufen beziehungsweise darauf warten.

Lokaler Nachweis ohne Netzwerk: Ein synthetischer DNS-Resolver benötigte etwa
400 ms, bei konfigurierten 250 ms. Die gesamte Operation dauerte 410 ms, wurde
trotzdem akzeptiert und meldete nur 1 ms Transportlaufzeit. Executor und Resolver
waren vollständig lokale Testfunktionen.

Auswirkung: Bei DNS-Störungen oder Sperrstau können Worker länger gebunden bleiben
als angenommen. Die Lease-/Parallelitätsgrenzen sind dadurch nicht für jede
Störung nachgewiesen. Eine reale Überlastung wurde nicht ausgelöst.

Empfehlung: Eine gemeinsame monotone Deadline für Zulassung, Sperren, DNS und
Transfer verwenden; Sperren begrenzt versuchen und bei Zeitüberschreitung
geschlossen abbrechen. Netzwerkoperationen nicht unter einer globalen
Zustandssperre durchführen. Stattdessen Budget unter Sperre reservieren, außerhalb
der Sperre abrufen und Ergebnis unter Sperre abschließen. Lease und Zeitbasis
müssen dazu konsistent bleiben. DNS muss tatsächlich begrenzbar sein; eine
nachträgliche Zeitmessung allein verhindert keine blockierte Auflösung.

## Bestätigte Schutzmaßnahmen

Die geprüften Codepfade und die erneut ausgeführten lokalen Tests bestätigen:

- signierte, kurzlebige, zweck-/instanzgebundene Capabilities;
- exakte Pfad-, Methoden-, Query-, Header- und Ressourcenprüfung;
- keine Authentifizierung allein anhand von Referer, Origin oder Client-IP;
- separate Begrenzung der Capability-Ausgabe sowie persistente Gateway- und
  Provider-Zulassungszustände;
- feste Provideradresse, öffentliches gepinntes DNS-Ziel, TLS-Prüfung, keine
  Redirects und begrenzte Antwortgrößen;
- lokale Tile-Autorität mit Pfad-/Dateityp-/PNG-Prüfungen;
- browserseitige Tokens nur im Speicher und Header, nicht in URLs; Same-Origin-
  Abruf ohne Referer und ohne Redirect-Freigabe;
- Textausgabe über sichere DOM-Operationen und JSON-HTML-Escaping des Bootstraps;
- begrenzte Archivabfragen und keine Änderung der Quellarchive durch den Adapter.

Ein einfacher zusätzlicher Textscan fand keine privaten Connect-/Routerhosts,
offensichtlichen privaten IPv4-Adressen oder Private-Key-Blöcke in den geprüften
Kandidaten-, Renderer- und Berichtdateien. Das ist kein vollständiger Secret-Scan.

Erneut erfolgreich ausgeführte Offline-Testgruppen:
`tile-access-policy`, `tile-gateway`, `tile-webhook-adapter`,
`tile-directory-authority`, `provider-tile-cache`, `provider-tile-runtime`,
`osm-tile-transport`, `runtime-module`, `symcon-archive-adapter`.

Die bestehenden Tests bestanden, obwohl die drei zusätzlichen Proben die obigen
Fehler nachwiesen. Ein grüner bisheriger Testlauf ist deshalb kein Abschlussbeleg.
Die lokalen Proben und ihre ausschließlich synthetischen Testdateien verbleiben
zur Reproduktion im privaten Prüfbereich des Audit-Worktrees.

## Offene Nachweise und Klarstellungen

1. **Windows-Dateirechte:** Effektive NTFS-DACLs, Dienstkonto, Vererbungen und
   übergeordnete Verzeichnisse wurden nicht geprüft. `chmod` und `is_link` allein
   beweisen keinen Schutz vor anderen lokalen Windows-Konten. Hier ist ein
   gezielter read-only Rechte-Nachweis nötig, kein vorsorgliches Umstellen der ACLs.
2. **Connect-Transport:** Die Konfiguration enthält die bisherigen positiven
   Forwarding-/Header-Kanonisierungsnachweise. Sie wurden in dieser Prüfung nicht
   durch neue HTTP-Angriffsvarianten bestätigt. Dafür wäre ein separates Gate mit
   synthetischen Anfragen nötig; keine privaten XYZ-Indizes erforderlich.
3. **Budgetbegriff:** Der Zähler mit dem Namen `maximumRequestsPerSelection`
   gehört effektiv zu einem Viewport-Schlüssel, einschließlich Generation, Zoom
   und Bounds. Ein neuer Viewport erhält einen neuen Teilzähler; das globale
   Provider-Minutenlimit bleibt stabil. Frühere Dokumente nennen teilweise
   „selection“, teilweise „viewport“. Vertrag und Benennung müssen eindeutig sein;
   eine pauschale Grenze je ausgewähltem Tag darf daraus nicht abgeleitet werden.
4. **Abhängigkeiten:** OpenLayers 10.10.0 und Build-Werkzeug esbuild 0.28.2 sind
   festgelegt. Ein aktueller Advisory-/CVE-Abgleich und eine vollständige
   transitive Supply-Chain-Prüfung waren nicht Teil der erfolgten Offline-Tests.
5. **Prüftiefe:** Kein Lasttest, keine Live-Dateikorruption, kein Token-Diebstahl-
   Test, kein Betriebssystem-Pentest und kein erneuter vollständiger Hashlauf
   über sämtliche statischen Karten-PNGs. Die Browser-Funktionsabnahme aus dem
   vorherigen Gate wurde nicht wiederholt.

## Nächstes Gate

Vor dem formalen Abschluss: SEC-01 bis SEC-03 repositoryseitig beheben, gezielte
Parallelitäts-/Korruptions-/Deadline-Regressionen ergänzen und alle bisherigen
Sicherheitstests wiederholen. Den Budgetvertrag präzisieren und die offenen
Rechte-/Transport-/Abhängigkeitsnachweise schließen oder ausdrücklich als
Restrestrisiko akzeptieren.

Live-Korrekturen erfordern ein neues Gate mit genauer Änderungsliste,
frischem Paket-/Konfigurationsabgleich, erhaltener Rollback-Grenze und unabhängigem
Postflight. Diese Sicherheitsprüfung erteilt keine solche Änderungsfreigabe.
