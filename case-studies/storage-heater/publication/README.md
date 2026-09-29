# Nachtspeicher Prognose

Beobachtendes Symcon-Modul für Nachtladung und Prognosevergleich. Es schaltet keine Heizgeräte.

## Installation

Nach Veröffentlichung der Bibliothek in Symcons Modulverwaltung unter **Modules → +** die Repository-URL `https://github.com/doctee/saef-storage-heater` hinzufügen. Anschließend eine Instanz **Nachtspeicher Prognose** anlegen. Sie startet deaktiviert. Quellenzuordnung, Beginn verlässlicher Daten und ein zum Objekt passendes trainiertes Modell konfigurieren; erst nach Prüfung die Beobachtung aktivieren.

Voraussetzungen: PHP ab 8.2, moderner Symcon-Kernel, OpenMeteoWeather mit Stundenprognose, archivierter separater kWh-Zähler (Zähleraggregation), kW-Leistung, Außentemperatur, zwei Raumtemperaturen und boolesche Betriebszustände. Die Installation oder Nutzung benötigt keine privaten Zugangsdaten in dieser Bibliothek.

## Bedienung

Im Instanzformular unter **Kamin angezündet am** Datum und Uhrzeit auswählen und **Kaminstart speichern** drücken. Nachträge sind möglich. Keine Endzeit und keine Holzmenge erforderlich. Ein versehentlich erfasster Start kann zum selben Zeitpunkt storniert werden; die Korrektur bleibt nachvollziehbar. Keine Messung oder Wärmemenge wird aus der Nennleistung des Kaminofens erfunden.

Das Modul prognostiziert täglich zwischen 21:45 und 22:00 Uhr die nächste Nachtladung (22–07 Uhr, Europe/Berlin). Ab 08 Uhr wird die Messung ergänzt. Versäumte Vorhersagen werden nicht nachträglich erfunden. Die erfasste Kaminhistorie wird getrennt gespeichert; spätere Nachträge ändern niemals eine frühere Prognose oder deren damaligen Wissensstand.

Die Fehlerauswertung unterscheidet Nächte mit und ohne **gemeldeten** Kaminstart im Fenster von 24 Stunden vor Ladebeginn bis Ladeende. Dieses Vergleichsfenster ist keine angenommene Brenndauer; kein Eintrag beweist nicht, dass der Kamin aus war. Eine mathematische Korrektur des Heizmodells erfolgt erst nach ausreichenden Beobachtungen.

## Daten und Grenzen

Das Nachtjournal umfasst 400 Nächte; Kaminstarts können bis 400 Tage zurück erfasst werden. Bis 1000 Einträge bleiben einschließlich Stornierungen erhalten; ein volles Protokoll wird gemeldet und nicht still überschrieben. Über **Journal ausgeben (JSON)** sind beide Protokolle und die Vergleichsauswertung exportierbar.

`ForecastValid` und `ForecastDate` gehören immer zum Zahlenwert `ForecastKWh`. Sensoralter und Wetterabdeckung werden geprüft. Ein fehlendes Frischesignal blockiert die Prognose statt einen Wert zu erfinden. Die Leistungsserie darf nur Änderungen melden; für elektrische Messwertfrische dient der direkte Energiezähler.

Kein Speicherfüllstand, keine automatische Regleroptimierung, kein Raumtemperatur- oder Ankunftsversprechen. Gegenwärtig festes Nachtfenster und vortrainierte Regression. Das Modell benötigt eine private, zur Installation passende Kalibrierung. Die dokumentierte historische Genauigkeit mit tatsächlich eingetretenem Wetter ist kein Nachweis der Genauigkeit mit Wettervorhersagen.

## Deaktivieren

**Beobachtung aktivieren** abwählen und übernehmen stoppt den Timer; Kaminstarts lassen sich weiterhin nachtragen. Vor Löschen der Instanz das Journal und die Konfiguration sichern.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe LICENSE. Keine private Installationskonfiguration oder Trainingshistorie ist Bestandteil dieser Bibliothek.
