# Heizungsvorwahl und Prognoseversionen

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
