# Storage Heater Forecast – Version 0.2.0

Beobachtendes IP-Symcon-Modul für die elektrische Nachtladung eines Nachtspeicherofens.
Es liest Messwerte und Schaltzustände. Es besitzt keine Geräteaktionen, keine Relais-
oder Regleränderungen und schreibt keine fremden Variablen oder Archive.

## Funktionsumfang

- Prognose einmal je lokalem Datum zwischen **21:45 und 22:00 Europe/Berlin** für
  das Ladefenster **22:00–07:00**. Nach 22 Uhr wird keine Prognose nachgetragen.
- Zeitumstellung: echte 8/9/10 Stunden; Berechnung anhand Unix-Zeitstempeln.
- Vortrainierte standardisierte Ridge-Regression; Training und Modellimport bleiben
  nachvollziehbare Offline-Schritte. Keine unbemerkte Selbstkalibrierung.
- Verwendet stündliche Open-Meteo-Temperaturvorhersagen, deren Punkte und Abrufzeit
  unveränderlich mit der Prognose gespeichert werden. Vollständige Abdeckung bis
  einschließlich 07:00 erforderlich; lineare Integration der Stundenpunkte.
- Gemessene Raumtemperaturen und Betriebshistorie enden um 21:00. Modelltraining
  verwendet genau diesen Datenstand. Aktuelle Freigabe/Absenkung werden zur Ausgabe
  erfasst. Die Prognose setzt die Fortführung dieser Zustände voraus.
- Ab 08:00: tatsächliche Zählerenergie, Stundenprofil der Gesamtleistung,
  Ventilatorlaufzeit und Zustandsänderungen; Fehler = Prognose minus Messung.
- Rolling Journal für **400 Nächte**, Export über `SHF_GetJournalJson(instanceId)`.
  Jede Prognose enthält Modell, Quellenzuordnung und Eingangsdaten. Messungen werden
  einmal ergänzt; Prognosen niemals anhand des Ergebnisses überschrieben.
- Fehlende Messungen werden höchstens sieben Tage nachgefragt, höchstens zwei
  Nächte pro Lauf. Neustart rekonstruiert zusätzlich die vorherige Nacht, keine
  erfundenen Prognosen oder unbeschränkte historische Nachberechnung.
- Diagnose über SAEF Registry, Statistics, ErrorRingBuffer, ConfigurationHash.
  Native Modulregistrierung übernimmt die Objektlebenszyklen; die unveränderten
  SAEF-Helfer übernehmen die Diagnose-Datenverträge. Das Journal ist fachlicher
  Zustand, kein alternativer generischer Metadatenspeicher.

## Voraussetzungen und Einrichtung

Zielplattform: modernes IP-Symcon mit PHP ab 8.2; OpenMeteoWeather mit `GetHourlyForecastJson` und `LastSuccess`;
separater kWh-Zähler mit Zähleraggregation, kW-Gesamtleistung mit Standardaggregation,
archivierte Temperaturen und boolesche Relaiszustände. Keine phasenweise Messung nötig.
Die erste Version ist auf die oben genannten Nachtzeiten und Zeitzone beschränkt.
Der gepinnte offizielle Bibliotheks-JSON-Schemawert reicht nur bis Version 6.2;
deshalb setzt die Metadatei zusätzlich ein Kernel-Mindestdatum 01.09.2025 und die
Laufzeit prüft PHP >= 8.2. Eine ältere Plattform-Kompatibilität wird nicht behauptet.

Das Modul startet **deaktiviert**. `BindingsJSON` enthält explizite positive IDs für
`archive`, `weather`, `energy`, `power`, `outside`, `gallery`, `sofa`, `fan`, `enabled`,
`reduced`; optional `presence` und `auxiliary`. Unbekannte Schlüssel, fehlende IDs,
Root-ID, falsche Variablentypen und eigene Ausgangsvariablen als Quellen werden abgewiesen.
Es gibt keine vorgegebenen privaten IDs und keinen Root-Platzhalter.

`HistoryStart`: erster verlässlicher Tag in YYYY-MM-DD. Zustandsarchive benötigen
einen Anfangswert vor dem ausgewerteten Zeitfenster. `ModelJSON` nimmt das geprüfte
Trainingsartefakt auf. Die privaten Bindings und das trainierte Modell gehören nicht
in das öffentliche Modulpaket.

Die Altersgrenzen sind zunächst 180 Minuten für Sensoraktualisierungen und Wetterabruf.
`VariableUpdated` beweist keine neue Funkmessung, wenn ein vorgelagerter Adapter alte
Werte erneut schreibt. Unveränderte nur bei Änderung gemeldete Sensoren können umgekehrt
als zu alt erkannt werden. Die Grenze ist konservativ konfigurierbar; sie ersetzt keinen
Geräte-Heartbeat. Schaltzustände werden nicht wegen langer Konstanz als veraltet behandelt.
Auch die nur bei Änderungen archivierte Gesamtleistung dient nicht als Frischesignal;
hierfür wird der direkt aktualisierte Energiezähler geprüft. So verhindert eine lange
Nullleistung im Sommer nicht die erste Prognose nach erneuter Heizungsfreigabe.

`ForecastValid` muss gemeinsam mit `ForecastKWh` und `ForecastDate` verwendet werden.
Bei ungültiger Prognose bleibt der letzte Zahlenwert als historische Anzeige stehen.
`ScoredNights = 0` bedeutet: der angezeigte mittlere Fehler ist noch nicht bewertbar.
Die Fehlerstatistik umfasst die gespeicherten abgeschlossenen Nächte, einschließlich
der separat markierten Änderungen von Absenkung oder Freigabe nach Ausgabe.

## Grenzen

Kaminstarts werden nachträglich im Instanzformular mit Datum und Uhrzeit erfasst.
Eine Endzeit, Brenndauer oder Wärmemenge ist nicht erforderlich und wird auch nicht
aus einer Nennleistung geschätzt. Erfassungs- und gegebenenfalls Stornozeitpunkt
bleiben getrennt erhalten. Nachträge verändern weder alte Prognosen noch deren
gespeicherten Wissensstand. Das funktioniert auch bei deaktivierter Beobachtung.

Die Fehlerauswertung gruppiert abgeschlossene Nächte nach **gemeldeten** Starts
im Zeitraum 24 Stunden vor Ladebeginn bis Ladeende. Das ist ein Vergleichsfenster,
keine Brenndauer und kein Nachweis einer Kaminwirkung. Ohne Eintrag bleibt die
tatsächliche Nutzung unbekannt. Das bestehende Modell wird dadurch nicht automatisch
neu trainiert. Die Zusatzwärme bleibt bis zur ausreichenden Datengrundlage ein
unsicherer Einfluss. Starts sind 400 Tage rückwirkend erfassbar; höchstens 1000
Einträge einschließlich Stornos bleiben erhalten, ohne stilles Überschreiben.

Die Vorhersage beschreibt erwartete elektrische Ladung unter dem bisherigen Betrieb,
nicht den optimalen Heizbedarf. Kein Speicherfüllstand, keine Temperatur-/Ankunfts-
garantie, keine automatische Aufwärmplanung, keine Regleroptimierung in dieser Version.
Anwesenheit wird optional protokolliert; ihre indirekten Auswirkungen sind über
Absenkung, Raumtemperatur und Ventilatorhistorie erfasst. Ein Aufenthaltsplan ist noch
kein eigenständiges Modellmerkmal. Das Stundenprofil erkennt keine minutengenauen
Phasenunterbrechungen. Gegenprüfung Energie/Leistung ist keine unabhängige Kalibrierung.
Ein Zählerwechsel oder Reset innerhalb einer Stunde kann wegen der positiven
Zählerdelta-Aggregation verborgen bleiben; die Leistungsgegenprüfung markiert größere
Abweichungen. Vollständige Aggregate beweisen keine vollständige Sensorfrische.

Die Modellvalidierung verwendet gemessene spätere Außentemperaturen. Die Qualität
mit tatsächlichen Wettervorhersagen wird erst anhand des neuen Journals messbar.
Außerhalb der Trainingsbereiche wird eine Warnung ausgegeben, kein künstlich präzises
Konfidenzband. Mehrtages- und Raumtemperaturmodelle werden separat entwickelt.

## Entwicklung und reproduzierbare Prüfung

`tools/train.py` nimmt einen privaten Stundenexport und zwei private Zustandsarchive
entgegen. Es benötigt numpy/pandas, trainiert die feste erste Modellform und schreibt
ein Modell plus chronologische Einzelprüfungen. Diese Dateien bleiben privat.

Der vorhandene SAEF-Dateisatz-Builder erzeugt die Distribution:

```console
php tools/build-symcon-module-fileset.php deployments/symcon/storage-heater-module.fileset.json
sh case-studies/storage-heater/tools/check.sh
```

`tools/package.py --output <private-pfad.zip>` verpackt exakt den mit SAEFs
`publish-symcon-module.php --prepare` bytegeprüften Kandidaten aus dem
Publikationsvertrag. Es gibt keine zweite Dateizuordnung im ZIP-Werkzeug.
Kein Netzwerkzugriff, keine Installation oder Veröffentlichung.

Die Tests laufen gegen das entpackte Paket: `php tests/fireplace.php` und
`php tests/runtime.php <entpacktes-paket>`. Das Paket nutzt unveränderte Helfer;
kein bestehender globaler Helfer wird aktualisiert. Vor Live-Aktivierung muss dessen
bereits geladener Eigentümer gegen diese Diagnoseverträge geprüft werden.

## Installation, Aktivierung und Rücknahme

Der Installationsweg ist SAEFs bestehende Veröffentlichung einer eigenständigen
Git-Modulbibliothek und anschließend Symcons Modulverwaltung. Der Vertrag
`deployments/symcon/storage-heater-publication.json` bereitet das Ziel
`doctee/saef-storage-heater` mit PR-Verfahren vor; er erzeugt kein GitHub-Repository.
Eine neue Zielbibliothek braucht zunächst ein bestätigtes Repository mit Basis-Commit.
Der bestehende Publisher prüft den Kandidaten, veröffentlicht mit expliziten Hashes
einen PR und integriert ihn in einem getrennten Schritt. Danach wird die Git-URL
in Symcons Modulverwaltung unter **Modules → +** hinzugefügt und die deaktivierte
Instanz **Nachtspeicher Prognose** angelegt. Bei einer freigegebenen Installation
über MCP kann die zuvor verifizierte Module-Control-Funktion `MC_CreateModule`
denselben Bibliotheksschritt übernehmen; danach die Instanz mit dem bestehenden
`SAEF_EnsureInstance`-Helfer anlegen.

Siehe [SAEF-Modulinstallation](../../project/SYMCON_MODULE_INSTALLATION.md) und
[Publikationsvertrag und Freigaben](../../deployments/symcon/publication/README.md).
Das ZIP ist ein reproduzierbares Prüfartefakt. Ein eigener Windows-Deploymentkanal
ist für die normale Git-Bibliothek nicht erforderlich.

Vor Aktivierung: Zielbibliothek und Elternkategorie, Helfer-Eigentümer, Quellen,
Archivtypen und Aktualität prüfen; private Vorher-Sicherung erstellen; zunächst
deaktivierte Instanz anlegen, Properties setzen, Quellen zurücklesen. Danach nur
die neue Beobachterinstanz aktivieren. Keine vorhandenen Heizungsvariablen oder
Archivkonfigurationen ändern. Die technische Installation und Aktivierung von
Version 0.2.0 wurden auf Symcon 9.1 erfolgreich geprüft; dies ersetzt keine
Vorprüfung einer anderen Installation und keinen Test der tatsächlichen
Vorhersagegüte während der Heizperiode.

Rücknahme: `Enabled=false` und Änderungen übernehmen. Dadurch wird der Timer
deaktiviert; vorhandene Journal- und Anzeigedaten bleiben erhalten. Vor Entfernen
der Instanz Journal exportieren und Instanzkonfiguration sichern. Entfernen oder
Historienlöschung ist keine automatische Rücknahmehandlung.

Offizielle Verträge: [SDK-Modulfunktionen](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module.md),
[Archive Control](https://www.symcon.de/de/llms/modules/archive-control.md),
[Variablenverwaltung](https://www.symcon.de/de/llms/functions/management-variables.md).
