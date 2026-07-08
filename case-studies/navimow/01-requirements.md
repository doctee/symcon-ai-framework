# 01 Requirements and Source Analysis

**Case study:** Navimow native IP-Symcon module  
**Status:** Initial analysis  
**Date:** 2026-07-08  
**Source under analysis:** <https://github.com/TA2k/ioBroker.navimow>  
**Source version observed:** `ioBroker.navimow` 1.0.2 metadata and README, with `main.js` as implementation source.  
**Implementation boundary:** This document contains analysis only. No productive PHP code is introduced.

## 1. Zielsetzung

Ziel ist ein natives IP-Symcon-Modul fuer Segway Navimow Maehroboter, das den
relevanten Funktionsumfang der bestehenden ioBroker-Integration technisch
nachvollziehbar nachbildet, aber nach SAEF als eigenstaendige Symcon-
Integration entworfen wird.

Das Modul soll nicht als direkte Portierung verstanden werden. Die ioBroker-
Integration dient als API- und Verhaltensquelle. Die Symcon-Architektur muss
eigene Modulgrenzen, Variablenrollen, Aktionen, Diagnostik und Fehlerbehandlung
definieren.

## 2. Analyseumfang und Quellenlage

Analysiert wurden:

- Repository README mit Feature-, Setup-, State- und API-Beschreibung.
- Adapter-Metadaten aus `io-package.json`.
- Admin-Konfiguration aus `admin/jsonConfig.json`.
- Zentrale Implementierungsstellen in `main.js` fuer OAuth2, REST, MQTT/WSS,
  Topic-Verarbeitung, Status-Polling, Remote-Befehle und Token-Refresh.

Die Analyse stuetzt sich auf eine Community-Implementierung und nicht auf eine
vollstaendige offizielle Herstellerdokumentation. API-Stabilitaet, Rate Limits
und vollstaendige Payload-Schemata muessen daher im naechsten Schritt separat
verifiziert werden.

## 3. Funktionsumfang der ioBroker-Integration

Die ioBroker-Integration bietet:

- OAuth2-Login ueber ein Navimow-Konto.
- Token-Austausch und automatischen Refresh.
- Geraete-Erkennung ueber die Navimow Smart-Home-API.
- Periodisches HTTP-Polling fuer allgemeine Statuswerte.
- MQTT ueber WebSocket Secure fuer Echtzeit-Updates.
- Remote-Befehle: Start, Stop, Pause, Resume und Dock.
- Statuskanaele fuer Geraeteinformationen, Statuswerte, Events, Attribute,
  Location und Diagnose.
- Roh-JSON des letzten Status als Diagnose- und Analysehilfe.
- Standort-/Positionswerte aus MQTT, inklusive relativer Koordinaten und
  Maehfortschritt.
- MQTT-Location-Watchdog in README/Version 1.0.2: Wenn HTTP einen aktiven
  Maehzustand meldet, aber MQTT-Location ausbleibt, wird der Location-Stream als
  stale markiert und MQTT kontrolliert neu verbunden.
- Optionale Kartenvisualisierung als PNG/Base64 in der ioBroker-Welt.

## 4. Verwendete APIs

Die Integration verwendet die Navimow Cloud unter:

```text
https://navimow-fra.ninebot.com
```

Die README nennt als Grundlage die offizielle Navimow SDK REST API sowie die
Navimow Home-Assistant-Integration. Die konkrete Implementierung nutzt REST fuer
Authentifizierung, Discovery, Polling und Befehle sowie MQTT/WSS fuer
Echtzeitdaten.

### REST-Endpunkte

| Methode | Pfad | Zweck | Anmerkung fuer IP-Symcon |
| --- | --- | --- | --- |
| `POST` | `/openapi/oauth/getAccessToken` | OAuth2 Authorization-Code-Austausch und Refresh. | Benoetigt Token-Speicherung und sicheren Umgang mit Refresh Tokens. |
| `GET` | `/openapi/smarthome/authList` | Geraete-Erkennung. | Liefert Geraete unter `data.payload.devices`. |
| `POST` | `/openapi/smarthome/getVehicleStatus` | Statusabfrage fuer bekannte Geraete. | Request enthaelt `devices: [{ id }]`. |
| `POST` | `/openapi/smarthome/sendCommands` | Befehle an Geraete. | Nutzt Google-Smart-Home-artige Command-Payloads. |
| `GET` | `/openapi/mqtt/userInfo/get/v2` | MQTT-Verbindungsinformationen. | Liefert Host/URL, Username und Passwortinformationen fuer MQTT/WSS. |

### Gemeinsame Header

Die Implementierung setzt fuer authentifizierte REST-Aufrufe:

```text
Authorization: Bearer <access_token>
Content-Type: application/json
requestId: <random UUID>
```

Token-Austausch und Token-Refresh nutzen `application/x-www-form-urlencoded`.

## 5. Authentifizierung

Der Login-Flow ist OAuth2 Authorization Code mit dem Channel/Client
`homeassistant`.

Beobachtete Konstanten und Parameter:

| Parameter | Beobachteter Wert / Verhalten |
| --- | --- |
| Login-URL | `https://navimow-h5-fra.willand.com/smartHome/login?...` |
| `channel` | `homeassistant` |
| `client_id` | `homeassistant` |
| `response_type` | `code` |
| `redirect_uri` | `http://localhost:1/callback` |
| Token-Endpunkt | `/openapi/oauth/getAccessToken` |
| Token-Refresh | gleicher Endpunkt mit `grant_type=refresh_token` |

Die ioBroker-Integration laesst den Benutzer die komplette Redirect-URL oder
den reinen `code` in die Adapterkonfiguration kopieren. Nach erfolgreichem
Token-Austausch wird der Auth-Code aus der Konfiguration geloescht und das
Token-Objekt gespeichert.

### SAEF-Entscheidung AD-NAV-001: Token-Daten sind Modulkonfiguration bzw. geschuetzter Modulzustand

**Entscheidung:** Ein natives Symcon-Modul darf OAuth Tokens nicht in
oeffentlichen Beispielen, Case-Study-Dateien oder sichtbaren Standardwerten
ablegen. Refresh Token und Access Token gehoeren in geschuetzte
Instanzeigenschaften oder einen anderen Symcon-geeigneten geschuetzten
Speichermechanismus.

**Rationale:** Tokens sind private Daten. SAEF verlangt die Trennung von
oeffentlichen Artefakten und privater Installation. Zudem muessen Token-Refresh
und Fehlerzustand nach Neustarts nachvollziehbar bleiben.

**Konsequenz:** Die spaetere Modulimplementierung braucht einen expliziten
Login-/Reauth-Status, aber keine produktiven Token-Beispiele in der
Dokumentation.

## 6. MQTT/WSS-Kommunikation

Die Integration ruft zunaechst `/openapi/mqtt/userInfo/get/v2` ab. Daraus werden
MQTT-Verbindungsdaten wie `mqttUrl`, `mqttHost`, `userName` und `pwdInfo`
abgeleitet.

Beobachtetes Verhalten:

- Wenn `mqttUrl` vorhanden ist, wird bevorzugt WebSocket verwendet.
- Bei `wss` wird TLS-Zertifikatsvalidierung aktiviert.
- Der WebSocket-Handshake enthaelt einen `Authorization: Bearer <access_token>`
  Header.
- Wenn Username und Passwort vorhanden sind, werden sie als MQTT Credentials
  gesetzt.
- Wenn kein `mqttUrl` vorhanden ist, existiert ein Fallback auf TCP MQTT
  `mqtt://<host>:1883`.
- Client-IDs beginnen mit `web_` und enthalten einen zufaelligen Anteil.
- MQTT wird nach Token-Refresh erneut verbunden.

### MQTT-Topics

Die Integration abonniert pro Geraet:

```text
/downlink/vehicle/{deviceId}/realtimeDate/state
/downlink/vehicle/{deviceId}/realtimeDate/event
/downlink/vehicle/{deviceId}/realtimeDate/attributes
/downlink/vehicle/{deviceId}/realtimeDate/location
/downlink/vehicle/{deviceId}/#
```

Der Topic-Parser erwartet:

```text
downlink/vehicle/{deviceId}/.../{channel}
```

Der letzte Topic-Teil wird als Kanal interpretiert. `state` wird in den
Statusbereich gemappt; andere Kanaele werden unter ihrem Kanalnamen abgelegt.
Array-Payloads werden auf den letzten Eintrag reduziert, etwa bei Location-
Payloads.

### SAEF-Entscheidung AD-NAV-002: MQTT ist Echtzeitpfad, HTTP bleibt Konsistenzpfad

**Entscheidung:** Ein Symcon-MVP sollte MQTT/WSS als Echtzeitkanal nutzen,
HTTP-Polling aber als eigenen Konsistenz- und Recovery-Pfad behalten.

**Rationale:** Die Quelle beschreibt, dass Batteriestand, Status und
`vehicleState` per HTTP stabilisiert werden, waehrend Location und
Maehfortschritt ueber MQTT kommen. Cloud- und MQTT-Verbindungen koennen
unabhaengig altern oder ausfallen.

**Konsequenz:** Die Modularchitektur braucht getrennte Diagnose- und
Fehlerzustaende fuer REST, MQTT, Token und letzte erfolgreiche Datenaktualisierung.

## 7. Geraete- und Datenmodell

Die ioBroker-Integration erzeugt pro Geraet eine Struktur nach Device-ID:

| Bereich | Zweck |
| --- | --- |
| `{deviceId}.general` | Geraeteinformationen wie Name, Modell, Seriennummer und Firmware. |
| `{deviceId}.status` | Aktuelle Statuswerte, z. B. `vehicleState`, Batterie, Position, Signal. |
| `{deviceId}.status.json` | Roh-JSON des letzten Statusupdates. |
| `{deviceId}.events` | MQTT-Events. |
| `{deviceId}.attributes` | MQTT-Device-Attribute. |
| `{deviceId}.remote` | Steuerbefehle. |
| `{deviceId}.location` | Echtzeitposition und Maehfortschritt aus MQTT. |
| `{deviceId}.diagnostics` | MQTT-Location-Watchdog-Diagnose. |
| `{deviceId}.map` | ioBroker-spezifische PNG/Base64-Kartenvisualisierung. |

### Beobachtete Statuswerte

Die README dokumentiert `status.vehicleState` mit folgenden bekannten Werten:

| Wert | Bedeutung |
| --- | --- |
| `isRunning` | Maehen |
| `isDocked` | Gedockt |
| `isIdle` | Idle |
| `isPaused` | Pausiert |
| `isDocking` | Rueckkehr zur Dockingstation |
| `isMapping` | Mapping |
| `isLifted` | Angeheben / Fehler |
| `Error` | Fehler |
| `inSoftwareUpdate` | Softwareupdate |
| `Self-Checking` | Selbstpruefung |
| `Offline` | Offline |

### Location-Daten

Bekannte Location-Werte:

| Wert | Bedeutung |
| --- | --- |
| `postureX` | Relative X-Position in Metern. |
| `postureY` | Relative Y-Position in Metern. |
| `postureTheta` | Orientierung in Radiant. |
| `vehicleState` | numerischer oder kodierter Fahrzeugstatus aus Location-Payload. |
| `time` | Zeitstempel. |
| `mowingPercentage` | Maehfortschritt aus MQTT. |

Wichtig: Die Koordinaten sind keine GPS-Koordinaten, sondern relative
Koordinaten innerhalb der Maehflaeche.

### SAEF-Entscheidung AD-NAV-003: Keine blinde JSON-zu-Variable-Abbildung im MVP

**Entscheidung:** Das native IP-Symcon-Modul soll im MVP eine kuratierte
Variablenliste fuer stabile Kernwerte verwenden. Roh-JSON darf als
diagnostische Debug-Information vorgesehen werden, aber nicht als primaeres
Datenmodell.

**Rationale:** ioBroker kann dynamisch Datapoints erzeugen. Ein Symcon-Modul
sollte Variablenrollen, Profile, Aktionen und Archivierungsverhalten explizit
machen. Das reduziert Schema-Drift und macht die Integration reviewbar.

**Konsequenz:** Unbekannte Felder werden zunaechst protokolliert bzw. in
diagnostischem Rohstatus gehalten. Erst wiederkehrende stabile Felder werden
zu oeffentlichen Modulvariablen.

## 8. Befehle

Die Befehle werden ueber `/openapi/smarthome/sendCommands` gesendet. Die
Payload folgt einem Google-Smart-Home-aehnlichen Schema:

```json
{
  "commands": [
    {
      "devices": [{ "id": "<deviceId>" }],
      "execution": {
        "command": "<command>",
        "params": {}
      }
    }
  ]
}
```

Beobachtetes Mapping:

| UI-Befehl | API-Command | Params |
| --- | --- | --- |
| `start` | `action.devices.commands.StartStop` | `{ "on": true }` |
| `stop` | `action.devices.commands.StartStop` | `{ "on": false }` |
| `pause` | `action.devices.commands.PauseUnpause` | `{ "on": false }` |
| `resume` | `action.devices.commands.PauseUnpause` | `{ "on": true }` |
| `dock` | `action.devices.commands.Dock` | keine |
| `Refresh` | keine Remote-API; lokaler Status-Refresh | nicht zutreffend |

Die ioBroker-Integration behandelt `alreadyInState` nicht als harten Fehler.
Nach erfolgreichem Befehl wird nach ungefaehr fuenf Sekunden ein Status-Refresh
ausgeloest.

### SAEF-Entscheidung AD-NAV-004: Steuerbefehle werden als actions modelliert, nicht als Statuswerte

**Entscheidung:** Im IP-Symcon-Modul sollen Start, Stop, Pause, Resume, Dock
und Refresh als kontrollierte Aktionen modelliert werden. Statusvariablen
bleiben read-only. Schreibbare Symcon-Variablen muessen ueber die Modul-
Action-Semantik laufen.

**Rationale:** SAEF und ADR-0001 verlangen `RequestAction()` fuer steuerbare
Variablen. Direkte Statusmanipulation wuerde Cloud-Zustand und Symcon-Zustand
entkoppeln.

**Konsequenz:** Der MVP braucht eine klare Action-Matrix: welche Aktion ist in
welchem `vehicleState` erlaubt, welche Antwort gilt als Erfolg, und wie wird
ein verzogertes Feedback sichtbar gemacht.

## 9. Runtime State und Diagnostik

Fuer ein natives Symcon-Modul sind mindestens folgende Zustandsklassen zu
trennen:

| Klasse | Beispiele | SAEF-Einordnung |
| --- | --- | --- |
| Oeffentliche Konfiguration | Login-/Auth-Status, Polling-Intervall, aktivierte Geraete | Modulkonfiguration |
| Domain State | `vehicleState`, Batterie, Docking-/Mowing-Zustand, Location | oeffentliche Statusvariablen |
| Command State | laufender Befehl, letzter Command, Command-Ergebnis | Aktionsdiagnostik bzw. Status |
| REST-Diagnostik | letzter HTTP-Erfolg, Fehlerzaehler, HTTP-Status | Statistics/ErrorRingBuffer |
| MQTT-Diagnostik | verbunden, letzter MQTT-Message-Zeitpunkt, stale status | Statistics/Registry/ErrorRingBuffer |
| Token-Diagnostik | Token vorhanden, Ablauf/Refresh geplant, Reauth erforderlich | Registry/Status, ohne Tokeninhalt |
| Raw Payload Debug | letztes gekuerztes Status-JSON | begrenzt und ohne Geheimnisse |

### SAEF-Entscheidung AD-NAV-005: Watchdog als explizite Modulverantwortung

**Entscheidung:** Der spaetere MVP sollte einen einfachen MQTT-Health-Status
und Zeitstempel fuer letzte REST- und MQTT-Aktualisierung enthalten. Der
vollstaendige Location-Watchdog mit kontrolliertem Reconnect kann als
MVP-plus-Funktion folgen, wenn Location in den MVP aufgenommen wird.

**Rationale:** EK-002, EK-004 und EK-006 verlangen begrenzte, erklaerbare
Retries und explizite interne Zustandsdiagnostik. MQTT-Staleness ohne
Diagnostik waere schwer zu betreiben.

**Konsequenz:** Die Analyse trennt "MVP fuer stabile Steuerung" von "erweiterte
Realtime-Location mit Recovery".

## 10. Erkannte Risiken

| Risiko | Bewertung | Konsequenz |
| --- | --- | --- |
| Inoffizielle bzw. nur indirekt dokumentierte API-Nutzung | Hoch | API-Verhalten muss mit Testaccount und realem Geraet validiert werden. |
| Cloud-Abhaengigkeit | Hoch | Modul muss Offline-, Token-, REST- und MQTT-Ausfall getrennt darstellen. |
| OAuth2-Flow mit Copy/Paste-Redirect | Mittel | Symcon UX muss klar und sicher gestaltet werden. |
| Tokens als private Daten | Hoch | Keine Tokens in Logs, Case Study, Beispielen oder sichtbaren Variablen. |
| Region/Base-URL `navimow-fra.ninebot.com` | Mittel | Offen, ob andere Regionen andere Hosts benoetigen. |
| MQTT/WSS-Header und Credentials | Mittel | Symcon-Unterstuetzung fuer WSS mit Authorization Header muss technisch geprueft werden. |
| Dynamische Payload-Schemata | Mittel | MVP sollte stabile Kernvariablen kuratieren. |
| Steuerbefehle mit verzogertem Feedback | Mittel | Aktionen duerfen Erfolg nicht nur aus HTTP-Antwort ableiten. |
| Sicherheitsrelevante Befehle an reales Geraet | Mittel | Keine ungebundenen Retries fuer Start/Stop/Dock; Statuspruefung nach Befehl. |
| Location-Daten nur waehrend Aktivitaet | Niedrig bis mittel | Location darf nicht als immer verfuegbarer Status modelliert werden. |
| Kartenrendering als Base64 | Niedrig fuer MVP | Nicht Bestandteil des MVP; spaeter separat bewerten. |
| Rate Limits unbekannt | Mittel | Polling-Intervall konservativ waehlen und Fehler auswerten. |

## 11. Offene Fragen

1. Welche IP-Symcon-Version soll das native Modul mindestens unterstuetzen?
2. Welche Authentifizierungs-UX ist in Symcon realistisch: Copy/Paste-Code,
   lokaler Callback, oder externer Manual Flow?
3. Sind `client_id=homeassistant` und der beobachtete Client Secret fuer eine
   Symcon-Integration zulaessig und dauerhaft stabil?
4. Gibt es offizielle Navimow SDK-Dokumentation mit vollstaendigen Schemas,
   Rate Limits und Fehlercodes?
5. Sind andere Regionen als `fra` relevant?
6. Welche Mower-Modelle und Firmware-Versionen sollen im MVP getestet werden?
7. Welche `vehicleState`-Werte treten in realen Fehlerfaellen auf?
8. Welche Felder liefert `getVehicleStatus` fuer unterschiedliche Modelle?
9. Welche MQTT-Payloads kommen fuer `state`, `event`, `attributes` und
   `location` vollstaendig vor?
10. Wie lange sind Access Token, Refresh Token und MQTT Credentials gueltig?
11. Unterstuetzt die Symcon-MQTT-Infrastruktur den benoetigten WSS-Auth-Header,
    oder braucht das Modul einen eigenen WebSocket/MQTT-Client?
12. Sollen mehrere Geraete als eine Konfigurator-/Splitterstruktur oder als
    eigenstaendige Device-Instanzen modelliert werden?
13. Welche Variablen sollen archiviert werden, und welche nicht?
14. Soll Location im MVP enthalten sein oder erst nach stabiler REST- und
    Befehlsunterstuetzung folgen?
15. Wie soll ein laufender Befehl angezeigt werden, wenn die API den Befehl
    annimmt, der Geraetezustand aber erst spaeter umspringt?

## 12. Architekturentscheidungen fuer den ersten Analysestand

| ID | Entscheidung | Status |
| --- | --- | --- |
| AD-NAV-001 | Token-Daten sind Modulkonfiguration bzw. geschuetzter Modulzustand. | Angenommen fuer Analyse |
| AD-NAV-002 | MQTT ist Echtzeitpfad, HTTP bleibt Konsistenzpfad. | Angenommen fuer Analyse |
| AD-NAV-003 | Keine blinde JSON-zu-Variable-Abbildung im MVP. | Angenommen fuer Analyse |
| AD-NAV-004 | Steuerbefehle werden als actions modelliert, nicht als Statuswerte. | Angenommen fuer Analyse |
| AD-NAV-005 | Watchdog als explizite Modulverantwortung, aber nicht zwingend kompletter MVP-Umfang. | Angenommen fuer Analyse |

Diese Entscheidungen bleiben innerhalb der Case Study. Falls sie spaeter
oeffentliches Modulverhalten, SAEF-Standards oder wiederverwendbare Muster
praegen, sollten sie in ADRs oder Knowledge-Artefakte ueberfuehrt werden.

## 13. MVP-Empfehlung

Der empfohlene MVP sollte bewusst klein bleiben:

1. OAuth2 Authorization-Code-Flow mit sicherer Token-Speicherung und Refresh.
2. Geraete-Erkennung ueber `/openapi/smarthome/authList`.
3. Eine Device-Instanz pro Mower mit kuratierten Kernvariablen:
   `vehicleState`, Batterie, Online-/Connection-Status, letzte REST-
   Aktualisierung und Rohstatus nur als begrenzte Diagnose.
4. Periodisches HTTP-Polling ueber `/openapi/smarthome/getVehicleStatus`.
5. Steueraktionen `Start`, `Stop`, `Pause`, `Resume`, `Dock` und `Refresh`
   ueber Symcon-Action-Semantik.
6. Command-Diagnostik: letzter Befehl, letzter Command-Fehler, letzter
   erfolgreicher Statusabgleich nach Befehl.
7. REST-/Token-Diagnostik nach SAEF: Fehlerzaehler, letzte erfolgreiche
   Aktualisierung, Reauth-erforderlich.

MQTT/WSS und Location sollten als zweiter Schritt folgen, nicht als zwingender
MVP-Bestandteil. Begruendung: Die Cloud-REST-API und sichere Befehlssemantik
sind die Grundlage. MQTT erhoeht den Nutzen deutlich, bringt aber zusaetzliche
Komplexitaet bei WebSocket-Headern, Credential-Rotation, Staleness und
Reconnect-Verhalten.

## 14. Naechster Arbeitsschritt

Der naechste SAEF-konforme Schritt ist ein Modul-Design-Dokument innerhalb
dieser Case Study:

```text
case-studies/navimow/02-module-design.md
```

Dieses Dokument sollte vor PHP-Code klaeren:

- Modulstruktur: Splitter/Konfigurator/Device-Instanz oder vereinfachter
  Einzelinstanz-Ansatz.
- Symcon-Variablenliste mit Rollen, Typen, Profilen, Aktionen und
  Archivierungsentscheidung.
- Konfigurationsfelder und private Werte.
- Token- und Reauth-State-Machine.
- REST-Polling- und Command-State-Machine.
- Diagnostikstruktur nach Registry, Statistics und ErrorRingBuffer.
- Verifikationsplan mit Testaccount, realem Geraet und API-Payload-Sammlung.
