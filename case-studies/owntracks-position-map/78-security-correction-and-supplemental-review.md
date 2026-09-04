# Sicherheitskorrektur und Zusatzprüfung

Datum: 2026-09-03
Status: Repository-Korrektur umgesetzt; lokale Abnahme bestanden.
**Keine abschließende Live-Sicherheitsfreigabe.**

## Ergebnis und Freigabegrenze

Die drei Befunde aus [Prüfung 77](77-final-security-review.md) sind im Kandidaten
bearbeitet und durch gezielte Regressionen abgesichert. Die Zusatzprüfung hat
zwei weitere offene Punkte ergeben: lokale Windows-Anlegerechte in den geprüften
Verzeichnissen und eine bekannte Schwachstelle im gemeinsamen PHP-Prüfwerkzeug.

Die Arbeit erfolgte in einem neuen, zunächst sauberen Worktree auf frisch
abgeglichenem `origin/main` (`f5d57a0cd80e51c55bc3e7706a9c85fa0dd67786`).
Der nicht eingecheckte OwnTracks-Workstream wurde als ausdrücklich abgegrenzte
Wiederherstellungsquelle übernommen und gegen das zuvor geprüfte Paket
abgeglichen. Der Quell-Worktree blieb unverändert. Das ist keine Behauptung,
sämtliche OwnTracks-Dateien seien bereits Bestandteil von `main`.

Nur Repository-Dateien und lokale Test-/Build-Artefakte wurden verändert.
Live erfolgten ausschließlich lesende Symcon-MCP-Prüfungen; die ACL-Abfrage
nutzte darüber den vorhandenen Windows-`Get-Acl`-Befehl. Keine Installation,
ACL-Änderung, Paketaktivierung, Änderung von Instanzen, Archiven, Logging, Hooks
oder Visualisierung. Kein Browser- oder Tile-Provider-Kontakt. Kein Commit,
Push oder Veröffentlichung. Öffentliche Abhängigkeitsnamen und Versionen wurden
an die üblichen Advisory-Dienste übermittelt, keine Installationsdaten.

## Korrekturen

| Befund | Änderung | Nachweis |
| --- | --- | --- |
| SEC-01: temporäre Dateien vor Sperre entfernt | Miss-State- und Provider-Metadatenbereinigung erfolgen erst unter derselben exklusiven Sperre wie Schreiben/Umbenennen. | Ein zweiter Prozess darf während der ersten Schreibsperre keine temporäre Datei entfernen; der erste Schreiber kann danach erfolgreich umbenennen. |
| SEC-02: beschädigter Zustand erneuert Budgets | Ungültige, übergroße oder ungeeignete Zustandsdateien werden abgelehnt; die betroffene Datei bleibt als Beleg erhalten. Größenprüfung vor und beim Lesen. | Korruptions-/Größentests; keine Operation unter leerem Ersatzbudget. |
| SEC-03: unbeschränktes Warten und Netzwerk unter Sperre | Gemeinsame monotone Deadline, nichtblockierende Sperrversuche, dauerhafte Reservierung vor Netzwerk und Abschluss gegen frisch gesperrten Zustand. | Sperr-Timeout, überlange Test-DNS-Auflösung, vertauschte/doppelte Abschlüsse, unterbrochene Reservierung und Netzwerk ausdrücklich außerhalb der Zustandssperre. |

Der existierende lokale Datei-Cache lieferte das Bereinigungsmuster. Die
Reservierung setzt bestehende State-Store- und Request-Budget-Bausteine zusammen.
`OwnTracksTileDeadline` bleibt bewusst innerhalb der Case Study: Registry und
Statistics speichern Diagnosedaten, begrenzen aber keine lokale monotone
Dateisperrwartezeit. Kein allgemeiner SAEF-Karten- oder Diagnose-Helper wurde
eingeführt. Die reine Rollback-Konvertierung nutzt die vorhandene
Zustandsvalidierung derselben Klasse statt einer zweiten Schemaimplementierung.

### Budgetvertrag und Parallelität

`maximumRequestsPerSelection` und `maximumBytesPerSelection` gelten hier für
den bereits vorhandenen Viewport-Schlüssel, nicht pauschal für einen gesamten
ausgewählten Tag. Generation, Zoom und Bounds können einen neuen Teilzähler
erzeugen. Das separate instanzweite Provider-Minuten- und Parallelitätslimit
bleibt unverändert. Ein neuer Viewport setzt dieses globale Limit nicht zurück.

Unter der Zustandssperre werden vor I/O ein Request und das maximal für diesen
Transfer verbleibende Bytebudget, höchstens 512 KiB, belastet. Nach gültigem
Abschluss wird nur der ungenutzte Byteanteil freigegeben. Ein vor Netzwerkbeginn
abgelehnter Versuch wird zurückgebucht. Bei abgebrochenem oder unbekanntem
Transferausgang bleiben die konservativen Belastungen erhalten. Ein 304-Abruf
belastet keinen erneut heruntergeladenen Tile-Body. Doppelte Abschlüsse können
nicht mehrfach erstatten.

Reservierungen verfallen nach 15 Sekunden ohne Budgeterstattung. Die bestehende
globale Zulassungs-Lease läuft nach 30 Sekunden aus; regulär wird sie im
`finally` freigegeben. Scheitert die Freigabe selbst an einer Sperr-/Deadline,
bleibt die Lease bis zum Ablauf bestehen, statt zusätzliche Parallelität
zuzulassen. Die 8-Sekunden-Anwendungsdeadline wird vom Hook bis in die Speicher
weitergereicht; ein einzelner Sperrversuch wartet höchstens 250 ms. Der
Providertransfer verwendet den kleineren Wert aus Restzeit und konfiguriertem
Transferlimit.

Diese Grenzen setzen funktionierenden lokalen Dateisystemzugriff und
unterstützte asynchrone DNS-Auflösung voraus; sie sind keine Zusicherung gegen
einen blockierten Kernel oder ein hängendes Dateisystem. Bei einem 4-MiB-Teilbudget
können beispielsweise acht unbekannte volle 512-KiB-Transfers das Budget bereits
verbrauchen. Diese vorsichtige Belastung darf nicht als tatsächlich gemessener
Datenverkehr ausgegeben werden.

### Transport: bewusste Kompatibilitäts- und Schutzgrenze

Der Produktionspfad verwendet keine synchronen PHP-DNS-Funktionen mehr.
Native cURL-Auflösung muss `ASYNCHDNS` melden; außerdem ist
`CURLOPT_PREREQFUNCTION` erforderlich. Das bedeutet für dynamische Abrufe
PHP ab 8.4 mit passender cURL-Unterstützung. Die vorhandene Windows-Laufzeit
erfüllt diese Merkmale laut frischem MCP-Abgleich. Statische/offline Funktionen
haben weiterhin die bisherige PHP-Untergrenze; eine ungeeignete Laufzeit darf
diesen dynamischen Transport nicht stillschweigend ungeschützt ersetzen.
Die Funktionsgrenzen sind im
[PHP-Handbuch](https://www.php.net/manual/en/curl.constants.php) dokumentiert.

**Nicht mit der bisherigen DNS-Vorprüfung verwechseln:** Der native Callback
prüft die tatsächlich verbundene Adresse nach TCP-/TLS-Aufbau, aber vor der
HTTP-Anfrage. Ein DNS-Angriff kann damit noch einen TCP-/TLS-Verbindungsversuch
zu einer nichtöffentlichen Adresse auslösen; er darf keine Tile-Anfrage,
Referer- oder Capability-Daten dorthin senden. Der feste Providerhostname,
TLS-Zertifikats-/Hostprüfung, Port 443, abgeschaltete Redirects und explizit
abgeschaltete Umgebungsproxies bleiben verbindlich. Das entspricht dem
dokumentierten [cURL-Callback-Zeitpunkt](https://curl.se/libcurl/c/CURLOPT_PREREQFUNCTION.html),
nicht einer Sperre bereits vor dem TCP-Verbindungsaufbau. Diese Restgrenze ist
vor Aktivierung ausdrücklich zu akzeptieren oder mit einer separat geplanten
DNS-/Egress-Lösung enger zu fassen; eine neue DNS-Gegenstelle wurde nicht
eingeführt.

Zusätzlich wurde die Adressklassifikation verschärft: die bisherige Kombination
aus `NO_PRIV_RANGE` und `NO_RES_RANGE` akzeptiert beispielsweise Shared-Address-,
Benchmark- und Dokumentationsnetze. Der Kandidat verlangt `GLOBAL_RANGE` und
schließt Multicast zusätzlich aus. Dieselbe Verschärfung gilt bei der
Antwortannahme. Grundlage ist die
[PHP-Definition der Adressfilter](https://www.php.net/manual/en/filter.constants.php).
Synthetische Tests decken diese Netze, private IPv4/IPv6-Adressen, falschen Port,
abweichende tatsächliche Gegenstelle, fehlendes Async-DNS, Header-/Body-Übergröße
und überschrittene Transferzeit ab. Sie führen **keinen Netzwerkabruf** aus.
Der Headerempfang ist zusätzlich auf insgesamt 16 KiB begrenzt.

## Zusatzbefunde

### SEC-04: lokale Windows-Anlegerechte — offen (P2)

Die nur lesende Prüfung umfasste fünf Cache-/Budget-Verzeichnisse, ihre Eltern,
die statische Tile-Wurzel und deren Eltern sowie das aktive Modul und dessen
Eltern. Normale lokale Benutzer besitzen dort Anlegerechte für Dateien und
Unterverzeichnisse. Die geprüften fünf vorhandenen Metadaten-/Budgetdateien und
die aktive Modul-PHP-Datei weisen dagegen nur privilegierte Schreibberechtigungen
auf. Kein geprüftes Ziel war ein Reparse Point oder hatte eine Null-DACL.

Daraus folgt **nicht**, dass normale Benutzer bestehende Budgetdateien oder
Modulcode überschreiben können. Es fehlt aber eine exklusive Schreibgrenze für
neue Namen: Vorbelegen noch fehlender Dateien, Dateisystemfüllung und der
Einfluss selbst angelegter Unterverzeichnisse bleiben relevant. Es wurde weder
ein Ausnutzungsversuch noch eine vollständige Windows-AccessCheck-Prüfung mit
einem fremden Token ausgeführt. Die DACLs wurden über
[Get-Acl](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.security/get-acl)
und ihre SIDs ausgewertet; private Pfade und Identitäten bleiben im privaten
Prüfbereich.

Nächstes eigenes System-Gate: ausschließlich die genau aufgelösten
OwnTracks-Verzeichnisse mit gesichertem DACL-Backup härten oder unter einer
geschützten privaten Wurzel bereitstellen. Dienstkonto und Administratoren
benötigen die vorgesehenen Rechte, andere Konten keine Anlegerechte.
Keine pauschale Änderung von Windows-Temp, dem allgemeinen Modulverzeichnis
oder anderen SAEF-Verbrauchern. Vorhandene fremd angelegte Inhalte müssten vor
Übernahme inventarisiert werden; ACL-Härtung allein macht sie nicht vertrauenswürdig.
Die Rechte am gemeinsamen Modul-Elternverzeichnis können weitere Module betreffen
und benötigen eine getrennte Bewertung. Die Härtung eines OwnTracks-Unterordners
ist kein Sicherheitsnachweis für die gesamte privilegierte Symcon-Installation.

### SEC-05: gemeinsames PHP-Prüfwerkzeug — offen (Advisory: hoch)

Der aktuelle `composer audit --locked` meldet für das festgelegte
PHP_CodeSniffer 3.13.5 **CVE-2026-67434 / GHSA-hmqg-cxww-wqhq**.
Betroffen sind präparierte Dateinamen beim Erzeugen von Git-, Hg- oder
Svn-Blame-Berichten. Die hier verwendeten normalen Berichte gehören nicht zu
diesen Auslösepfaden. PHP_CodeSniffer ist eine Entwicklungsabhängigkeit und
nicht Bestandteil der 35 Modulnutzdateien. Das gemeinsame Werkzeug bleibt
trotzdem aktualisierungsbedürftig; die 3.x-Korrektur beginnt bei 3.13.6.
Siehe [Hersteller-Advisory](https://github.com/PHPCSStandards/PHP_CodeSniffer/security/advisories/GHSA-hmqg-cxww-wqhq).

Kein Update, keine Composer-Lock-Änderung und keine Änderung am gemeinsam
genutzten Vendor-Verzeichnis wurden vorgenommen. Das Update gehört in einen
separaten Framework-/Toolchain-Workstream mit anschließendem Regressionslauf.

Das JavaScript-Lockfile mit OpenLayers 10.10.0 und esbuild 0.28.2 wurde dagegen
mit `npm audit --package-lock-only --ignore-scripts` geprüft: keine gemeldeten
Advisories im Ergebnis (50 Pakete). Die aktuellen esbuild-Advisories für ältere
Versionen wurden zusätzlich mit den
[Hersteller-Meldungen](https://github.com/evanw/esbuild/security/advisories)
abgeglichen. Das ist keine Garantie gegen unbekannte Schwachstellen und kein
vollständiger Lieferkettennachweis.

## Verifikation und Artefakte

Erfolgreich:

- 21 Kommandos der OwnTracks-Testsuite einschließlich vorhandener
  Authentifizierungs-, WebHook-, Cache-, Runtime- und Pakettests;
- neue `security-regressions.php` und `system-tile-transport.php`;
- repositoryweite PHPStan-Analyse sowie PHPCS-Prüfung mit normalem Bericht;
- deterministischer Paketbau, Strukturprüfung und Fileset-Aktualitätsprüfung;
- unveränderter Live-Konfigurationsfingerabdruck und unterstützte
  Windows-Transportmerkmale über Symcon MCP.

Die erfolgreichen MCP-Abfragen hatten jeweils leere `transportError`- und
`executionError`-Felder sowie `truncated=false`. Eine erste Dateipaket-Vorprüfung
brach ohne Aktion ab, weil fälschlich ein Hash der Hashdatei statt deren Inhalt
verglichen wurde. Nach Korrektur des lesenden Vergleichs war genau ein passendes
Paket identifiziert; erst danach erfolgte dessen ACL-Abfrage.

Ausgangspaket:
`82c148a5f1e7789db1850641f5ea45f44322f8f4e8b2c84ea354b53982d2de9c`.

Das Kandidatenpaket umfasst 35 Nutzdateien plus zwei Manifeste. Gegenüber dem
Ausgangspaket sind acht PHP-Dateien geändert, eine lokale Deadline-Datei kommt
hinzu, 26 Nutzdateien sind byte-identisch. Renderer, Browser-Bundle, Quellen-
und ETA-Verträge sowie Form-/Modulidentität bleiben unverändert.
Die endgültige Kandidatenidentität steht im erzeugten
`fileset.sources.json` und im privaten Übergabeprotokoll:
`7354982680611b90a220ad6ffc618907ffb0f82c184d77fdb6ac2964e0406b61`.
Vor einer Aktivierung ist sie nochmals gegen alle Nutzdateien zu prüfen.
Der abschließende lesende MCP-Postflight bestätigte weiterhin genau das alte
Paket mit allen 34 Nutzdateien, unveränderte Konfiguration und gesunden Instanzstatus.

## Zustandsmigration und Rollback

Die erste autorisierte neue Speicherung migriert gültiges Miss-State-Format 1
nach Format 2, ohne verbrauchte Zähler zu erneuern. **Nur den alten Modulcode
zurückzukopieren wäre kein sicherer Rollback:** Der alte Store akzeptiert das
neue Ledger nicht und könnte den Zustand als leer neu anlegen.

Für einen getrennt freigegebenen Rollback:

1. Tile-Schreiber kontrolliert stilllegen und laufende Reservierungen ablaufen
   lassen; keine alte und neue Store-Version gleichzeitig schreiben lassen.
   Lease-Ablauf allein beweist bei der alten Version mit unbegrenzter DNS-Wartezeit
   keine Ruhe. Tatsächliche In-flight-Arbeit und Sperren müssen ebenfalls frei sein;
   andernfalls kein Paket-/Zustandswechsel.
2. Den dann aktuellen v2-Zustand privat und bytegenau sichern und hashen.
3. Mit `tools/prepare-miss-state-rollback.php --prepare-legacy INPUT EXPECTED_SHA256 NEW_OUTPUT`
   eine neue v1-Datei vorbereiten. Das Werkzeug ersetzt keine aktive Datei.
   Die Ledger-Einträge entfallen, ihre bereits verbuchten Worst-case-Belastungen
   bleiben erhalten. Die reine Transformation und das Werkzeug sind getestet.
4. Den vorbereiteten Zustand und die vorherige Modulversion unter einem frischen
   Hash-/Eigentums-Gate konsistent anwenden, unabhängig zurücklesen und
   erst dann wieder Anfragen zulassen. Kein altes Budget-Backup zurückspielen,
   keine Cache-Löschung zur vermeintlichen Reparatur.

Fehlerhafte Zustände benötigen eine gesonderte Wiederherstellungsentscheidung.
Auch das normale `clear()` umgeht die Fail-closed-Prüfung nicht.

## Noch offene Abnahme-Gates

- Genau begrenzte Windows-Rechtehärtung bzw. ausdrückliche Risikoentscheidung.
- Separates Update des gemeinsamen PHP-Prüfwerkzeugs.
- Entscheidung über die native Pre-HTTP- statt Pre-TCP-Adressprüfung.
- Frischer synthetischer Connect-Header-/Negativtest und realer Transporttest
  nach eigener Freigabe; bisherige Forwarding-Belege ersetzen keinen neuen Test.
- Erst anschließend konkrete Aktivierung mit Paket-/Konfigurationsabgleich,
  gesichertem Zustands-Rollback und unabhängigem Postflight.

Keine private ObjectID, Trackerkennung, Koordinate, Bewegungsspur, private URL
oder installationsspezifischer Dateipfad ist Bestandteil dieses Berichts.
