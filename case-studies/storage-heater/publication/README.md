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

## Version 0.5.0: vorhandene Heizungsvorwahl

`PlanBindingsJSON` aktiviert einen rein beobachtenden Adapter für eine vorhandene
Vorwahl mit einmaligen zyklischen Start- und Rücksetzereignissen. Die Einbindung
muss anhand der tatsächlich vorhandenen Steuerung geprüft werden. Alle IDs bleiben
private Konfiguration; das Modul richtet keine Vorwahl und keine Heizungsereignisse ein.

Erforderliche Schlüssel:

| Schlüssel | Quelle |
| --- | --- |
| arrival | Ganzzahliger vorgewählter Ankunftszeitpunkt; 0 = keine Vorwahl |
| storageRequested, fanRequested | Boolesche Aufwärmflags |
| storageEvent, fanEvent, resetEvent | Einmalige zyklische Ereignisse der vorhandenen Steuerung |
| regulatorEnabled, regulatorActive | Boolesche Freigabe und Aktivierung des Raumreglers |
| setpoint | Numerischer Raumregler-Sollwert |
| setback | Boolescher aktueller Thermostat-Absenkzustand |
| winter | Boolescher Winterbetrieb |

Zusätzlich wird `presence` in den bisherigen `BindingsJSON` benötigt.
Der Adapter setzt folgende **zu prüfende Steuerungssemantik** voraus: Bei
Winterbetrieb ohne Anwesenheit startet der Speicher-Timer die Ladefreigabe ohne
Absenkung und zunächst ohne Ventilatorfreigabe. Der folgende Ventilator-Timer
gibt den Raumregler frei. Die Rücksetzung beendet die Aufwärmflags; sie ist nur
für weiterhin ausbleibende Anwesenheit angenommen. Ankunft darf die Vorwahl
löschen, während der Raumregler weiterläuft. Aktuelle Reglerfreigabe wird daher
unabhängig vom Vorwahlflag gelesen. Manuelle Eingriffe und künftige Türzustände
sind keine planbaren Dauerlaufzeiten.

Es werden echte `NextRun`-Werte verwendet; abgelaufene aktive Timer mit
`NextRun=0` gelten nicht als zukünftige Starts. Die Berechnungslogik der
bestehenden Steuerskripte wird weder ausgeführt noch nachgebaut.

- Variablen- und Timeränderungen lösen nach fünf Sekunden Beruhigungszeit eine
  Neulesung aus; der bestehende Fünfminutentakt dient als Rückfallebene.
  Zusammengehörige Schreibfolgen ergeben einen stabilen Planstand, keine Garantie
  für die Speicherung jedes flüchtigen Zwischenzustands.
- Planstände enthalten tatsächliche Beobachtungszeit, Werte, Änderungszeiten,
  Timerzustände, Bindings und Konfigurationshash. Änderungen werden neu angehängt.
- Ab 21:45 entsteht eine separate planbezogene Abschätzung. Planänderungen erzeugen
  neue Versionen, einschließlich späterer Änderungen während der Ladenacht.
  Die ursprüngliche Nachtprognose und ihre Fehlerstatistik werden nicht verändert.
- Nach Ladebeginn werden ausschließlich vorher gespeicherte Modelleingaben als
  Vergleichsbasis benutzt. Bereits abgeschlossene, mit einer Stunde Abstand
  abgefragte Zählerstunden werden separat als Messung ausgewiesen. Fehlende
  Grundlagen oder Archivlücken werden gemeldet, nicht rekonstruiert.
- Geplante konstante Ladefreigabe und Absenkung können in die bestehende
  Ganznacht-Regression eingehen. Ihr historisches `fan_hours`-Merkmal bleibt
  historische Laufzeit; es wird **nicht** durch zukünftige Freigabestunden ersetzt.
- Bei möglichem Ventilatorbetrieb, gemischtem Nachtbetrieb oder bedingter Ankunft
  wird keine scheinbar kalibrierte kWh-Gesamtprognose ausgegeben. Die
  unveränderte Vergleichsbasis bleibt sichtbar. Die mögliche Ventilatorlaufzeit
  reicht von null bis zur geplanten Freigabedauer; sie ist weder ein
  Vertrauensintervall noch eine Schätzung des elektrischen Ventilatorverbrauchs.
  Wärmeabgabe, Restenergie und Aufwärmtemperaturen benötigen ein eigenes,
  belastbar kalibriertes Modell. Diese Version behauptet keine Verbesserung
  der Prognosegenauigkeit.

`PlanStatus` und die read-only HTMLBox `PlanComparison` lassen sich in eine
bestehende Visualisierung verlinken. `SHF_GetPlanJournalJson(instanceId)` exportiert
das separate Journal. Je maximal 256 Planstände und Prognoseversionen sowie eine
zusätzliche Gesamtgrenze von 3 MiB begrenzen den Speicher; ein Zähler dokumentiert
entfernte alte Einträge. Die verbleibenden Einträge bleiben unverändert. Für eine
langfristige Kalibrierung regelmäßig privat exportieren.

Rücknahme: nur `PlanBindingsJSON` auf `{}` setzen und übernehmen. Das entfernt
die Beobachtungsabonnements und stoppt den Zusatz-Timer, erhält aber das Journal.
Deaktivieren der ganzen Instanz stoppt beide Beobachtungstimer. Keine Geräte,
vorhandenen Ereignisse, Reglerparameter oder Archiveinstellungen werden geändert.

## Version 0.6.0: experimentelle Aufwärmkurven

Das optionale privat kalibrierte `WarmingModelJSON` ergänzt zwei Luft-/Massemodelle
für Galerie und Wohnzimmer. `WarmingOptionsJSON` bindet den vorhandenen
Zweipunktregler ein und enthält `regulator`, `latitude`, `longitude`, `maxPowerKw`
und `standbyKw`. Die vorhandenen Vorwahl- und Temperaturbindungen werden verwendet.

Bei zukünftiger Ankunft werden bis zu 48 Stunden vorausberechnet. Ventilatorlauf
folgt der Vorwahl und einem simulierten Galerie-Thermostat. Für die Nachtladung
wird eine **experimentelle frühe Schätzung** aus dem bisherigen Nachtmodell
verwendet und gleichmäßig über 23–07 verteilt; Netzunterbrechungen sind unbekannt.
Wetter/Sonnenwärme, Modell, Plan, Ausgangswerte und Annahmen werden mit der Kurve
fest gespeichert. Änderungen erzeugen eigene Versionen. Die reguläre nächtliche
kWh-Prognose bleibt davon getrennt.

`WarmingComparison` zeigt zwei Diagramme, Messabdeckung, mittleren absoluten
Fehler und vorzeichenbehaftete Abweichung je Planstand. `WarmingStatus` meldet den
Zustand. Keine Nachberechnung wird als ursprüngliche Vorhersage ausgegeben.
Messlücken und Batterieausfälle bleiben sichtbar; Kaminmeldungen und tatsächlicher
Ventilatorbetrieb werden getrennt erfasst. Überlappende Versionen sind keine
unabhängigen Tests. Die bisherige Archivgenauigkeit gilt nicht automatisch für
diese Vorhersage mit geplantem Betrieb und prognostizierter Ladung.

`SHF_GetWarmingJournalJson` exportiert das separate Journal (maximal 48 Stände,
drei MiB; noch offene Vergleiche werden nicht still entfernt). Rücknahme:
`WarmingModelJSON={}`; Historie bleibt erhalten. Die Heizungsregelung wird nie
verändert. Keine gesicherte Aussage zu 48 Stunden, Wohnzimmertemperatur 18 °C oder
physischem Speicherfüllstand.

## Version 0.7.0: Fenster und getrennte Aufwärmstufen

Optional in `WarmingOptionsJSON`: `windowRaw` (Ganzzahl 0 geschlossen, 1 gekippt,
2 offen) und `windowContact` (boolescher Offen-Kontakt desselben Fensters).
Beide müssen gemeinsam gesetzt werden. Der boolesche Kontakt muss eine nicht
invertierte Absenkquelle des konfigurierten Heating-Control-Reglers sein.
Alle tatsächlichen `SetBackVariables` werden lesend eingebunden (maximal 16);
Absenkbetrag und Kontaktinversion werden aus der Reglerkonfiguration übernommen.
Die vorhandene Reglerkonfiguration wird nicht geschrieben.

Die Vorhersage trennt Speicher-Absenkung, Ventilatorfreigabe und Raumregler-
Absenkung. Erst Speicherladung ohne Absenkung und ohne Ventilator, später
Ventilatorregelung nach Vorwahl. Bei beispielsweise 20,5 °C und 5 K aktiver
Regler-Absenkung simuliert sie den Thermostat um 15,5 °C. Die Speicher-Absenkung
bleibt davon unabhängig. Weitere Absenkkontakte werden als ODER berücksichtigt.

Fensterzustände und Kontaktwechsel sind Bestandteil des Planstands. Ein
Zustandswechsel erzeugt nach Beruhigung eine neue Aufwärmkurve, soweit eine
zukünftige Aufwärmvorwahl besteht. Reine Sensor-Heartbeatmeldungen erzeugen
keine neuen Stände. Veraltete Rohwerte und widersprüchliche Fenster-/Reglerstände
verhindern neue Kurven. Gespeicherte Kurven bleiben unverändert.

**Zukunftsannahme:** Alle aktuellen Fenster-/Türkontakte bleiben bis zum Horizont
unverändert. Ein übliches nächtliches Kippen wird nicht als sicherer zukünftiger
Schaltzeitpunkt erfunden. Die Ansicht nennt diese Annahme, den wirksamen Sollwert,
beide Aufwärmstufen sowie später tatsächlich gekippte/offene Stunden und
Abweichungen von der Annahme. Zusätzliche Lüftungswärmeverluste sind noch nicht
kalibriert; die Kurve berücksichtigt den bekannten Thermostateffekt, keinen
frei erfundenen Wärmeverlustfaktor. Der Energieeinfluss künftigen Ventilatorbetriebs
ist weiterhin unkalibriert und wird nicht aus der historischen Laufzeit abgeleitet.

Auch die ursprüngliche Nachtprognose speichert den bei Ausgabe bekannten Plan
mit Fensterkontext. Nach der Nacht werden Fensterexposition und Wetterabweichung
gegen die gebundene Außentemperatur getrennt vom Energievergleich protokolliert.
Fehlende Zusatzdaten verhindern den Energievergleich nicht. Es wird keine
Wetterkorrektur automatisch aktiviert; diese benötigt eine zeitlich getrennte
Prüfung mit ausschließlich früher verfügbaren Daten. Frühere Journale bleiben
lesbar. Rücknahme der Fenstereinbindung: nur `windowRaw` und `windowContact` aus
`WarmingOptionsJSON` entfernen und übernehmen; keine Historie löschen.
