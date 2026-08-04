# Symcon Module Validator: fehlende `$`-Abhängigkeit verhindert jede Validierung

**Stand:** 29.07.2026
**Betroffene Seite:**
<https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/module-validator/>

## 1. Kurzfassung

Der Validator bricht vor der Schema-Prüfung ab, weil der ausgelieferte
JavaScript-Code jQuery-Notation (`$`) verwendet, die Seite aber keine
jQuery-Bibliothek lädt. Der Fehler betrifft nicht nur die Ausgabe, sondern alle
vier Validatorfunktionen.

## 2. Reproduktion

1. Validator-Seite öffnen.
2. Einen Dateityp wählen, beispielsweise `library.json`.
3. Gültiges JSON in „Inhalt“ einfügen.
4. „Validieren“ anklicken.

Ist-Ergebnis:

- Safari: `ReferenceError: Can't find variable: $`
- Chromium: `ReferenceError: $ is not defined`
- zuerst `SetSchema` bei Zeile 672, anschließend `SetOutput` bei Zeile 711
- kein fachliches Validierungsergebnis

Soll-Ergebnis:

Die JSON-Datei wird gegen das gewählte Symcon-Schema geprüft. Anschließend
werden entweder ein gültiges Ergebnis oder konkrete Schemafehler angezeigt.

## 3. Technische Ursache

Der aktuelle Seitenquelltext lädt AJV 6.10.2, aber kein jQuery. Gleichzeitig
enthalten folgende Funktionen `$`-Zugriffe:

- `LoadData`: `$('#sourcefile')`, `$('#inputbox')`
- `SetSchema`: `$('#schematype')`
- `Validate`: `$('#schematype')`, `$('#inputbox')`
- `SetOutput`: `$('#validationresulttitle')`, `$('#validationresult')`

Das bloße Reparieren der zwei im Stacktrace sichtbaren Stellen wäre daher
unvollständig. Wahrscheinlich ist die frühere jQuery-Abhängigkeit bei einer
Überarbeitung der Website entfallen. Das ist eine Schlussfolgerung aus dem
aktuell ausgelieferten HTML und keine Aussage zur Änderungshistorie.

## 4. Korrekturvorschlag

Bevorzugt sollten die wenigen jQuery-Aufrufe durch native Browser-APIs ersetzt
werden. Das vermeidet eine zusätzliche Abhängigkeit. Ein kurzfristiger Hotfix
könnte jQuery wieder einbinden, würde aber die unnötige Abhängigkeit und
bestehende Robustheitsprobleme konservieren.

Minimale Ersetzungen:

```text
$('#schematype').val()
-> document.getElementById('schematype').value

$('#inputbox').val()
-> document.getElementById('inputbox').value

$('#sourcefile')[0].files[0]
-> document.getElementById('sourcefile').files[0]

.html(text)
-> .textContent = text

.css('color', color)
-> .style.color = color

.attr('hidden', value)
-> .hidden = Boolean(value)
```

Zusätzlich empfohlen:

- das asynchrone Laden des Schemas vor `Validate()` abwarten;
- HTTP- und Schema-Ladefehler getrennt von ungültigem Eingabe-JSON melden;
- AJV-Ladefehler nicht fälschlich als „kein gültiges JSON“ ausgeben;
- Ergebnisse mit `textContent` und Zeilenumbrüchen statt `innerHTML` rendern;
- `ajv.errorsText(errors, { separator: '\n' })` verwenden;
- `/data\./g` statt `new RegExp('data.', 'g')` verwenden, weil der Punkt im
  bestehenden Ausdruck als Regex-Wildcard wirkt;
- bei fehlender Datei in `LoadData` sauber abbrechen.

### Kompakter robuster Kern

```js
let schemaPromise;

function element(id) {
  return document.getElementById(id);
}

function SetSchema() {
  const selected = element('schematype').value;
  const url = `/assets/files/validation/${selected}Schema.json`;

  schemaPromise = fetch(url, { cache: 'no-cache' })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.text();
    })
    .catch(error => {
      SetOutput(
        'Das Validator-Schema konnte nicht geladen werden.',
        'red',
        error.message,
        false,
        false
      );
      return null;
    });

  return schemaPromise;
}

async function Validate() {
  const fileType = element('schematype').value;
  const inputText = element('inputbox').value;

  if (inputText.trim() === '') {
    SetOutput('', '', '', true, true);
    return;
  }

  let inputData;
  try {
    inputData = JSON.parse(inputText);
  } catch {
    SetOutput(
      `Die ${fileType}.json beinhaltet kein gültiges JSON.`,
      'red',
      '',
      false,
      true
    );
    return;
  }

  const schemaText = await (schemaPromise || SetSchema());
  if (schemaText === null) {
    return;
  }

  try {
    if (typeof Ajv !== 'function') {
      throw new Error('AJV wurde nicht geladen');
    }

    const ajv = new Ajv({ allErrors: true });
    const validate = ajv.compile(JSON.parse(schemaText));

    if (validate(inputData)) {
      SetOutput(
        `Die ${fileType}.json ist gültig.`,
        'white',
        '',
        false,
        true
      );
      return;
    }

    const errors = ajv
      .errorsText(validate.errors, { separator: '\n' })
      .replace(/data\./g, `${fileType} `);

    SetOutput('Die Datei ist fehlerhaft:', 'red', errors, false, false);
  } catch (error) {
    SetOutput(
      'Der Validator konnte die Prüfung nicht ausführen.',
      'red',
      error.message,
      false,
      false
    );
  }
}

function SetOutput(
  titleText,
  titleColor,
  resultText,
  titleHidden,
  resultHidden
) {
  const title = element('validationresulttitle');
  const result = element('validationresult');

  title.textContent = titleText;
  title.style.color = titleColor;
  title.hidden = Boolean(titleHidden);

  result.textContent = resultText;
  result.style.whiteSpace = 'pre-line';
  result.hidden = Boolean(resultHidden);
}
```

Für `LoadData`:

```js
function LoadData() {
  const file = element('sourcefile').files[0];
  if (!file) {
    return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    element('inputbox').value = reader.result;
  };
  reader.onerror = () => {
    SetOutput(
      'Die Datei konnte nicht gelesen werden.',
      'red',
      '',
      false,
      true
    );
  };
  reader.readAsText(file, 'UTF-8');
}
```

`SetSchema()` sollte nach den Funktionsdefinitionen einmal aufgerufen werden,
wenn das Schema sofort vorgeladen werden soll. Ohne Initialaufruf lädt
`Validate()` es beim ersten Prüflauf.

## 5. Verifikation und Akzeptanzkriterien

- Der initiale Seitenaufruf erzeugt keinen Konsolenfehler.
- Der Wechsel zwischen `library.json`, `module.json`, `locale.json` und
  `form.json` lädt jeweils das passende Schema.
- Texteingabe und Dateiauswahl funktionieren in Safari, Chrome/Chromium und
  Firefox.
- Eine gültige Datei liefert „… ist gültig“.
- Syntaktisch ungültiges JSON liefert nur den JSON-Hinweis.
- Schemawidriges, aber syntaktisch gültiges JSON liefert konkrete AJV-Fehler.
- Schema-, Netzwerk- und AJV-Fehler werden als Validatorfehler und nicht als
  Eingabefehler ausgewiesen.
- Es treten keine `$ is not defined`- oder `Can't find variable: $`-Fehler auf.

## 6. Gegenprobe

Die im konkreten Test verwendete `library.json` und vier `module.json` bestanden
separat gegen die offiziellen Symcon-Schemas mit derselben AJV-Version 6.10.2.
Dies belegt nicht die Funktionsfähigkeit der Weboberfläche, grenzt den
beobachteten Defekt aber auf deren JavaScript- und Abhängigkeitsschicht ein.

## 7. Quellen

- [Bestehender Forumbeitrag](https://community.symcon.de/t/php-module-json-dateien-mit-dem-module-validator-ueberpruefen/51071)
- [Betroffener Module Validator](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/module-validator/)
- [Offizielle Tool-Übersicht](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/)
