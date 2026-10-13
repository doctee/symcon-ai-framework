# Nachtspeicher Prognose

Beobachtendes Symcon-Modul für Nachtladung und Prognosevergleich. Es schaltet keine Heizgeräte.

## Installation

Nach Veröffentlichung der Bibliothek in Symcons Modulverwaltung unter **Modules → +** die Repository-URL `https://github.com/doctee/saef-storage-heater` hinzufügen. Anschließend eine Instanz **Nachtspeicher Prognose** anlegen. Sie startet deaktiviert. Quellenzuordnung, Beginn verlässlicher Daten und ein zum Objekt passendes trainiertes Modell konfigurieren; erst nach Prüfung die Beobachtung aktivieren.

Voraussetzungen: PHP ab 8.2, Symcon ab 8.0, OpenMeteoWeather mit Stundenprognose, archivierter separater kWh-Zähler (Zähleraggregation), kW-Leistung, Außentemperatur, zwei Raumtemperaturen und boolesche Betriebszustände. Die Installation oder Nutzung benötigt keine privaten Zugangsdaten in dieser Bibliothek.

## Bedienung

Für die Visualisierung stehen **Kamin angezündet am** (`FireplaceStartInput`) und **Kaminprotokoll** (`FireplaceAction`) als native, verlinkbare Bedienelemente bereit. Zuerst Datum und Uhrzeit prüfen bzw. auswählen, dann **Start speichern** drücken. Die Datumsauswahl allein erfasst keinen Kaminstart. **Start stornieren** korrigiert den exakt ausgewählten Zeitpunkt. Der Eingabezeitpunkt bleibt bis zur nächsten Änderung erhalten, auch nach Neustarts. `FireplaceStatus` zeigt das Ergebnis; `FireplaceEntries` die letzten 30 Einträge. Das Instanzformular bleibt ebenfalls nutzbar. Die native Aktionsdarstellung erfordert Symcon 8.0 oder neuer.

Im Instanzformular unter **Kamin angezündet am** Datum und Uhrzeit auswählen und **Kaminstart speichern** drücken. Nachträge sind möglich. Keine Endzeit und keine Holzmenge erforderlich. Ein versehentlich erfasster Start kann zum selben Zeitpunkt storniert werden; die Korrektur bleibt nachvollziehbar. Keine Messung oder Wärmemenge wird aus der Nennleistung des Kaminofens erfunden.

Das Modul prognostiziert täglich zwischen 21:45 und 22:00 Uhr die nächste Nachtladung (22–07 Uhr, Europe/Berlin). Ab 08 Uhr wird die Messung ergänzt. Versäumte Vorhersagen werden nicht nachträglich erfunden. Die erfasste Kaminhistorie wird getrennt gespeichert; spätere Nachträge ändern niemals eine frühere Prognose oder deren damaligen Wissensstand.

Die Fehlerauswertung unterscheidet Nächte mit und ohne **gemeldeten** Kaminstart im Fenster von 24 Stunden vor Ladebeginn bis Ladeende. Dieses Vergleichsfenster ist keine angenommene Brenndauer; kein Eintrag beweist nicht, dass der Kamin aus war. Eine mathematische Korrektur des Heizmodells erfolgt erst nach ausreichenden Beobachtungen.

## Daten und Grenzen

Das Nachtjournal umfasst 400 Nächte; Kaminstarts können bis 400 Tage zurück erfasst werden. Bis 1000 Einträge bleiben einschließlich Stornierungen erhalten; ein volles Protokoll wird gemeldet und nicht still überschrieben. Über **Journal ausgeben (JSON)** sind beide Protokolle und die Vergleichsauswertung exportierbar.

`ScoredNights` gibt die Anzahl tatsächlich bewerteter Nächte an; bei null Nächten ist der mittlere Fehler noch nicht aussagekräftig.

`ForecastValid` und `ForecastDate` gehören immer zum Zahlenwert `ForecastKWh`. Sensoralter und Wetterabdeckung werden geprüft. Ein fehlendes Frischesignal blockiert die Prognose statt einen Wert zu erfinden. Die Leistungsserie darf nur Änderungen melden; für elektrische Messwertfrische dient der direkte Energiezähler.

Kein Speicherfüllstand, keine automatische Regleroptimierung, kein Raumtemperatur- oder Ankunftsversprechen. Gegenwärtig festes Nachtfenster und vortrainierte Regression. Das Modell benötigt eine private, zur Installation passende Kalibrierung. Die dokumentierte historische Genauigkeit mit tatsächlich eingetretenem Wetter ist kein Nachweis der Genauigkeit mit Wettervorhersagen.

## Deaktivieren

**Beobachtung aktivieren** abwählen und übernehmen stoppt den Timer; Kaminstarts lassen sich weiterhin nachtragen. Vor Löschen der Instanz das Journal und die Konfiguration sichern.

## Version 0.3.1

Die Referenzbereinigung verwendet die dokumentierte Modulmethode
`GetReferenceList()`. Dadurch vermeidet sie beim Initialisieren unter Symcon
Rust den abgelehnten nativen Aufruf auf die gerade ausgeführte Instanz.
Konfiguration, Prognoseberechnung und Kaminprotokoll bleiben unverändert.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe LICENSE. Keine private Installationskonfiguration oder Trainingshistorie ist Bestandteil dieser Bibliothek.

## Version 0.4.0: Auskühlmodell und Temperaturvergleich

Optionales, privat kalibriertes `CoolingModelJSON` mit zwei Koeffizienten
(`kPerHour.gallery`, `kPerHour.sofa`), `schema=1`, `timezone=Europe/Berlin`,
`window=23-05` und `trainedThrough` als positivem Unix-Zeitpunkt vor Ausgabe.
Optionale `ranges` für gallery/sofa/outside enthalten die jeweiligen unteren und
oberen Kalibriergrenzen. `{}` deaktiviert nur diese Zusatzfunktion.
`CoolingBindingsJSON` kann `sofaBattery` (Prozentwert), `floor` und `supply`
(boolesche Zustände) als zusätzliche positive Quellen-IDs enthalten.

Die Temperaturkurve entsteht einmal zwischen 23:00 und 23:15, startet beim
aktuellen Sensorwert und endet spätestens 05:00 (maximal sechs reale Stunden).
Die zukünftigen Außentemperaturen kommen aus der damals gespeicherten
Wettervorhersage. Fehlende oder veraltete Sensorwerte, leere konfigurierte
Batterie und unvollständiges Wetter verhindern eine neue Kurve. Versäumte
Vorhersagen werden nicht nachgetragen. Die Kurve bleibt nach Ausgabe unverändert.

`CoolingComparison` zeigt aktuelle Temperaturen, Prognose und Messung getrennt
für Galerie und Wohnzimmer einschließlich Wertetabelle und der letzten sieben
Nächte. Die read-only HTMLBox kann in eine bestehende Visualisierung verlinkt
werden. `CoolingStatus` meldet fehlende Daten. Messungen werden zeitlich zwischen
Archivänderungen interpoliert, nur bei höchstens sechs Stunden Abstand; größere
Lücken bleiben offen. Nach höchstens sechs Stunden Wartezeit nach Nachtende wird
die Messung abgeschlossen. Das separate exportierbare Temperaturjournal bewahrt
maximal 120 Nächte auf und übersteht Neustarts.

Die Kurve ist ausdrücklich ein **Vergleich ohne zusätzliche Wärmezufuhr**.
Heizung, Speicherrestwärme, Kamin und Sonne sind nicht eingerechnet. Sie ist keine
Komfort-, Frostschutz- oder mehrtägige Temperaturgarantie. Elektrische
Ladeprognose, Kaminprotokoll und Heizungsregelung bleiben unverändert.
Rücknahme: nur `CoolingModelJSON` auf `{}` setzen; keine Historie wird gelöscht.
