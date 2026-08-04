# Vorbereitete kurze Antwort für den bestehenden Symcon-Forumbeitrag

Hallo zusammen,

der Module Validator ist aktuell reproduzierbar nicht funktionsfähig. Schon
beim Laden beziehungsweise Wechseln des Dateityps erscheint
`ReferenceError: Can't find variable: $` in `SetSchema` (Zeile 672); beim Klick
auf „Validieren“ folgt derselbe Fehler in `SetOutput` (Zeile 711). In Chromium
lautet die Meldung entsprechend `$ is not defined`.

Im aktuell ausgelieferten HTML wird AJV 6.10.2 geladen, jQuery jedoch nicht.
Der Validator-Code verwendet `$` weiterhin in `LoadData`, `SetSchema`,
`Validate` und `SetOutput`. Dadurch erreicht die Eingabe die eigentliche
AJV-Prüfung nicht.

Ein Gegencheck der verwendeten Testdateien mit den offiziellen
Symcon-Schemas und derselben AJV-Version 6.10.2 war erfolgreich. Details,
Reproduktion und einen Korrekturvorschlag habe ich im Anhang zusammengefasst.

Könnt ihr euch das bitte ansehen? Danke!

**Vorgesehener Anhang:**
`symcon-module-validator-referenceerror-korrekturvorschlag.pdf`
